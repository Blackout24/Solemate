<?php
// ═══════════════════════════════════════════════════════
//  solemate_gift_card.php  —  Buy a Gift Card
// ═══════════════════════════════════════════════════════
session_start();
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require 'includes/solemate_ecommerce.php';
require 'includes/solemate_payfast.php';

$errors  = [];
$success = false;
$settings = getSiteSettings($pdo);

$amounts = [200, 300, 500, 750, 1000, 1500];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$settings['gift_cards']) {
    $errors[] = 'Gift cards are currently unavailable.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount    = (float)($_POST['amount']         ?? 0);
    $custom    = (float)($_POST['custom_amount']  ?? 0);
    $finalAmt  = $amount > 0 ? $amount : $custom;

    $recipName  = trim($_POST['recipient_name']  ?? '');
    $recipEmail = strtolower(trim($_POST['recipient_email'] ?? ''));
    $message    = trim($_POST['message'] ?? '');
    $senderName = trim($_POST['sender_name'] ?? (isCustomerLoggedIn() ? getCustomerName() : ''));
    $senderEmail= strtolower(trim($_POST['sender_email'] ?? (isCustomerLoggedIn() ? getCustomerEmail() : '')));

    if ($finalAmt < 100)    $errors[] = 'Minimum gift card value is R100.';
    if ($finalAmt > 5000)   $errors[] = 'Maximum gift card value is R5,000.';
    if (!$recipName)         $errors[] = 'Recipient name is required.';
    if (!$recipEmail || !filter_var($recipEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Valid recipient email is required.';
    if (!$senderName)        $errors[] = 'Your name is required.';
    if (!$senderEmail || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Your email is required.';

    if (empty($errors)) {
        $code    = generateGiftCardCode($pdo);
        $cartRef = generateCartRef($pdo);

        // Insert gift card (inactive until payment confirmed)
        $pdo->prepare("
            INSERT INTO dbo.gift_cards
                (code, initial_balance, balance, purchased_by, recipient_email, recipient_name, message, cart_ref, is_active, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ")->execute([
            $code, $finalAmt, $finalAmt,
            $senderName, $recipEmail, $recipName, $message,
            $cartRef,
            date('Y-m-d', strtotime('+1 year')),
        ]);

        // Insert payment record
        $pdo->prepare("
            INSERT INTO dbo.payments (cart_ref, amount_gross, status, customer_email)
            VALUES (?, ?, N'pending', ?)
        ")->execute([$cartRef, $finalAmt, $senderEmail]);

        // Store in session for return page
        $_SESSION['sm_cart_ref']   = $cartRef;
        $_SESSION['sm_order_ref']  = $code;
        $_SESSION['sm_gift_card']  = true;

        // Build PayFast form and auto-redirect
        $pfData = pfBuildPaymentData([
            'first_name'       => explode(' ', $senderName)[0],
            'last_name'        => implode(' ', array_slice(explode(' ', $senderName), 1)) ?: '-',
            'email'            => $senderEmail,
            'cart_ref'         => $cartRef,
            'amount'           => $finalAmt,
            'item_name'        => 'SoleMate Gift Card for ' . $recipName,
            'item_description' => "R{$finalAmt} SoleMate Gift Card — code will be emailed to {$recipEmail}",
        ]);

        $pfCfg = pfConfig();
        ?>
        <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting…</title>
        <style>body{font-family:'DM Sans',system-ui;background:#1A0F08;color:#FAF7F2;min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1rem}
        .spin{width:40px;height:40px;border:3px solid rgba(200,169,126,.2);border-top-color:#C8A97E;border-radius:50%;animation:s .7s linear infinite}
        @keyframes s{to{transform:rotate(360deg)}}p{font-size:13px;color:rgba(200,169,126,.6)}</style></head>
        <body><div class="spin"></div><p>Redirecting to PayFast…</p>
        <form id="pf" action="<?= htmlspecialchars($pfCfg['url'],ENT_QUOTES) ?>" method="POST">
        <?php foreach ($pfData as $k=>$v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>"><?php endforeach; ?>
        </form><script>setTimeout(()=>document.getElementById('pf').submit(),400)</script></body></html>
        <?php
        exit;
    }
}

function e(string $v): string { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
$custName  = isCustomerLoggedIn() ? getCustomerName()  : '';
$custEmail = isCustomerLoggedIn() ? getCustomerEmail() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gift Cards — SoleMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;--accent:#D4561A;--muted:#8B7355}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--dark);min-height:100vh}
nav{background:var(--dark);padding:0 1.75rem;display:flex;align-items:center;justify-content:space-between;height:60px;box-shadow:0 2px 20px rgba(0,0,0,.3)}
.logo{font-family:'Playfair Display',serif;font-size:22px;color:var(--tan);text-decoration:none}.logo span{color:var(--accent)}
.nav-back{color:rgba(255,255,255,.45);font-size:13px;text-decoration:none}.nav-back:hover{color:var(--tan)}
.page{max-width:820px;margin:0 auto;padding:2.5rem 1.5rem 4rem;display:grid;grid-template-columns:1fr 340px;gap:1.75rem}
@media(max-width:680px){.page{grid-template-columns:1fr}}
/* Gift card preview */
.gc-preview{background:linear-gradient(135deg,var(--dark) 0%,#3d1f0e 100%);border-radius:18px;padding:2rem;aspect-ratio:1.586/1;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 12px 40px rgba(0,0,0,.25);margin-bottom:1.25rem}
.gc-logo{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--tan)}.gc-logo span{color:var(--accent)}
.gc-tagline{font-size:11px;color:rgba(200,169,126,.5);margin-top:3px;letter-spacing:.5px}
.gc-amount{font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:#fff;text-align:right}
.gc-code{font-family:monospace;font-size:13px;color:rgba(200,169,126,.6);letter-spacing:2px}
.gc-validity{font-size:10px;color:rgba(200,169,126,.4);text-align:right}
/* Form card */
.card{background:#fff;border-radius:14px;padding:1.5rem;border:1px solid rgba(107,66,38,.08);box-shadow:0 1px 4px rgba(0,0,0,.06)}
h2{font-family:'Playfair Display',serif;font-size:1.3rem;margin-bottom:1.25rem}
/* Amount pills */
.amount-pills{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem}
.pill{border:1.5px solid rgba(107,66,38,.2);border-radius:8px;padding:8px 18px;font-size:14px;font-weight:500;cursor:pointer;background:none;color:var(--dark);font-family:inherit;transition:all .15s}
.pill:hover,.pill.active{background:var(--dark);color:var(--tan);border-color:var(--dark)}
/* FG */
.fg{margin-bottom:.875rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.fg input,.fg textarea{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;padding:10px 14px;font-size:14px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;transition:border-color .15s;resize:vertical}
.fg input:focus,.fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.fg input::placeholder,.fg textarea::placeholder{color:rgba(107,66,38,.35)}
.section-title{font-family:'Playfair Display',serif;font-size:.95rem;font-weight:700;margin:1.25rem 0 .75rem;padding-bottom:.5rem;border-bottom:1px solid rgba(107,66,38,.1)}
.err-box{background:#fff8f6;border:1.5px solid rgba(212,86,26,.3);border-radius:9px;padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#8B2200}
.err-box ul{margin:.25rem 0 0 1rem;line-height:1.9}
.submit-btn{width:100%;background:var(--accent);color:#fff;border:none;padding:13px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;transition:opacity .15s;margin-top:.75rem}
.submit-btn:hover{opacity:.88}
</style>
</head>
<body>
<nav>
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <a href="solemate_homepage.php" class="nav-back">← Back</a>
</nav>

<div class="page">
  <!-- Left: preview + info -->
  <div>
    <h2>Give the gift of fresh kicks 🎁</h2>
    <div class="gc-preview" id="gcPreview">
      <div>
        <div class="gc-logo">Sole<span>Mate</span></div>
        <div class="gc-tagline">SHOE CLEANING &amp; REPAIR · DURBAN</div>
      </div>
      <div>
        <div class="gc-amount" id="previewAmt">R 0.00</div>
        <div class="gc-code">GC-XXXX-XXXX</div>
        <div class="gc-validity">Valid 1 year from purchase</div>
      </div>
    </div>
    <div style="font-size:13px;color:var(--muted);line-height:1.8">
      ✅ Redeemable on any SoleMate service<br>
      ✅ Code emailed instantly after payment<br>
      ✅ Valid for 12 months<br>
      ✅ Balance never expires mid-job
    </div>
  </div>

  <!-- Right: form -->
  <div>
    <div class="card">
      <?php if (!empty($errors)): ?>
      <div class="err-box">
        <?php if (count($errors)===1): echo e($errors[0]); else: ?>
        <ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!$settings['gift_cards']): ?>
      <p style="font-size:13px;color:var(--muted)">Gift cards aren't available to purchase right now — please check back later.</p>
      <?php else: ?>
      <form method="POST">
        <div style="font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;margin-bottom:1rem">Choose an amount</div>
        <div class="amount-pills">
          <?php foreach ($amounts as $a): ?>
          <button type="button" class="pill" data-amt="<?= $a ?>" onclick="selectAmt(this)">R<?= $a ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="amount" id="selectedAmt" value="">
        <div class="fg">
          <label>Custom amount (R100 – R5,000)</label>
          <input type="number" name="custom_amount" id="customAmt" min="100" max="5000" step="50"
                 placeholder="e.g. 450" oninput="setCustomAmt(this.value)"
                 value="<?= e($_POST['custom_amount']??'') ?>">
        </div>

        <div class="section-title">Recipient details</div>
        <div class="fg"><label>Recipient name *</label>
          <input type="text" name="recipient_name" placeholder="Who's receiving this?" required maxlength="100" value="<?= e($_POST['recipient_name']??'') ?>"></div>
        <div class="fg"><label>Recipient email *</label>
          <input type="email" name="recipient_email" placeholder="their@email.com" required maxlength="100" value="<?= e($_POST['recipient_email']??'') ?>"></div>
        <div class="fg"><label>Personal message <span style="color:rgba(107,66,38,.4);font-weight:400">(optional)</span></label>
          <textarea name="message" rows="2" placeholder="Happy birthday! Enjoy fresh kicks 👟" maxlength="300"><?= e($_POST['message']??'') ?></textarea></div>

        <div class="section-title">Your details</div>
        <div class="fg"><label>Your name *</label>
          <input type="text" name="sender_name" required maxlength="100" value="<?= e($_POST['sender_name']??$custName) ?>"></div>
        <div class="fg"><label>Your email *</label>
          <input type="email" name="sender_email" required maxlength="100" value="<?= e($_POST['sender_email']??$custEmail) ?>"></div>

        <button type="submit" class="submit-btn">Purchase gift card — R<span id="btnAmt">0</span></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function selectAmt(btn) {
  document.querySelectorAll('.pill').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('selectedAmt').value = btn.dataset.amt;
  document.getElementById('customAmt').value    = '';
  updatePreview(parseFloat(btn.dataset.amt));
}
function setCustomAmt(val) {
  document.querySelectorAll('.pill').forEach(p=>p.classList.remove('active'));
  document.getElementById('selectedAmt').value = '';
  updatePreview(parseFloat(val) || 0);
}
function updatePreview(amt) {
  document.getElementById('previewAmt').textContent = 'R ' + (amt || 0).toLocaleString('en-ZA',{minimumFractionDigits:2});
  document.getElementById('btnAmt').textContent     = (amt || 0).toLocaleString('en-ZA',{minimumFractionDigits:2});
}
// Restore state on page reload (after validation error)
const prev = <?= json_encode((float)($_POST['amount']??$_POST['custom_amount']??0)) ?>;
if (prev > 0) updatePreview(prev);
</script>
</body>
</html>