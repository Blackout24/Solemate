<?php
// ═══════════════════════════════════════════════════════
//  solemate_payment_notify.php  —  PayFast ITN Handler
//
//  Server-to-server POST from PayFast after every payment.
//  MUST respond HTTP 200 OK — no HTML output.
//
//  This file:
//    1. Validates the ITN (IP + signature + amount)
//    2. Updates payment record → 'complete'
//    3. Updates booking → payment_status='paid', status='Received'
//    4. Redeems coupon usage count
//    5. Deducts gift card balance
//    6. Awards loyalty points to logged-in customer
//    7. Sends confirmation email
// ═══════════════════════════════════════════════════════
require_once 'includes/solemate_database_connection.php';
require_once 'includes/solemate_payfast.php';
require_once 'includes/solemate_ecommerce.php';
require_once 'includes/solemate_mailer.php';
require_once 'includes/solemate_customer_mailer.php';

// Always return 200 to PayFast (even on our own errors)
http_response_code(200);

// ── Log helper (append to error_log) ────────────────────
function pfLog(string $msg): void {
    error_log('[SoleMate PayFast ITN] ' . $msg);
}

// ── Read POST ────────────────────────────────────────────
$post = $_POST;
if (empty($post)) {
    pfLog('Empty POST — possible direct browser access.');
    exit;
}

pfLog('ITN received: ' . json_encode(array_intersect_key($post, array_flip(['m_payment_id','payment_status','amount_gross','pf_payment_id']))));

$cartRef = trim($post['m_payment_id'] ?? $post['custom_str1'] ?? '');
if (!$cartRef) { pfLog('No cart_ref in ITN.'); exit; }

// ── Fetch payment record ─────────────────────────────────
$pmtStmt = $pdo->prepare("SELECT * FROM dbo.payments WHERE cart_ref=?");
$pmtStmt->execute([$cartRef]);
$pmt = $pmtStmt->fetch();

if (!$pmt) { pfLog("Payment record not found for cart_ref: {$cartRef}"); exit; }
if ($pmt['status'] === 'complete') { pfLog("Duplicate ITN for cart_ref: {$cartRef} — already processed."); exit; }

// ── Validate ITN ─────────────────────────────────────────
$valid = pfValidateITN($post, (float)$pmt['amount_gross']);
if (!$valid['ok']) {
    pfLog('ITN validation failed: ' . $valid['reason']);
    // Do NOT update anything — possible fraud or misconfiguration
    exit;
}

pfLog("ITN valid for cart_ref: {$cartRef}");

// ── Update payment record ────────────────────────────────
$pdo->prepare("
    UPDATE dbo.payments
    SET status=N'complete', pf_payment_id=?, payment_method=?,
        amount_fee=?, amount_net=?, paid_at=GETDATE()
    WHERE cart_ref=?
")->execute([
    $post['pf_payment_id'] ?? null,
    $post['payment_method'] ?? null,
    (float)($post['amount_fee'] ?? 0),
    (float)($post['amount_net'] ?? 0),
    $cartRef,
]);

// ── Update all bookings with this cart_ref ───────────────
// Fetch booking meta including service_key to detect custom shoes
$bkMeta = $pdo->prepare("
    SELECT b.drop_date, b.collection, s.service_key
    FROM dbo.booking b
    LEFT JOIN dbo.services s ON b.service_id = s.id
    WHERE b.cart_ref = ?
");
$bkMeta->execute([$cartRef]);
$bkMeta     = $bkMeta->fetch();
$dropDate   = $bkMeta['drop_date']  ?? null;
$colMethod  = $bkMeta['collection'] ?? '';
$serviceKey = $bkMeta['service_key'] ?? '';

$isCustomShoe = ($serviceKey === 'custom-shoes');

if ($isCustomShoe) {
    // Payment confirms the production order — always 'Design confirmed'
    $bkStatus = 'Design confirmed';
} else {
    // Standard booking (drop-off, courier, home): Pending only while the date is
    // still days ahead; today or earlier → Received
    $bkStatus = ($dropDate && strtotime($dropDate) > strtotime('today'))
        ? 'Pending'
        : 'Received';
}

$pdo->prepare("
    UPDATE dbo.booking
    SET payment_status=N'paid', payment_ref=?, status=?, amount_paid=?
    WHERE cart_ref=?
")->execute([
    $post['pf_payment_id'] ?? null,
    $bkStatus,
    (float)($post['amount_gross'] ?? 0),
    $cartRef,
]);

// ── Care-product order? Mark paid + email (idempotent) ───
if (completeCareOrder($pdo, $cartRef)) { pfLog("Care order completed for cart_ref: {$cartRef}"); }

// ── Redeem coupon ────────────────────────────────────────
if (!empty($pmt['coupon_code'])) {
    redeemCoupon($pdo, $pmt['coupon_code']);
    pfLog("Coupon redeemed: {$pmt['coupon_code']}");
}

// ── Deduct gift card ─────────────────────────────────────
if (!empty($pmt['gift_card_code']) && (float)$pmt['gift_card_discount'] > 0) {
    redeemGiftCard($pdo, $pmt['gift_card_code'], (float)$pmt['gift_card_discount'], $cartRef);
    pfLog("Gift card deducted: {$pmt['gift_card_code']} R{$pmt['gift_card_discount']}");
}

