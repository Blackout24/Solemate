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

    $result = completePaymentManually($pdo, $cartRef, 'sandbox-auto');

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
                'price'      => (float)$row['amount_gross'],
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