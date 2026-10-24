<?php
// ═══════════════════════════════════════════════════════
//  solemate_care_checkout.php  —  Care products checkout
//  Reads the session basket, asks for contact + delivery
//  details, then posts to solemate_care_payment_init.php
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require_once 'includes/solemate_care_orders.php';

// Sign in first (same rule as bookings) — return here afterwards
if (!isCustomerLoggedIn()) {
    header('Location: solemate_customer_login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/solemate_care_checkout.php'));
    exit;
}

if (!careTableExists($pdo, 'care_orders') || !careTableExists($pdo, 'care_products')) {
    http_response_code(503);
    exit('Shop checkout is not set up yet. Run the care products + care orders SQL migrations first.');
}

$basket = careCartLines($pdo, $_SESSION['care_cart'] ?? []);
if (!$basket['items']) {
    header('Location: solemate_homepage.php?page=cart'); exit;
}
$_SESSION['care_cart'] = $basket['cart'];

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Prefill from the customer's account
$cs = $pdo->prepare("SELECT first_name, last_name, email, phone FROM dbo.customers WHERE id=?");
$cs->execute([getCustomerId()]);
$cust = $cs->fetch() ?: [];

// Default saved address (if any)
$defaultAddr = '';
try {
    $as = $pdo->prepare("SELECT TOP 1 street, suburb, city, province, postal_code FROM dbo.customer_addresses WHERE customer_id=? ORDER BY is_default DESC, id DESC");
    $as->execute([getCustomerId()]);
    if ($a = $as->fetch()) {
        $defaultAddr = implode(', ', array_filter([$a['street'], $a['suburb'], $a['city'], $a['province'], $a['postal_code']]));
    }
} catch (\Throwable $e) {}

