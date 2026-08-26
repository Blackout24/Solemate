<?php
// ═══════════════════════════════════════════════════════
//  solemate_reset_password.php  —  Password Reset
//  Reached via the link in the password reset email.
// ═══════════════════════════════════════════════════════
session_start();

if (!empty($_SESSION['customer_logged_in'])) {
    header('Location: solemate_customer_dashboard.php');
    exit;
}

require 'includes/solemate_database_connection.php';

$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenRow = null;
$errors   = [];
$done     = false;

// ── Validate token ──────────────────────────────────────
if ($token) {
    $stmt = $pdo->prepare("
        SELECT id, email, expires_at, used
        FROM dbo.password_resets
        WHERE token = ?
    ");
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) {
        $errors[] = 'This reset link is invalid. Please request a new one.';
        $tokenRow = null;
    } elseif ($tokenRow['used']) {
        $errors[] = 'This reset link has already been used. Please request a new one.';
        $tokenRow = null;
    } elseif (strtotime($tokenRow['expires_at']) < time()) {
        $errors[] = 'This reset link has expired. Please request a new one.';
        $tokenRow = null;
    }
} else {
    $errors[] = 'No reset token provided. Please use the link from your email.';
}

// ── Handle new password submission ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRow && empty($errors)) {
    $newPw  = $_POST['password']         ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$newPw)
        $errors[] = 'Please enter a new password.';
    elseif (strlen($newPw) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    elseif (strlen($newPw) > 72)
        $errors[] = 'Password is too long (max 72 characters).';
    elseif ($newPw !== $confirm)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($newPw, PASSWORD_BCRYPT);

        // Update the customer's password
        $pdo->prepare("UPDATE dbo.customers SET password_hash = ? WHERE email = ?")
            ->execute([$hash, $tokenRow['email']]);

        // Mark token as used
        $pdo->prepare("UPDATE dbo.password_resets SET used = 1 WHERE id = ?")
            ->execute([$tokenRow['id']]);

        $done = true;
    }
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — SoleMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<link rel="icon"          href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;
  --accent:#D4561A;--light:#E8D5BE;--muted:#8B7355;--success:#2D6A4F;
}
body{
  font-family:'DM Sans',system-ui,sans-serif;background:var(--dark);color:var(--dark);
  min-height:100vh;display:flex;align-items:flex-start;justify-content:center;
  padding:2rem 1.5rem;position:relative;overflow-x:hidden;overflow-y:auto;
}
body::before{content:'';position:fixed;inset:0;
  background:
    radial-gradient(ellipse 60% 50% at 20% 50%,rgba(212,86,26,.07) 0%,transparent 60%),
    radial-gradient(ellipse 50% 60% at 80% 30%,rgba(200,169,126,.06) 0%,transparent 60%);
  pointer-events:none}
body::after{content:'';position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(200,169,126,.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(200,169,126,.03) 1px,transparent 1px);
  background-size:40px 40px;pointer-events:none}
.card{
  background:rgba(250,247,242,.97);border-radius:18px;padding:2.5rem 2.25rem 2rem;
  width:100%;max-width:400px;margin:auto;
  box-shadow:0 0 0 1px rgba(200,169,126,.15),0 25px 60px rgba(0,0,0,.5),0 8px 20px rgba(0,0,0,.3);
  position:relative;z-index:1;animation:slideUp .4s cubic-bezier(.16,1,.3,1) both;
}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.card-accent{position:absolute;top:0;right:0;width:80px;height:80px;
  background:linear-gradient(225deg,rgba(212,86,26,.08) 0%,transparent 60%);
  border-radius:0 18px 0 0;pointer-events:none}
.logo{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--brown);
  text-align:center;display:block;margin-bottom:4px}
.logo span{color:var(--accent)}
.tagline{font-size:12px;color:var(--muted);text-align:center;margin-bottom:2rem;
  display:block;letter-spacing:.3px}
.card-icon{width:44px;height:44px;background:var(--dark);border-radius:12px;
  display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;
  font-size:20px;box-shadow:0 4px 12px rgba(26,15,8,.3)}
.card-title{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:700;
  margin-bottom:4px;text-align:center}
.card-sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:1.5rem;line-height:1.6}

.fg{margin-bottom:1rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);
  text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.pw-wrap{position:relative}
.pw-wrap input{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;
  padding:11px 44px 11px 14px;font-size:14px;font-family:inherit;background:var(--cream);
  color:var(--dark);outline:none;transition:border-color .18s,box-shadow .18s}
.pw-wrap input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.pw-wrap input::placeholder{color:rgba(107,66,38,.35)}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;padding:4px}
.pw-toggle:hover{color:var(--accent)}
.strength-bar{height:3px;border-radius:2px;background:rgba(107,66,38,.1);margin-top:6px;overflow:hidden}
.strength-fill{height:100%;width:0;transition:width .3s,background .3s;border-radius:2px}
.pw-rules{font-size:11px;color:rgba(107,66,38,.5);margin-top:4px;line-height:1.6}

