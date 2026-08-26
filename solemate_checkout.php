<?php
// ═══════════════════════════════════════════════════════
//  solemate_checkout.php  —  Checkout + Discount + Pay
//
//  Receives booking details as POST from the homepage form.
//  Stores them in session. Allows coupon/gift card/loyalty
//  redemption via AJAX. Submits to payment_init.php.
// ═══════════════════════════════════════════════════════
session_start();
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require 'includes/solemate_ecommerce.php';
$settings = getSiteSettings($pdo);

// ── Populate cart from incoming booking POST ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id'])) {
    $svcId = (int)($_POST['service_id'] ?? 0);
    $svc   = $svcId > 0
        ? $pdo->prepare("SELECT id,service_name,price,service_key FROM dbo.services WHERE id=?")->execute([$svcId])
        : null;
    $svcRow = null;
    if ($svcId > 0) {
        $s = $pdo->prepare("SELECT id,service_name,price,service_key FROM dbo.services WHERE id=?");
        $s->execute([$svcId]); $svcRow = $s->fetch();
    }

    $cart = cartGet();
    $cart['item'] = [
        'service_id'   => $svcId,
        'service_name' => $svcRow['service_name'] ?? 'Shoe Service',
        'service_key'  => $svcRow['service_key']  ?? '',
        'price'        => (float)($svcRow['price'] ?? 0),
        'shoe_type'    => trim($_POST['shoe_type']  ?? ''),
        'drop_date'    => trim($_POST['drop_date']   ?? ''),
        'collection'   => trim($_POST['collection']  ?? 'drop-off'),
        'notes'        => trim($_POST['notes']       ?? ''),
        'pairs'        => max(1,(int)($_POST['pairs'] ?? 1)),
        'surname'      => trim($_POST['surname']     ?? ''),
        'customer_name'=> trim($_POST['customer_name']?? ''),
        'email'        => strtolower(trim($_POST['email'] ?? '')),
        'phone'        => trim($_POST['phone']       ?? ''),
        'customization_data' => $_POST['customization_data'] ?? null,
    ];
    // Only reset discounts when the booking itself changes (new service or date)
    $prevItem = $cart['item'] ?? [];
    $bookingChanged = ($prevItem['service_id'] ?? null) !== $svcId
                   || ($prevItem['drop_date']  ?? null) !== $cart['item']['drop_date']
                   || ($prevItem['collection'] ?? null) !== $cart['item']['collection'];
    if ($bookingChanged) {
        $cart['coupon_code']=$cart['coupon_discount']=null;
        $cart['gift_card_code']=$cart['gift_card_discount']=null;
        $cart['loyalty_points']=$cart['loyalty_discount']=0;
    }
    cartSet($cart);
}

$cart = cartGet();
if (!$cart['item']) {
    header('Location: solemate_homepage.php?page=booking');
    exit;
}

$item          = $cart['item'];
$subtotal      = (float)$item['price'];
$collectionFee = isset($item['collection_fee'])
    ? (float)$item['collection_fee']
    : match($item['collection']) { 'courier'=>80,'home'=>120, default=>0 };
$fullTotal     = $subtotal + $collectionFee;   // base total before discounts

// ── AJAX: validate coupon ────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'coupon') {
    header('Content-Type: application/json');
    if (!$settings['coupons']) { echo json_encode(['ok'=>false,'message'=>'Coupons are currently unavailable.']); exit; }
    $code   = trim($_POST['code'] ?? '');
    $result = validateCoupon($pdo, $code, $fullTotal);
    if ($result['ok']) {
        $cart['coupon_code']     = strtoupper($code);
        $cart['coupon_discount'] = $result['discount'];
        cartSet($cart);
        echo json_encode(['ok'=>true,'discount'=>$result['discount'],'message'=>$result['message'],'total'=>cartTotal($cart)]);
    } else {
        $cart['coupon_code']=$cart['coupon_discount']=null;
        cartSet($cart);
        echo json_encode(['ok'=>false,'message'=>$result['message']]);
    }
    exit;
}

