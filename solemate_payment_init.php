<?php
// ═══════════════════════════════════════════════════════
//  solemate_payment_init.php  —  PayFast Redirect
//
//  Creates a pending booking + payment record, then
//  auto-submits the PayFast form.
// ═══════════════════════════════════════════════════════
session_start();
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require 'includes/solemate_ecommerce.php';
require 'includes/solemate_payfast.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: solemate_checkout.php'); exit;
}

$cart = cartGet();
if (!$cart['item']) {
    header('Location: solemate_homepage.php?page=booking'); exit;
}

$item  = $cart['item'];
$fn    = trim($_POST['first_name'] ?? $item['customer_name'] ?? '');
$ln    = trim($_POST['last_name']  ?? $item['surname']       ?? '');
$email = strtolower(trim($_POST['email'] ?? $item['email']   ?? ''));
$phone = trim($_POST['phone'] ?? $item['phone']              ?? '');

if (!$fn || !$email) {
    header('Location: solemate_checkout.php'); exit;
}

$isCustom = !empty($item['is_custom']);
$total    = cartTotal($cart); // includes collection fee

$cartRef  = generateCartRef($pdo);

// Custom shoe orders get SM-CUSTOM- prefix
if ($isCustom) {
    do {
        $orderRef = 'SM-CUSTOM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $chk = $pdo->prepare("SELECT 1 FROM dbo.booking WHERE order_ref=?");
        $chk->execute([$orderRef]);
    } while ($chk->fetchColumn());
} else {
    $orderRef = generateOrderRef($pdo);
}

// ── 1. Create payment record (pending) ───────────────────
$pdo->prepare("
    INSERT INTO dbo.payments
        (cart_ref,amount_gross,status,customer_email,
         coupon_code,coupon_discount,gift_card_code,gift_card_discount,
         loyalty_redeemed,loyalty_discount)
    VALUES (?,?,N'pending',?,?,?,?,?,?,?)
")->execute([
    $cartRef, $total, $email,
    $cart['coupon_code']     ?? null, (float)($cart['coupon_discount']    ?? 0),
    $cart['gift_card_code']  ?? null, (float)($cart['gift_card_discount'] ?? 0),
    (int)($cart['loyalty_points']  ?? 0), (float)($cart['loyalty_discount'] ?? 0),
]);

// ── 2. Create booking record (pending payment) ───────────
$status = 'Pending';
$pdo->prepare("
    INSERT INTO dbo.booking
        (order_ref,cart_ref,customer_name,email,phone,
         service_id,shoe_type,drop_date,collection,notes,status,
         payment_status,customization_data)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,N'pending',?)
")->execute([
    $orderRef, $cartRef,
    trim($fn.' '.$ln), $email, $phone,
    $item['service_id'], $item['shoe_type'], $item['drop_date'],
    $item['collection'], $item['notes'], $status,
    $item['customization_data'] ?? null,
]);

// ── 3. Store cart_ref in session for return page ─────────
$_SESSION['sm_cart_ref'] = $cartRef;
$_SESSION['sm_order_ref'] = $orderRef;

// ── 4. Build PayFast form ────────────────────────────────
$pfData = pfBuildPaymentData([
    'first_name'       => $fn,
    'last_name'        => $ln,
    'email'            => $email,
    'cart_ref'         => $cartRef,
    'amount'           => $total,
    'item_name'        => $item['service_name'],
    'item_description' => $item['shoe_type'] ? $item['service_name'].' for '.$item['shoe_type'] : $item['service_name'],
]);

$pfCfg = pfConfig();
$pfUrl  = $pfCfg['url'];
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
<form id="pf" action="<?= htmlspecialchars($pfUrl,ENT_QUOTES,'UTF-8') ?>" method="POST">
  <?php foreach ($pfData as $k => $v): ?>
  <input type="hidden" name="<?= htmlspecialchars($k,ENT_QUOTES) ?>" value="<?= htmlspecialchars($v,ENT_QUOTES) ?>">
  <?php endforeach; ?>
</form>
<script>setTimeout(()=>document.getElementById('pf').submit(),400);</script>
</body>
</html>