// ── Redeem loyalty points ────────────────────────────────
if ((int)($pmt['loyalty_redeemed'] ?? 0) > 0) {
    // Find customer by email
    $custStmt = $pdo->prepare("SELECT id FROM dbo.customers WHERE email=?");
    $custStmt->execute([$pmt['customer_email']]);
    $cust = $custStmt->fetch();
    if ($cust) {
        redeemLoyaltyPoints($pdo, (int)$cust['id'], (int)$pmt['loyalty_redeemed'], $cartRef);
        pfLog("Loyalty redeemed: {$pmt['loyalty_redeemed']} pts for customer {$cust['id']}");
    }
}

// ── Award loyalty points on paid amount ──────────────────
$amountPaid = (float)($post['amount_gross'] ?? 0);
if ($amountPaid > 0 && getSiteSettings($pdo)['loyalty']) {
    $custStmt = $pdo->prepare("SELECT id FROM dbo.customers WHERE email=?");
    $custStmt->execute([$pmt['customer_email']]);
    $cust = $custStmt->fetch();
    if ($cust) {
        $earned = loyaltyPointsEarned($amountPaid);
        awardLoyaltyPoints($pdo, (int)$cust['id'], $earned, $cartRef, "Earned on payment — R{$amountPaid}");
        pfLog("Loyalty awarded: {$earned} pts to customer {$cust['id']}");
    }
}

// ── Activate gift cards purchased in this payment ────────
// (If this cart_ref corresponds to a gift card purchase, activate it)
$gcStmt = $pdo->prepare("UPDATE dbo.gift_cards SET is_active=1 WHERE cart_ref=?");
$gcStmt->execute([$cartRef]);

// ── Email gift card code to recipient ────────────────────
// (Runs only when this payment is for a gift card purchase, not a booking)
$gcEmailStmt = $pdo->prepare("SELECT * FROM dbo.gift_cards WHERE cart_ref=? AND is_active=1");
$gcEmailStmt->execute([$cartRef]);
$gcEmailRow = $gcEmailStmt->fetch();
if ($gcEmailRow && !empty($gcEmailRow['recipient_email'])) {
    $emailSent = sendGiftCardEmail([
        'recipient_email' => $gcEmailRow['recipient_email'],
        'recipient_name'  => $gcEmailRow['recipient_name']  ?? '',
        'sender_name'     => $gcEmailRow['purchased_by']    ?? '',
        'sender_email'    => $pmt['customer_email'],
        'code'            => $gcEmailRow['code'],
        'balance'         => (float)$gcEmailRow['balance'],
        'expires_at'      => $gcEmailRow['expires_at']      ?? null,
        'message'         => $gcEmailRow['message']          ?? '',
    ]);
    pfLog($emailSent
        ? "Gift card email sent to: {$gcEmailRow['recipient_email']} (code: {$gcEmailRow['code']})"
        : "Gift card email FAILED for code: {$gcEmailRow['code']}"
    );
}

// ── Send confirmation email ──────────────────────────────
try {
    $bkRow = $pdo->prepare("
        SELECT b.*, s.service_name, s.service_key
        FROM dbo.booking b
        LEFT JOIN dbo.services s ON b.service_id = s.id
        WHERE b.cart_ref = ?
    ");
    $bkRow->execute([$cartRef]);
    $booking = $bkRow->fetch();

    if ($booking) {
        if (($booking['service_key'] ?? '') === 'custom-shoes') {
            // ── Custom shoe confirmation ──────────────────────
            $custDec = json_decode($booking['customization_data'] ?? '{}', true) ?? [];
            $courierFee = in_array($booking['collection'], ['courier','home']) ? 85.00 : 0.00;
            sendCustomShoeConfirmation([
                'order_ref'        => $booking['order_ref'],
                'name'             => $booking['customer_name'],
                'email'            => $booking['email'],
                'shoe_size'        => $custDec['size'] ?? '',
                'collection'       => ucfirst($booking['collection']),
                'drop_date'        => $booking['drop_date'] ? date('d M Y', strtotime($booking['drop_date'])) : 'TBC',
                'price'            => $amountPaid - $courierFee,
                'courier_fee'      => $courierFee,
                'total'            => $amountPaid,
                'colors'           => $custDec['colors'] ?? [],
                'delivery_address' => '',
                'notes'            => $booking['notes'] ?? '',
            ]);
            pfLog("Custom shoe confirmation email sent for: {$booking['order_ref']}");
        } else {
            // ── Standard booking confirmation ─────────────────
            sendBookingConfirmation([
                'order_ref'  => $booking['order_ref'],
                'name'       => $booking['customer_name'],
                'email'      => $booking['email'],
                'service'    => $booking['service_name'] ?? 'Shoe Service',
                'shoe'       => $booking['shoe_type'] ?? '',
                'drop_date'  => $booking['drop_date'] ? date('d M Y', strtotime($booking['drop_date'])) : 'TBC',
                'collection' => ucfirst($booking['collection']),
                'delivery_address' => $booking['delivery_address'] ?? '',
                'price'      => $amountPaid,
                'price_from' => false,
            ]);
        }
    }
} catch (Exception $e) {
    pfLog('Email failed: ' . $e->getMessage());
}

pfLog("ITN processing complete for cart_ref: {$cartRef}");
exit;