// ── AJAX: validate gift card ─────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'giftcard') {
    header('Content-Type: application/json');
    if (!$settings['gift_cards']) { echo json_encode(['ok'=>false,'message'=>'Gift cards are currently unavailable.']); exit; }
    $code   = strtoupper(trim($_POST['code'] ?? ''));
    $result = validateGiftCard($pdo, $code);
    if ($result['ok']) {
        // cartTotal() now includes the collection fee — cap gift card against full order total
        $remaining = cartTotal($cart);
        $discount  = min($result['balance'], $remaining);
        $cart['gift_card_code']     = $code;
        $cart['gift_card_discount'] = $discount;
        cartSet($cart);
        echo json_encode(['ok'=>true,'balance'=>$result['balance'],'discount'=>$discount,'message'=>$result['message'],'total'=>cartTotal($cart)]);
    } else {
        $cart['gift_card_code']=$cart['gift_card_discount']=null;
        cartSet($cart);
        echo json_encode(['ok'=>false,'message'=>$result['message']]);
    }
    exit;
}

// ── AJAX: apply loyalty points ───────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'loyalty') {
    header('Content-Type: application/json');
    if (!$settings['loyalty']) { echo json_encode(['ok'=>false,'message'=>'Loyalty points are currently unavailable.']); exit; }
    if (!isCustomerLoggedIn()) { echo json_encode(['ok'=>false,'message'=>'Sign in to use loyalty points.']); exit; }
    $custRow = $pdo->prepare("SELECT loyalty_points FROM dbo.customers WHERE id=?");
    $custRow->execute([getCustomerId()]); $custRow=$custRow->fetch();
    $available = (int)($custRow['loyalty_points'] ?? 0);
    $redeem    = min((int)($_POST['points'] ?? 0), loyaltyMaxRedeem($available, $fullTotal));
    $discount  = loyaltyPointsValue($redeem);
    $cart['loyalty_points']  = $redeem;
    $cart['loyalty_discount'] = $discount;
    cartSet($cart);
    echo json_encode(['ok'=>true,'redeemed'=>$redeem,'discount'=>$discount,'total'=>cartTotal($cart),'message'=>"{$redeem} points = R".number_format($discount,2).' off']);
    exit;
}

// ── Remove discount ──────────────────────────────────────
if (isset($_GET['remove'])) {
    if ($_GET['remove']==='coupon')   { $cart['coupon_code']=$cart['coupon_discount']=null; }
    if ($_GET['remove']==='giftcard') { $cart['gift_card_code']=$cart['gift_card_discount']=null; }
    if ($_GET['remove']==='loyalty')  { $cart['loyalty_points']=$cart['loyalty_discount']=0; }
    cartSet($cart);
    header('Location: solemate_checkout.php'); exit;
}

$total        = cartTotal($cart);   // already includes collection fee
$totalWithFee = $total;

$custPoints = 0;
$custMaxRedeem = 0;
if (isCustomerLoggedIn()) {
    $cr = $pdo->prepare("SELECT loyalty_points FROM dbo.customers WHERE id=?");
    $cr->execute([getCustomerId()]); $crRow=$cr->fetch();
    $custPoints    = (int)($crRow['loyalty_points'] ?? 0);
    $custMaxRedeem = loyaltyMaxRedeem($custPoints, $fullTotal);
}

