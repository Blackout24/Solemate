<?php
// solemate_payment_status.php — AJAX poll endpoint
header('Content-Type: application/json');
require 'includes/solemate_database_connection.php';
require_once 'includes/solemate_payfast.php';

$cartRef = trim($_GET['cart_ref'] ?? '');
if (!$cartRef) { echo json_encode(['paid' => false]); exit; }

$stmt = $pdo->prepare("SELECT status, amount_gross, customer_email FROM dbo.payments WHERE cart_ref=?");
$stmt->execute([$cartRef]);
$row = $stmt->fetch();

// Already confirmed — just return paid
if ($row && $row['status'] === 'complete') {
    echo json_encode(['paid' => true]);
    exit;
}

// ── Sandbox fallback ──────────────────────────────────────
// PayFast can't POST ITN to localhost, so the notify endpoint
// never fires. In sandbox mode we process the confirmation here
// so the return page can complete without needing ngrok.
if ($row && pfConfig()['sandbox']) {
    require_once 'includes/solemate_ecommerce.php';
    require_once 'includes/solemate_mailer.php';

    $amountPaid = (float)$row['amount_gross'];

    // Mark payment complete
    $pdo->prepare("
        UPDATE dbo.payments
        SET status = N'complete', paid_at = GETDATE()
        WHERE cart_ref = ?
    ")->execute([$cartRef]);

    // Mark booking paid — Pending until shoes physically arrive
    // (drop-off today = Received; future date / courier / home = Pending)
    $bkMeta = $pdo->prepare("
        SELECT b.drop_date, b.collection, s.service_key
        FROM dbo.booking b
        LEFT JOIN dbo.services s ON b.service_id = s.id
        WHERE b.cart_ref = ?
    ");
    $bkMeta->execute([$cartRef]);
    $bkMeta     = $bkMeta->fetch();
    $dropDate   = $bkMeta['drop_date']   ?? null;
    $colMethod  = $bkMeta['collection']  ?? '';
    $serviceKey = $bkMeta['service_key'] ?? '';

    if ($serviceKey === 'custom-shoes') {
        $bkStatus = 'Design confirmed';
    } else {
        $bkStatus = ($colMethod === 'drop-off' && $dropDate && strtotime($dropDate) <= strtotime('today'))
            ? 'Received'
            : 'Pending';
    }

    $pdo->prepare("
        UPDATE dbo.booking
        SET payment_status = N'paid',
            status         = ?,
            amount_paid    = ?
        WHERE cart_ref = ?
    ")->execute([$bkStatus, $amountPaid, $cartRef]);

    // Activate any gift card purchased in this payment
    $pdo->prepare("
        UPDATE dbo.gift_cards SET is_active = 1 WHERE cart_ref = ?
    ")->execute([$cartRef]);

    // Award loyalty points (with tier multiplier) + complete any referral
    $custRow = $pdo->prepare("SELECT id, loyalty_points FROM dbo.customers WHERE email = ?");
    $custRow->execute([$row['customer_email']]);
    $cust = $custRow->fetch();
    $siteSettings = getSiteSettings($pdo);
    if ($cust && $amountPaid > 0) {
        if ($siteSettings['loyalty']) {
            $earned = loyaltyPointsEarnedWithTier($amountPaid, (int)$cust['loyalty_points']);
            awardLoyaltyPoints($pdo, (int)$cust['id'], $earned, $cartRef, "Earned on payment — R{$amountPaid}");
        }
        // Update total_spent
        $pdo->prepare("UPDATE dbo.customers SET total_spent=total_spent+? WHERE id=?")
            ->execute([$amountPaid, (int)$cust['id']]);
        // Mark referral complete if this is their first paid booking
        if ($siteSettings['referrals']) {
            $bookingCount = $pdo->prepare("SELECT COUNT(*) FROM dbo.booking WHERE email=? AND payment_status=N'paid'");
            $bookingCount->execute([$row['customer_email']]);
            if ((int)$bookingCount->fetchColumn() === 1) {
                try { completeReferral($pdo, (int)$cust['id']); } catch (\Throwable $e) {
                    error_log('[SoleMate referral] ' . $e->getMessage());
                }
            }
        }
    }

    // Send booking confirmation email
    try {
        $bkRow = $pdo->prepare("
            SELECT b.*, s.service_name
            FROM dbo.booking b
            LEFT JOIN dbo.services s ON b.service_id = s.id
            WHERE b.cart_ref = ?
        ");
        $bkRow->execute([$cartRef]);
        $booking = $bkRow->fetch();

        if ($booking) {
            sendBookingConfirmation([
                'order_ref'  => $booking['order_ref'],
                'name'       => $booking['customer_name'],
                'email'      => $booking['email'],
                'service'    => $booking['service_name'] ?? 'Shoe Service',
                'shoe'       => $booking['shoe_type']    ?? '',
                'drop_date'  => !empty($booking['drop_date'])
                                    ? date('d M Y', strtotime($booking['drop_date']))
                                    : 'TBC',
                'collection' => ucfirst($booking['collection'] ?? 'drop-off'),
                'price'      => $amountPaid,
                'price_from' => false,
            ]);
        }
    } catch (Exception $e) {
        error_log('[SoleMate sandbox mailer] ' . $e->getMessage());
    }

    echo json_encode(['paid' => true]);
    exit;
}

echo json_encode(['paid' => false]);