.submit-btn{width:100%;background:var(--dark);color:var(--tan);border:none;
  padding:13px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;
  font-family:inherit;transition:all .18s;position:relative;overflow:hidden}
.submit-btn:hover{background:#2a1a10;color:#e5c98e}
.submit-btn:active{transform:scale(.99)}

.error-box{background:#fff8f6;border:1.5px solid rgba(212,86,26,.3);border-radius:9px;
  padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#8B2200;
  display:flex;align-items:flex-start;gap:8px}
.error-box ul{margin:.25rem 0 0 1rem;line-height:1.9}
.success-box{background:#f0faf5;border:1.5px solid rgba(45,106,79,.3);border-radius:9px;
  padding:16px 18px;font-size:13px;color:#1a4731;line-height:1.7}
.success-box strong{display:block;font-size:15px;margin-bottom:6px}
.back-link{display:block;text-align:center;margin-top:1.25rem;font-size:12px;
  color:var(--muted);text-decoration:none}
.back-link:hover{color:var(--accent)}

.sign-in-btn{display:block;width:100%;background:var(--accent);color:#fff;border:none;
  padding:13px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;
  font-family:inherit;text-decoration:none;text-align:center;margin-top:.75rem;
  transition:opacity .15s}
.sign-in-btn:hover{opacity:.88}
</style>
</head>
<body>

<div class="card">
  <div class="card-accent"></div>

  <div class="card-icon">🔒</div>
  <span class="logo">Sole<span>Mate</span></span>
  <span class="tagline">Shoe Cleaning &amp; Repair · Durban</span>

  <?php if ($done): ?>

  <div class="card-title" style="margin-bottom:4px">Password updated!</div>
  <div class="success-box" style="margin:1.25rem 0">
    <strong>✅ All done</strong>
    Your password has been changed successfully. Sign in below with your new password.
  </div>
  <a href="solemate_customer_login.php?reset=success" class="sign-in-btn">Sign in →</a>

  <?php elseif (!$tokenRow && !empty($errors)): ?>

  <div class="card-title" style="margin-bottom:4px">Reset link problem</div>
  <p class="card-sub">This link is no longer valid.</p>
  <div class="error-box">
    <span style="flex-shrink:0">⚠️</span>
    <span><?= e($errors[0]) ?></span>
  </div>
  <a href="solemate_forgot_password.php" class="sign-in-btn">Request a new link →</a>
  <a href="solemate_customer_login.php" class="back-link">← Back to Sign in</a>

  <?php else: ?>

  <div class="card-title" style="margin-bottom:4px">Choose a new password</div>
  <p class="card-sub">Pick something strong — at least 8 characters.</p>

  <?php if (!empty($errors)): ?>
  <div class="error-box">
    <span style="flex-shrink:0">⚠️</span>
    <div>
      <?php if (count($errors) === 1): ?>
        <?= e($errors[0]) ?>
      <?php else: ?>
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" style="margin-top:1rem">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="fg">
      <label>New password *</label>
      <div class="pw-wrap">
        <input type="password" name="password" id="pw" autocomplete="new-password"
               placeholder="Min. 8 characters" required minlength="8" maxlength="72" autofocus>
        <button type="button" class="pw-toggle" onclick="
          const f=document.getElementById('pw');
          f.type=f.type==='password'?'text':'password';
          this.textContent=f.type==='password'?'👁':'🙈'
        ">👁</button>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="sf"></div></div>
      <div class="pw-rules">8+ characters — mix letters, numbers, and symbols.</div>
    </div>

    <div class="fg">
      <label>Confirm new password *</label>
      <div class="pw-wrap">
        <input type="password" name="confirm_password" id="pw2" autocomplete="new-password"
               placeholder="Repeat your password" required maxlength="72">
        <button type="button" class="pw-toggle" onclick="
          const f=document.getElementById('pw2');
          f.type=f.type==='password'?'text':'password';
          this.textContent=f.type==='password'?'👁':'🙈'
        ">👁</button>
      </div>
    </div>

    <button type="submit" class="submit-btn">Update password →</button>
  </form>
  <a href="solemate_customer_login.php" class="back-link">← Cancel, go to Sign in</a>

  <?php endif; ?>
</div>

<script>
document.getElementById('pw')?.addEventListener('input', function () {
  const v = this.value, sf = document.getElementById('sf');
  let s = 0;
  if (v.length >= 8) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  const c = ['#e74c3c','#e67e22','#f1c40f','#2ecc71'];
  const w = ['25%','50%','75%','100%'];
  sf.style.width      = v.length ? (w[s-1]||'10%') : '0';
  sf.style.background = v.length ? (c[s-1]||'#e74c3c') : '';
});
</script>
</body>
</html>
