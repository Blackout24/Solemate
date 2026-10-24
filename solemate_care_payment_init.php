<?php
// ═══════════════════════════════════════════════════════
//  solemate_care_payment_init.php  —  care products → PayFast
//  Re-prices the basket from the database, creates a pending
//  payment + care order, then auto-submits the PayFast form.
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require_once 'includes/solemate_care_orders.php';
require 'includes/solemate_payfast.php';

function careBack(string $msg): void {
    header('Location: solemate_care_checkout.php?err=' . urlencode($msg)); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: solemate_care_checkout.php'); exit; }
if (!isCustomerLoggedIn())                 { header('Location: solemate_customer_login.php'); exit; }
if (!careTableExists($pdo, 'care_orders') || !careTableExists($pdo, 'care_products')) {
    careBack('Shop checkout is not set up yet.');
}

$basket = careCartLines($pdo, $_SESSION['care_cart'] ?? []);
if (!$basket['items']) { header('Location: solemate_homepage.php?page=cart'); exit; }

$fn     = trim($_POST['first_name'] ?? '');
$ln     = trim($_POST['last_name']  ?? '');
$email  = strtolower(trim($_POST['email'] ?? ''));
$phone  = trim($_POST['phone'] ?? '');
$method = ($_POST['delivery_method'] ?? 'collect') === 'delivery' ? 'delivery' : 'collect';
$addr   = trim($_POST['delivery_address'] ?? '');

if ($fn === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) careBack('Please enter your name and a valid email address.');
if ($phone === '')                                            careBack('Please enter a phone number.');
if ($method === 'delivery' && mb_strlen($addr) < 8)           careBack('Please enter a full delivery address.');
if ($method === 'collect') $addr = '';

$subtotal = $basket['subtotal'];
$fee      = $method === 'delivery' ? CARE_DELIVERY_FEE : 0.0;
$total    = round($subtotal + $fee, 2);
$name     = trim($fn . ' ' . $ln);

$cartRef  = generateCartRef($pdo);
$orderRef = generateCareOrderRef($pdo);

try {
    $pdo->beginTransaction();

    // 1. payment record (same table the booking flow uses)
    $pdo->prepare("
        INSERT INTO dbo.payments (cart_ref, amount_gross, status, customer_email)
        VALUES (?, ?, N'pending', ?)
    ")->execute([$cartRef, $total, $email]);

    // 2. care order
    $pdo->prepare("
        INSERT INTO dbo.care_orders
            (order_ref, cart_ref, customer_id, customer_name, email, phone,
             delivery_method, delivery_address, subtotal, delivery_fee, total, status, payment_status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,N'Pending',N'pending')
    ")->execute([$orderRef, $cartRef, getCustomerId(), $name, $email, $phone,
                 $method, $addr ?: null, $subtotal, $fee, $total]);
    $idq = $pdo->prepare("SELECT id FROM dbo.care_orders WHERE order_ref=?");
    $idq->execute([$orderRef]);
    $orderId = (int)$idq->fetchColumn();

    // 3. line items (price snapshot)
    $ins = $pdo->prepare("INSERT INTO dbo.care_order_items (order_id, product_id, name, unit_price, qty, line_total) VALUES (?,?,?,?,?,?)");
    foreach ($basket['items'] as $it) {
        $ins->execute([$orderId, $it['id'], $it['name'], $it['price'], $it['qty'], $it['line']]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[SoleMate care order] ' . $e->getMessage());
    careBack('Could not create your order. Please try again.');
}

// The basket is cleared on the return page once payment is confirmed
$_SESSION['sm_cart_ref']       = $cartRef;
$_SESSION['sm_care_order_ref'] = $orderRef;

$desc = [];
foreach ($basket['items'] as $it) $desc[] = $it['name'] . ' x' . $it['qty'];

$pfData = pfBuildPaymentData([
    'first_name'       => $fn,
    'last_name'        => $ln,
    'email'            => $email,
    'cart_ref'         => $cartRef,
    'amount'           => $total,
    'item_name'        => 'Care products (' . $basket['count'] . ' item' . ($basket['count'] > 1 ? 's' : '') . ')',
    'item_description' => implode(', ', $desc),
]);
$pfUrl = pfConfig()['url'];
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><title>Redirecting to PayFast…</title>
<style>
body{font-family:'DM Sans',system-ui,sans-serif;background:#1A0F08;color:#FAF7F2;
  min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1rem}
.spinner{width:40px;height:40px;border:3px solid rgba(200,169,126,.2);border-top-color:#C8A97E;
  border-radius:50%;animation:spin 0.7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
p{font-size:14px;color:rgba(200,169,126,.7)}
</style>
</head>
<body>
<div class="spinner"></div>
<p>Redirecting to PayFast…</p>
<form id="pf" action="<?= htmlspecialchars($pfUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST">
  <?php foreach ($pfData as $k => $v): ?>
  <input type="hidden" name="<?= htmlspecialchars($k, ENT_QUOTES) ?>" value="<?= htmlspecialchars($v, ENT_QUOTES) ?>">
  <?php endforeach; ?>
</form>
<script>setTimeout(()=>document.getElementById('pf').submit(),400);</script>
</body>
</html>