$err = trim($_GET['err'] ?? '');
$sub = $basket['subtotal'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Checkout — SoleMate</title>
<script>try{if(localStorage.getItem('solemate-theme')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--cream:#F8F3EC;--tan:#B98A54;--brown:#5C3820;--dark:#1A0F08;--accent:#C1440E;--accent-rgb:193,68,14;--light:#EFDFC5;--muted:#7C6650;--success:#2D6A4F;--text:#241508;--card-bg:#FFFDFB;--border-rgb:92,56,32;color-scheme:light}
[data-theme="dark"]{--cream:#15100B;--tan:#DDBB8C;--brown:#C99568;--accent:#E8763B;--accent-rgb:232,118,59;--light:#2B2015;--muted:#B3A08C;--success:#5CC49A;--text:#F4EBDD;--card-bg:#1F1712;--border-rgb:224,203,178;color-scheme:dark}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--text);min-height:100vh;padding:1.75rem 1.25rem 4rem}
.wrap{max-width:900px;margin:0 auto}
.logo{font-family:'Playfair Display',serif;font-size:22px;color:var(--tan);text-decoration:none;display:inline-block;margin-bottom:1.5rem}
.logo span{color:var(--accent)}
h1{font-family:'Playfair Display',serif;font-size:1.75rem;margin-bottom:.35rem}
.sub{font-size:14px;color:var(--muted);margin-bottom:1.5rem}
.grid{display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start}
@media(max-width:760px){.grid{grid-template-columns:1fr}}
.card{background:var(--card-bg);border:1px solid rgba(var(--border-rgb),.12);border-radius:14px;padding:1.5rem}
.card h2{font-family:'Playfair Display',serif;font-size:16px;margin-bottom:1rem}
.row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
@media(max-width:520px){.row{grid-template-columns:1fr}}
.fg{margin-bottom:.9rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.fg input,.fg textarea{width:100%;border:1px solid rgba(var(--border-rgb),.2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:inherit;background:var(--cream);color:var(--text);outline:none}
.fg input:focus,.fg textarea:focus{border-color:var(--accent)}
.opt{display:flex;gap:.75rem;align-items:flex-start;border:1.5px solid rgba(var(--border-rgb),.18);border-radius:10px;padding:.85rem 1rem;margin-bottom:.6rem;cursor:pointer}
.opt:has(input:checked){border-color:var(--accent);background:rgba(var(--accent-rgb),.06)}
.opt input{margin-top:3px;accent-color:var(--accent)}
.opt b{font-size:14px}.opt small{display:block;color:var(--muted);font-size:12px;margin-top:2px}
.item{display:flex;justify-content:space-between;gap:.75rem;font-size:13px;padding:.45rem 0;border-bottom:1px solid rgba(var(--border-rgb),.08)}
.item span:last-child{white-space:nowrap}
.tot{display:flex;justify-content:space-between;font-size:13px;color:var(--muted);padding:.4rem 0}
.tot.big{font-size:15px;color:var(--text);font-weight:500;padding-top:.8rem;border-top:1px solid rgba(var(--border-rgb),.15);margin-top:.4rem}
.tot.big strong{font-family:'Playfair Display',serif;font-size:22px;color:var(--accent)}
.pay{width:100%;background:var(--accent);color:#fff;border:none;padding:14px;border-radius:9px;font-size:15px;font-weight:500;cursor:pointer;font-family:inherit;margin-top:1rem}
.pay:hover{opacity:.9}
.err{background:#fff8f6;border:1px solid rgba(212,86,26,.3);color:#8B2200;border-radius:9px;padding:.8rem 1rem;font-size:13px;margin-bottom:1rem}
[data-theme="dark"] .err{background:rgba(212,86,26,.14);color:#FF9A6B}
.back{font-size:13px;color:var(--muted);text-decoration:none;display:inline-block;margin-top:.75rem}
</style>
</head>
<body>
<div class="wrap">
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <h1>Checkout</h1>
  <p class="sub">Care products · pay securely with PayFast</p>

  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

  <form method="POST" action="solemate_care_payment_init.php" class="grid">
    <div>
      <div class="card" style="margin-bottom:1.25rem">
        <h2>Your details</h2>
        <div class="row">
          <div class="fg"><label>First name *</label><input type="text" name="first_name" required value="<?= e($cust['first_name'] ?? '') ?>"></div>
          <div class="fg"><label>Surname</label><input type="text" name="last_name" value="<?= e($cust['last_name'] ?? '') ?>"></div>
        </div>
        <div class="row">
          <div class="fg"><label>Email *</label><input type="email" name="email" required value="<?= e($cust['email'] ?? '') ?>"></div>
          <div class="fg"><label>Phone *</label><input type="tel" name="phone" required value="<?= e($cust['phone'] ?? '') ?>" placeholder="+27 82 000 0000"></div>
        </div>
      </div>

      <div class="card">
        <h2>Collection or delivery</h2>
        <label class="opt">
          <input type="radio" name="delivery_method" value="collect" checked onchange="syncDelivery()">
          <span><b>Collect in store — Free</b><small>123 Florida Road, Morningside · we'll email you when it's ready</small></span>
        </label>
        <label class="opt">
          <input type="radio" name="delivery_method" value="delivery" onchange="syncDelivery()">
          <span><b>Courier delivery — R<?= number_format(CARE_DELIVERY_FEE, 2) ?></b><small>Delivered to your address</small></span>
        </label>
        <div class="fg" id="addrWrap" style="display:none;margin-top:.8rem">
          <label>Delivery address *</label>
          <textarea name="delivery_address" id="addr" rows="3" placeholder="Street, suburb, city, postal code"><?= e($defaultAddr) ?></textarea>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Order summary</h2>
      <?php foreach ($basket['items'] as $it): ?>
      <div class="item"><span><?= e($it['name']) ?> <span style="color:var(--muted)">× <?= (int)$it['qty'] ?></span></span><span>R<?= number_format($it['line'], 2) ?></span></div>
      <?php endforeach; ?>
      <div class="tot" style="margin-top:.5rem"><span>Subtotal</span><span>R<?= number_format($sub, 2) ?></span></div>
      <div class="tot"><span id="feeLbl">Collection</span><span id="feeAmt">Free</span></div>
      <div class="tot big"><span>Total</span><strong id="totalAmt">R<?= number_format($sub, 2) ?></strong></div>
      <button type="submit" class="pay" id="payBtn">Pay R<?= number_format($sub, 2) ?> with PayFast →</button>
      <a class="back" href="solemate_homepage.php?page=cart">← Back to cart</a>
    </div>
  </form>
</div>
<script>
var SUB = <?= json_encode($sub) ?>, FEE = <?= json_encode(CARE_DELIVERY_FEE) ?>;
function money(n){ return 'R' + n.toLocaleString('en-ZA',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function syncDelivery(){
  var deliv = document.querySelector('input[name=delivery_method]:checked').value === 'delivery';
  document.getElementById('addrWrap').style.display = deliv ? 'block' : 'none';
  document.getElementById('addr').required = deliv;
  document.getElementById('feeLbl').textContent = deliv ? 'Courier delivery' : 'Collection';
  document.getElementById('feeAmt').textContent = deliv ? money(FEE) : 'Free';
  var t = SUB + (deliv ? FEE : 0);
  document.getElementById('totalAmt').textContent = money(t);
  document.getElementById('payBtn').textContent = 'Pay ' + money(t) + ' with PayFast →';
}
syncDelivery();
</script>
</body>
</html>
