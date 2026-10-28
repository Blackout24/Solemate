<?php
// ═══════════════════════════════════════════════════════
//  solemate_payment_return.php  —  PayFast Return Page
//
//  Fixes vs previous version:
//    - cart_ref now in URL (session no longer required)
//    - PHP-side instant check: if already paid, render
//      the success page immediately — zero JS polling
//    - If not yet paid: first poll at 500ms, then every
//      1 s (was 2 s start + 2 s interval = up to 40 s)
//    - <meta refresh> every 5 s as hard fallback
//    - Progress bar shows elapsed time
//    - Stops gracefully after 30 s and shows contact copy
// ═══════════════════════════════════════════════════════
session_start();
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require 'includes/solemate_ecommerce.php';

// cart_ref comes from URL (preferred) or session fallback
$cartRef = trim($_GET['cart_ref'] ?? $_SESSION['sm_cart_ref'] ?? '');

$isPaid     = false;
$booking    = null;
$gcRow      = null;
$isGiftCard = false;
$careOrder  = null;
$careItems  = [];

if ($cartRef) {
    // Try booking first (regular service payment)
    $stmt = $pdo->prepare("
        SELECT b.order_ref, b.customer_name, b.payment_status, b.status,
               s.service_name, b.shoe_type, b.drop_date, b.collection,
               b.amount_paid, p.coupon_discount, p.gift_card_discount
        FROM dbo.booking b
        LEFT JOIN dbo.services s ON b.service_id = s.id
        LEFT JOIN dbo.payments p ON b.cart_ref    = p.cart_ref
        WHERE b.cart_ref = ?
    ");
    $stmt->execute([$cartRef]);
    $booking = $stmt->fetch();
    $isPaid  = $booking && $booking['payment_status'] === 'paid';

    // No booking found → could be a gift card purchase
    if (!$booking) {
        $gcStmt = $pdo->prepare("
            SELECT gc.*, p.status AS pay_status, p.amount_gross
            FROM dbo.gift_cards gc
            LEFT JOIN dbo.payments p ON gc.cart_ref = p.cart_ref
            WHERE gc.cart_ref = ?
        ");
        $gcStmt->execute([$cartRef]);
        $gcRow = $gcStmt->fetch();
        if ($gcRow) {
            $isGiftCard = true;
            $isPaid     = $gcRow['pay_status'] === 'complete' && (int)$gcRow['is_active'] === 1;
        }
    }
}

// No booking / gift card → could be a care-product order
if ($cartRef && !$booking && !$gcRow) {
    try {
        $cst = $pdo->prepare("SELECT * FROM dbo.care_orders WHERE cart_ref = ?");
        $cst->execute([$cartRef]);
        $careOrder = $cst->fetch() ?: null;
        if ($careOrder) {
            $isPaid = $careOrder['payment_status'] === 'paid';
            if ($isPaid) {
                $cit = $pdo->prepare("SELECT name, qty, line_total FROM dbo.care_order_items WHERE order_id=? ORDER BY id");
                $cit->execute([(int)$careOrder['id']]);
                $careItems = $cit->fetchAll();
            }
        }
    } catch (\Throwable $e) { $careOrder = null; }
}

$loyaltyBalance = 0;
if (isCustomerLoggedIn()) {
    $lr = $pdo->prepare("SELECT loyalty_points FROM dbo.customers WHERE id=?");
    $lr->execute([getCustomerId()]);
    $loyaltyBalance = (int)(($lr->fetch())['loyalty_points'] ?? 0);
}

if ($isPaid) {
    cartClear();
    if ($careOrder) unset($_SESSION['care_cart'], $_SESSION['sm_care_order_ref']);
    unset($_SESSION['sm_cart_ref'], $_SESSION['sm_order_ref']);
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$safeRef = urlencode($cartRef);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if (!$isPaid && $cartRef): ?>
<meta http-equiv="refresh" content="5;url=solemate_payment_return.php?cart_ref=<?= $safeRef ?>">
<?php endif; ?>
<title><?= $isPaid ? 'Payment confirmed' : 'Confirming payment' ?> — SoleMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;--accent:#D4561A;--muted:#8B7355;--success:#2D6A4F}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--dark);
  min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
  padding:2.5rem 1.5rem 4rem}
.logo-nav{width:100%;max-width:540px;margin-bottom:2.5rem}
.logo{font-family:'Playfair Display',serif;font-size:22px;color:var(--brown);text-decoration:none}
.logo span{color:var(--accent)}
.card{background:#fff;border-radius:18px;padding:2.5rem 2.25rem;width:100%;max-width:520px;
  box-shadow:0 1px 4px rgba(0,0,0,.07),0 8px 32px rgba(0,0,0,.07);border:1px solid rgba(107,66,38,.08)}
.icon-circle{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-size:32px;margin:0 auto 1.25rem}
.icon-ok{background:#d4edda}
.icon-wait{background:#fff3cd}
h1{font-family:'Playfair Display',serif;font-size:1.5rem;text-align:center;margin-bottom:.5rem}
.sub{font-size:14px;color:var(--muted);text-align:center;line-height:1.7;margin-bottom:1.5rem}
hr{border:none;border-top:1px solid rgba(107,66,38,.1);margin:1.25rem 0}
dl{display:grid;grid-template-columns:auto 1fr;gap:.4rem 1.25rem;font-size:13px}
dt{color:var(--muted);font-weight:500}dd{font-weight:500}
.btn-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.75rem;justify-content:center}
.btn-p{background:var(--dark);color:var(--tan);border:none;padding:11px 24px;border-radius:9px;
  font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;
  display:inline-block;transition:all .15s}
.btn-p:hover{background:#2a1a10;color:#e5c98e}
.btn-g{background:none;border:1.5px solid rgba(107,66,38,.22);color:var(--muted);padding:10px 22px;
  border-radius:9px;font-size:13px;text-decoration:none;display:inline-block;transition:all .15s}
.btn-g:hover{border-color:var(--accent);color:var(--accent)}
.loyalty-pill{display:inline-flex;align-items:center;gap:.5rem;background:rgba(200,169,126,.15);
  border:1px solid rgba(200,169,126,.3);border-radius:20px;padding:.5rem 1rem;
  font-size:13px;color:var(--brown);margin:1rem auto 0;width:fit-content;display:flex}

/* ── Waiting state ── */
.progress-track{height:4px;background:rgba(107,66,38,.12);border-radius:2px;margin:1rem 0;overflow:hidden}
.progress-fill{height:100%;width:0;background:var(--accent);border-radius:2px;
  transition:width .8s linear}
.status-msg{font-size:13px;color:var(--muted);text-align:center;min-height:20px}
.dots span{display:inline-block;animation:dot 1.2s infinite both}
.dots span:nth-child(2){animation-delay:.2s}
.dots span:nth-child(3){animation-delay:.4s}
@keyframes dot{0%,80%,100%{opacity:.2}40%{opacity:1}}
.contact-note{font-size:12px;color:var(--muted);text-align:center;margin-top:1.25rem;
  padding:10px 14px;background:rgba(107,66,38,.04);border-radius:8px;line-height:1.7;display:none}
.contact-note a{color:var(--accent);text-decoration:none}
.contact-note a:hover{text-decoration:underline}
.success-anim{animation:popIn .4s cubic-bezier(.34,1.56,.64,1) both}
@keyframes popIn{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}
</style>
</head>
<body>

<div class="logo-nav">
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
</div>

<div class="card">

<?php if ($isPaid && $isGiftCard && $gcRow): ?>
  <!-- ── GIFT CARD SUCCESS ─────────────────────────── -->
  <div class="icon-circle icon-ok success-anim">🎁</div>
  <h1>Gift card purchased!</h1>
  <p class="sub">
    Thank you, <?= e($gcRow['purchased_by'] ?? 'there') ?>!<br>
    The gift card code has been emailed to <strong><?= e($gcRow['recipient_email'] ?? '') ?></strong>.
  </p>
  <hr>
  <dl>
    <dt>Gift card code</dt> <dd style="font-family:monospace;letter-spacing:1px"><?= e($gcRow['code']) ?></dd>
    <dt>Value</dt>          <dd style="color:var(--success)">R<?= number_format((float)($gcRow['initial_balance'] ?? $gcRow['balance']), 2) ?></dd>
    <dt>Recipient</dt>      <dd><?= e($gcRow['recipient_name'] ?? '') ?></dd>
    <dt>Valid until</dt>    <dd><?= $gcRow['expires_at'] ? e(date('d M Y', strtotime($gcRow['expires_at']))) : '12 months' ?></dd>
  </dl>
  <?php if ($loyaltyBalance > 0): ?>
  <div class="loyalty-pill">⭐ You now have <strong><?= number_format($loyaltyBalance) ?> loyalty points</strong></div>
  <?php endif; ?>
  <div class="btn-row">
    <a href="solemate_homepage.php?page=giftcard" class="btn-p">Buy another →</a>
    <a href="solemate_homepage.php" class="btn-g">Back to home</a>
  </div>

<?php elseif ($isPaid && $careOrder): ?>
  <!-- ── CARE ORDER SUCCESS ────────────────────────── -->
  <div class="icon-circle icon-ok success-anim">🧴</div>
  <h1>Order confirmed!</h1>
  <p class="sub">
    Thank you, <?= e(explode(' ', $careOrder['customer_name'])[0]) ?>!<br>
    A confirmation has been emailed to <strong><?= e($careOrder['email']) ?></strong>.
  </p>
  <hr>
  <dl>
    <dt>Order ref</dt> <dd><?= e($careOrder['order_ref']) ?></dd>
    <?php foreach ($careItems as $ci): ?>
    <dt><?= e($ci['name']) ?></dt><dd>× <?= (int)$ci['qty'] ?> · R<?= number_format((float)$ci['line_total'], 2) ?></dd>
    <?php endforeach; ?>
    <dt><?= $careOrder['delivery_method'] === 'delivery' ? 'Delivery' : 'Collection' ?></dt>
    <dd><?= $careOrder['delivery_method'] === 'delivery' ? e($careOrder['delivery_address'] ?? '') : 'In store — 123 Florida Road, Morningside' ?></dd>
    <dt>Amount paid</dt><dd style="color:var(--success)">R<?= number_format((float)$careOrder['total'], 2) ?></dd>
  </dl>
  <?php if ($loyaltyBalance > 0): ?>
  <div class="loyalty-pill">⭐ You now have <strong><?= number_format($loyaltyBalance) ?> loyalty points</strong></div>
  <?php endif; ?>
  <div class="btn-row">
    <a href="solemate_homepage.php?page=tracker&amp;oid=<?= urlencode($careOrder['order_ref']) ?>" class="btn-p">Track this order →</a>
    <a href="solemate_homepage.php?page=care" class="btn-g">Keep shopping</a>
  </div>

<?php elseif ($isPaid && $booking): ?>
  <!-- ── BOOKING SUCCESS ───────────────────────────── -->
  <div class="icon-circle icon-ok success-anim">✅</div>
  <h1>Payment confirmed!</h1>
  <p class="sub">
    Thank you, <?= e(explode(' ', $booking['customer_name'])[0]) ?>!<br>
    Your booking is confirmed — we'll be in touch shortly.
  </p>
  <hr>
  <dl>
    <dt>Order ref</dt>  <dd><?= e($booking['order_ref']) ?></dd>
    <dt>Service</dt>    <dd><?= e($booking['service_name'] ?? 'Shoe Service') ?></dd>
    <?php if (!empty($booking['shoe_type'])): ?><dt>Shoes</dt><dd><?= e($booking['shoe_type']) ?></dd><?php endif; ?>
    <?php if (!empty($booking['drop_date'])): ?><dt>Drop-off</dt><dd><?= e(date('d M Y', strtotime($booking['drop_date']))) ?></dd><?php endif; ?>
    <?php if (!empty($booking['collection'])): ?><dt>Collection</dt><dd><?= ucfirst(e($booking['collection'])) ?></dd><?php endif; ?>
    <dt>Amount paid</dt><dd style="color:var(--success)">R<?= number_format((float)$booking['amount_paid'], 2) ?></dd>
  </dl>

  <?php if ((float)($booking['coupon_discount'] ?? 0) > 0): ?>
  <div style="font-size:12px;color:var(--success);margin-top:.875rem">
    🏷️ Coupon saved you R<?= number_format($booking['coupon_discount'], 2) ?>
  </div>
  <?php endif; ?>
  <?php if ((float)($booking['gift_card_discount'] ?? 0) > 0): ?>
  <div style="font-size:12px;color:var(--success);margin-top:.25rem">
    🎁 Gift card saved you R<?= number_format($booking['gift_card_discount'], 2) ?>
  </div>
  <?php endif; ?>
  <?php if ($loyaltyBalance > 0): ?>
  <div class="loyalty-pill">⭐ You now have <strong><?= number_format($loyaltyBalance) ?> loyalty points</strong></div>
  <?php endif; ?>

  <div class="btn-row">
    <?php if (isCustomerLoggedIn()): ?>
    <a href="solemate_customer_dashboard.php?tab=bookings" class="btn-p">View my orders →</a>
    <?php endif; ?>
    <a href="solemate_homepage.php" class="btn-g">Back to home</a>
  </div>

<?php elseif ($cartRef): ?>
  <!-- ── WAITING ───────────────────────────────────── -->
  <div class="icon-circle icon-wait">🔒</div>
  <h1>Confirming your payment<span class="dots"><span>.</span><span>.</span><span>.</span></span></h1>
  <p class="sub">PayFast is confirming your payment.<br>This usually takes under 10 seconds.</p>

  <div class="progress-track">
    <div class="progress-fill" id="bar"></div>
  </div>
  <div class="status-msg" id="msg">Connecting to PayFast…</div>

  <div class="contact-note" id="contactNote">
    Taking longer than expected? <strong>Your payment may still have gone through.</strong><br>
    Check your email for a confirmation, or
    <a href="solemate_homepage.php">return to the homepage</a> and contact us with your reference:
    <strong><?= e($cartRef) ?></strong>
  </div>

<?php else: ?>
  <!-- ── NO REF ─────────────────────────────────────── -->
  <div class="icon-circle icon-wait">🕐</div>
  <h1>Session expired</h1>
  <p class="sub">We couldn't find your payment reference. If you completed a payment, check your email for a confirmation or contact us.</p>
  <div class="btn-row"><a href="solemate_homepage.php" class="btn-p">Back to home</a></div>

<?php endif; ?>
</div>

<?php if (!$isPaid && $cartRef): ?>
<script>
(function () {
  const CART_REF    = <?= json_encode($cartRef) ?>;
  const MAX_SECS    = 30;        // give up after 30 s
  const POLL_FAST   = 1000;      // 1 s between polls
  const FIRST_POLL  = 500;       // first check after 0.5 s
  const BAR_TICK    = 200;       // progress bar update interval

  const bar      = document.getElementById('bar');
  const msg      = document.getElementById('msg');
  const note     = document.getElementById('contactNote');
  const start    = Date.now();
  let   attempts = 0;
  let   stopped  = false;

  // Progress bar ticks independently of polling
  const barTimer = setInterval(() => {
    const pct = Math.min(((Date.now() - start) / (MAX_SECS * 1000)) * 100, 97);
    bar.style.width = pct + '%';
  }, BAR_TICK);

  const messages = [
    'Connecting to PayFast…',
    'Waiting for confirmation…',
    'Almost there…',
    'Still waiting — this can take a moment…',
  ];

  async function poll() {
    if (stopped) return;
    attempts++;
    msg.textContent = messages[Math.min(attempts - 1, messages.length - 1)];

    try {
      const res  = await fetch('solemate_payment_status.php?cart_ref=' + encodeURIComponent(CART_REF));
      const data = await res.json();

      if (data.paid) {
        clearInterval(barTimer);
        bar.style.transition = 'width .3s linear';
        bar.style.width      = '100%';
        // Small delay so the bar completes visually
        setTimeout(() => {
          window.location.href = 'solemate_payment_return.php?cart_ref=' + encodeURIComponent(CART_REF);
        }, 300);
        return;
      }
    } catch (err) {
      // Network hiccup — keep trying
    }

    const elapsed = Date.now() - start;
    if (elapsed >= MAX_SECS * 1000) {
      stopped = true;
      clearInterval(barTimer);
      bar.style.width      = '100%';
      bar.style.background = 'var(--muted)';
      msg.textContent      = 'Payment is still being processed — see note below.';
      note.style.display   = 'block';
      return;
    }

    setTimeout(poll, POLL_FAST);
  }

  // First poll fires quickly
  setTimeout(poll, FIRST_POLL);
})();
</script>
<?php endif; ?>
</body>
</html>