$pointsToEarn = loyaltyPointsEarned($totalWithFee);

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Checkout — SoleMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;--accent:#D4561A;--muted:#8B7355;--success:#2D6A4F}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--dark);min-height:100vh}
nav{background:var(--dark);padding:0 1.75rem;display:flex;align-items:center;justify-content:space-between;height:60px;box-shadow:0 2px 20px rgba(0,0,0,.3)}
.logo{font-family:'Playfair Display',serif;font-size:22px;color:var(--tan);text-decoration:none}.logo span{color:var(--accent)}
.nav-back{color:rgba(255,255,255,.45);font-size:13px;text-decoration:none}.nav-back:hover{color:var(--tan)}
.page{max-width:820px;margin:0 auto;padding:2rem 1.5rem 4rem;display:grid;grid-template-columns:1fr 340px;gap:1.75rem}
@media(max-width:700px){.page{grid-template-columns:1fr}}
h2{font-family:'Playfair Display',serif;font-size:1.4rem;margin-bottom:1.25rem}
.card{background:#fff;border-radius:14px;padding:1.5rem;border:1px solid rgba(107,66,38,.08);box-shadow:0 1px 4px rgba(0,0,0,.06)}
.fg{margin-bottom:.875rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.fg input,.fg select,.fg textarea{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;padding:10px 14px;font-size:14px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;transition:border-color .15s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
/* Summary card */
.summary-card{background:#fff;border-radius:14px;padding:1.5rem;border:1px solid rgba(107,66,38,.08);position:sticky;top:1rem}
.svc-name{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;margin-bottom:3px}
.svc-meta{font-size:12px;color:var(--muted);line-height:1.7}
.divider{border:none;border-top:1px solid rgba(107,66,38,.1);margin:1rem 0}
.price-row{display:flex;justify-content:space-between;font-size:14px;margin-bottom:.5rem}
.price-row.strike{text-decoration:line-through;color:var(--muted)}
.price-row.discount{color:var(--success)}
.price-row.total{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(107,66,38,.1)}
/* Discount inputs */
.discount-section{margin-bottom:1rem}
.discount-section h4{font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem}
.input-row{display:flex;gap:.5rem}
.input-row input{flex:1;border:1.5px solid rgba(107,66,38,.18);border-radius:8px;padding:8px 12px;font-size:13px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none}
.input-row input:focus{border-color:var(--accent)}
.apply-btn{background:var(--dark);color:var(--tan);border:none;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit;white-space:nowrap}
.apply-btn:hover{background:#2a1a10}
.remove-link{font-size:11px;color:var(--accent);text-decoration:none;margin-left:auto;display:block;text-align:right;margin-top:3px}.remove-link:hover{text-decoration:underline}
.applied-badge{background:#f0faf5;border:1px solid rgba(45,106,79,.25);border-radius:7px;padding:5px 10px;font-size:12px;color:#1a4731;display:flex;align-items:center;justify-content:space-between;margin-top:.4rem}
.feedback{font-size:11px;margin-top:.35rem}
.feedback.ok{color:var(--success)}.feedback.err{color:var(--accent)}
/* Loyalty slider */
.loyalty-wrap{background:rgba(250,247,242,.6);border:1px solid rgba(107,66,38,.12);border-radius:10px;padding:.875rem 1rem;margin-top:.25rem}
.loyalty-info{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:.5rem}
input[type=range]{width:100%;accent-color:var(--accent)}
/* Pay button */
.pay-btn{width:100%;background:var(--accent);color:#fff;border:none;padding:14px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;margin-top:1.25rem;transition:opacity .15s;display:flex;align-items:center;justify-content:center;gap:.5rem}
.pay-btn:hover{opacity:.9}
.pf-logo{font-size:11px;color:rgba(255,255,255,.7);margin-top:.5rem;text-align:center}
.earn-tip{background:rgba(200,169,126,.12);border-radius:8px;padding:.625rem .875rem;font-size:12px;color:var(--brown);margin-top:.75rem}
</style>
</head>
<body>
<nav>
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <a href="solemate_homepage.php?page=booking" class="nav-back">← Edit booking</a>
</nav>

<div class="page">

  <!-- Left: customer details + discounts -->
  <div>
    <h2>Confirm your booking</h2>
    <div class="card" style="margin-bottom:1.25rem">
      <div style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:1rem">Your details</div>
      <form id="checkoutForm" action="solemate_payment_init.php" method="POST">
        <div class="row-2">
          <div class="fg"><label>First name *</label><input type="text" name="first_name" required value="<?= e($item['customer_name']) ?>" placeholder="Siya"></div>
          <div class="fg"><label>Last name *</label><input type="text" name="last_name" required value="<?= e($item['surname']) ?>" placeholder="Khoza"></div>
        </div>
        <div class="fg"><label>Email *</label><input type="email" name="email" required value="<?= e($item['email']) ?>" placeholder="siya@example.com"></div>
        <div class="fg"><label>Phone</label><input type="tel" name="phone" value="<?= e($item['phone']) ?>" placeholder="082 123 4567"></div>
      </form>
    </div>

    <!-- Discount panel -->
    <div class="card">
      <div style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:1rem">Discounts &amp; rewards</div>

      <!-- Coupon -->
      <?php if ($settings['coupons']): ?>
      <div class="discount-section">
        <h4>Coupon code</h4>
        <?php if ($cart['coupon_code']): ?>
          <div class="applied-badge">
            <span>🏷️ <?= e($cart['coupon_code']) ?> — R<?= number_format($cart['coupon_discount'],2) ?> off</span>
            <a href="?remove=coupon" class="remove-link">Remove</a>
          </div>
        <?php else: ?>
          <div class="input-row">
            <input type="text" id="couponInput" placeholder="WELCOME10" maxlength="30">
            <button class="apply-btn" onclick="applyDiscount('coupon')">Apply</button>
          </div>
          <div class="feedback" id="couponFeedback"></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Gift Card -->
      <?php if ($settings['gift_cards']): ?>
      <div class="discount-section">
        <h4>Gift card</h4>
        <?php if ($cart['gift_card_code']): ?>
          <div class="applied-badge">
            <span>🎁 <?= e($cart['gift_card_code']) ?> — R<?= number_format($cart['gift_card_discount'],2) ?> off</span>
            <a href="?remove=giftcard" class="remove-link">Remove</a>
          </div>
        <?php else: ?>
          <div class="input-row">
            <input type="text" id="giftInput" placeholder="GC-XXXX-XXXX" maxlength="20">
            <button class="apply-btn" onclick="applyDiscount('giftcard')">Apply</button>
          </div>
          <div class="feedback" id="giftFeedback"></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Loyalty Points -->
      <?php if ($settings['loyalty'] && isCustomerLoggedIn() && $custPoints >= 100): ?>
      <div class="discount-section">
        <h4>Loyalty points</h4>
        <?php if ($cart['loyalty_points'] > 0): ?>
          <div class="applied-badge">
            <span>⭐ <?= number_format($cart['loyalty_points']) ?> pts — R<?= number_format($cart['loyalty_discount'],2) ?> off</span>
            <a href="?remove=loyalty" class="remove-link">Remove</a>
          </div>
        <?php else: ?>
          <div class="loyalty-wrap">
            <div class="loyalty-info">
              <span>Balance: <strong><?= number_format($custPoints) ?> pts</strong></span>
              <span>Max: <strong><?= number_format($custMaxRedeem) ?> pts</strong> = R<?= number_format(loyaltyPointsValue($custMaxRedeem),2) ?></span>
            </div>
            <input type="range" id="loyaltySlider" min="0" max="<?= $custMaxRedeem ?>" step="50" value="0"
                   oninput="document.getElementById('loyaltyVal').textContent=this.value">
            <div style="font-size:12px;color:var(--muted);display:flex;justify-content:space-between;margin-top:3px">
              <span>0 pts</span>
              <span id="loyaltyVal">0</span>
              <span><?= number_format($custMaxRedeem) ?> pts</span>
            </div>
            <button class="apply-btn" style="margin-top:.5rem;width:100%" onclick="applyLoyalty()">Redeem points</button>
            <div class="feedback" id="loyaltyFeedback"></div>
          </div>
        <?php endif; ?>
      </div>
      <?php elseif (!isCustomerLoggedIn()): ?>
      <div style="font-size:12px;color:var(--muted);background:rgba(107,66,38,.05);border-radius:8px;padding:.75rem;text-align:center">
        <a href="solemate_customer_login.php?redirect=<?= urlencode('/SoleMate/solemate_checkout.php') ?>" style="color:var(--accent);font-weight:500">Sign in</a> to earn &amp; redeem loyalty points.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: order summary + pay button -->
  <div>
    <div class="summary-card">
      <div style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:1rem">Order summary</div>

      <div class="svc-name"><?= e($item['service_name']) ?></div>
      <?php if (!empty($item['is_custom'])): ?>
        <?php
          $colors = $item['colors'] ?? [];
          if (!$colors && !empty($item['customization_data'])) {
              $dec = json_decode($item['customization_data'], true) ?? [];
              $colors = $dec['colors'] ?? [];
          }
        ?>
        <?php if (!empty($colors)): ?>
        <div style="display:flex;gap:5px;flex-wrap:wrap;margin:.5rem 0">
          <?php foreach (array_slice(array_values($colors),0,10) as $hex): ?>
          <span style="width:18px;height:18px;border-radius:50%;background:<?= e($hex) ?>;border:1.5px solid rgba(255,255,255,.15);display:inline-block;flex-shrink:0" title="<?= e($hex) ?>"></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="svc-meta">
          Size: <?= e($item['colors']['size'] ?? $item['shoe_size'] ?? '') ?><br>
          Material: <?= ucfirst(e($item['material'] ?? '')) ?><br>
          <?= $item['drop_date'] ? 'Production by: '.date('d M Y',strtotime($item['drop_date'])).'<br>' : '' ?>
          Collection: <?= ucfirst(e($item['collection'])) ?>
          <?php if (!empty($item['delivery_address'])): ?>
          <br><span style="font-size:11px;opacity:.7"><?= e($item['delivery_address']) ?></span>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="svc-meta">
          <?= $item['shoe_type'] ? e($item['shoe_type']).'<br>' : '' ?>
          <?= $item['drop_date'] ? 'Drop-off: '.date('d M Y',strtotime($item['drop_date'])).'<br>' : '' ?>
          Collection: <?= ucfirst(e($item['collection'])) ?>
        </div>
      <?php endif; ?>

      <hr class="divider">

      <?php if (!empty($item['is_custom']) && !empty($item['price_breakdown'])): ?>
        <?php foreach ($item['price_breakdown'] as $pbRow): ?>
        <div class="price-row">
          <span><?= e($pbRow['label']) ?></span>
          <span><?= ($pbRow['cost'] == 1200 ? '' : '+') ?>R<?= number_format((float)$pbRow['cost'],2) ?></span>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="price-row"><span>Service</span><span>R<?= number_format($subtotal,2) ?></span></div>
      <?php endif; ?>
      <?php if ($collectionFee > 0): ?>
      <div class="price-row"><span>Collection fee</span><span>R<?= number_format($collectionFee,2) ?></span></div>
      <?php endif; ?>

      <?php if ($cart['coupon_discount'] > 0): ?>
      <div class="price-row discount"><span>🏷️ Coupon (<?= e($cart['coupon_code']) ?>)</span><span>−R<?= number_format($cart['coupon_discount'],2) ?></span></div>
      <?php endif; ?>
      <?php if ($cart['gift_card_discount'] > 0): ?>
      <div class="price-row discount"><span>🎁 Gift card</span><span>−R<?= number_format($cart['gift_card_discount'],2) ?></span></div>
      <?php endif; ?>
      <?php if ($cart['loyalty_discount'] > 0): ?>
      <div class="price-row discount"><span>⭐ <?= number_format($cart['loyalty_points']) ?> pts</span><span>−R<?= number_format($cart['loyalty_discount'],2) ?></span></div>
      <?php endif; ?>

      <div class="price-row total" id="totalRow"><span>Total</span><span id="totalAmt">R<?= number_format($totalWithFee,2) ?></span></div>

      <?php if ($settings['loyalty'] && isCustomerLoggedIn()): ?>
      <div class="earn-tip">⭐ You'll earn <strong><?= $pointsToEarn ?> loyalty points</strong> on this order.</div>
      <?php endif; ?>

      <!-- Submit to payment_init -->
      <form action="solemate_payment_init.php" method="POST" id="payForm">
        <input type="hidden" name="first_name" id="hfn">
        <input type="hidden" name="last_name"  id="hln">
        <input type="hidden" name="email"       id="hem">
        <input type="hidden" name="phone"       id="hph">
      </form>

      <button class="pay-btn" onclick="submitPay()">
        🔒 Pay securely — R<span id="btnAmt"><?= number_format($totalWithFee,2) ?></span>
      </button>
      <div class="pf-logo">Secured by PayFast · SSL encrypted</div>

      <div style="font-size:11px;color:var(--muted);margin-top:.875rem;line-height:1.7;text-align:center">
        By paying you agree to our <a href="#" style="color:var(--accent)">terms</a>.<br>
        Booking confirmed after payment clears.
      </div>
    </div>
  </div>

</div>

<script>
const subtotal = <?= $subtotal ?>;
const fee      = <?= $collectionFee ?>;

function updateTotalDisplay(newTotal) {
  const t = parseFloat(newTotal).toFixed(2);
  document.getElementById('totalAmt').textContent = 'R' + parseFloat(t).toLocaleString('en-ZA',{minimumFractionDigits:2});
  document.getElementById('btnAmt').textContent   = parseFloat(t).toLocaleString('en-ZA',{minimumFractionDigits:2});
}

async function applyDiscount(type) {
  const codeEl  = document.getElementById(type==='coupon' ? 'couponInput' : 'giftInput');
  const feedEl  = document.getElementById(type==='coupon' ? 'couponFeedback' : 'giftFeedback');
  const code    = codeEl ? codeEl.value.trim() : '';

  feedEl.textContent = 'Checking…';
  feedEl.className   = 'feedback';

  const fd = new FormData();
  fd.append('code', code);
  const res  = await fetch(`solemate_checkout.php?ajax=${type}`, {method:'POST',body:fd});
  const data = await res.json();

  feedEl.textContent = data.message;
  feedEl.className   = 'feedback ' + (data.ok ? 'ok' : 'err');
  if (data.ok) { updateTotalDisplay(data.total); setTimeout(()=>window.location.href='solemate_checkout.php',800); }
}

async function applyLoyalty() {
  const pts    = parseInt(document.getElementById('loyaltySlider').value);
  const feedEl = document.getElementById('loyaltyFeedback');
  if (pts < 100) { feedEl.textContent='Minimum 100 points.'; feedEl.className='feedback err'; return; }

  const fd = new FormData(); fd.append('points', pts);
  const res  = await fetch('solemate_checkout.php?ajax=loyalty', {method:'POST',body:fd});
  const data = await res.json();

  feedEl.textContent = data.message;
  feedEl.className   = 'feedback ' + (data.ok ? 'ok' : 'err');
  if (data.ok) { updateTotalDisplay(data.total); setTimeout(()=>window.location.href='solemate_checkout.php',800); }
}

function submitPay() {
  const cf = document.getElementById('checkoutForm');
  const pf = document.getElementById('payForm');
  const fn = cf.querySelector('[name=first_name]').value.trim();
  const ln = cf.querySelector('[name=last_name]').value.trim();
  const em = cf.querySelector('[name=email]').value.trim();
  const ph = cf.querySelector('[name=phone]').value.trim();

  if (!fn || !ln || !em) { alert('Please fill in your name and email.'); return; }

  document.getElementById('hfn').value = fn;
  document.getElementById('hln').value = ln;
  document.getElementById('hem').value = em;
  document.getElementById('hph').value = ph;
  pf.submit();
}
</script>
</body>
</html>