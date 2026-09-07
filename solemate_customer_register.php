<?php
// ═══════════════════════════════════════════════════════
//  solemate_customer_register.php  —  Customer Registration
// ═══════════════════════════════════════════════════════
session_start();

// Already logged in? Go to dashboard
if (!empty($_SESSION['customer_logged_in'])) {
    header('Location: solemate_customer_dashboard.php');
    exit;
}

require 'includes/solemate_database_connection.php';
require_once 'includes/solemate_ecommerce.php';
require 'includes/solemate_mailer.php';
$settings = getSiteSettings($pdo);
require 'includes/solemate_customer_mailer.php';
require 'includes/solemate_customer_auth.php';

$errors        = [];
$success       = false;
$referralCodeIn = strtoupper(trim($_GET['ref'] ?? $_POST['referral_code'] ?? ''));
$old     = [];  // repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['first_name'] = trim($_POST['first_name'] ?? '');
    $old['last_name']  = trim($_POST['last_name']  ?? '');
    $old['email']      = strtolower(trim($_POST['email'] ?? ''));
    $old['phone']      = trim($_POST['phone'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['confirm_password'] ?? '';

    // ── Validation ───────────────────────────────────────
    if (!$old['first_name'])
        $errors[] = 'First name is required.';
    elseif (mb_strlen($old['first_name']) > 60)
        $errors[] = 'First name is too long (max 60 characters).';
    elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $old['first_name']))
        $errors[] = 'First name may only contain letters.';

    if (!$old['last_name'])
        $errors[] = 'Last name is required.';
    elseif (mb_strlen($old['last_name']) > 60)
        $errors[] = 'Last name is too long (max 60 characters).';
    elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $old['last_name']))
        $errors[] = 'Last name may only contain letters.';

    $phoneDigits = preg_replace('/\D+/', '', $old['phone']);
    if (!$old['phone'])
        $errors[] = 'Phone number is required.';
    elseif (strlen($phoneDigits) !== 10)
        $errors[] = 'Phone number must be exactly 10 digits.';
    else
        $old['phone'] = $phoneDigits;

    if (!$old['email'])
        $errors[] = 'Email address is required.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    elseif (mb_strlen($old['email']) > 100)
        $errors[] = 'Email address is too long.';

    if (!$password)
        $errors[] = 'Password is required.';
    elseif (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    elseif (strlen($password) > 72)
        $errors[] = 'Password is too long (max 72 characters).';

    if ($password && $password !== $confirm)
        $errors[] = 'Passwords do not match.';

    // Check email not already registered
    if (empty($errors)) {
        try {
            $chk = $pdo->prepare("SELECT id FROM dbo.customers WHERE email = ?");
            $chk->execute([$old['email']]);
            if ($chk->fetch()) {
                $errors[] = 'An account with that email already exists. <a href="solemate_customer_login.php" class="inline-link">Sign in instead?</a>';
            }
        } catch (\PDOException $e) {
            error_log('[SoleMate register] email lookup failed: ' . $e->getMessage());
            $errors[] = 'We couldn\'t check that email right now. Please try again in a moment.';
        }
    }

    // ── Insert ───────────────────────────────────────────
    if (empty($errors)) {
        try {
            $hash        = password_hash($password, PASSWORD_BCRYPT);
            $newRefCode  = generateReferralCode($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO dbo.customers
                    (first_name, last_name, email, phone, password_hash, is_verified, referral_code)
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([
                $old['first_name'],
                $old['last_name'],
                $old['email'],
                $old['phone'],
                $hash,
                $newRefCode,
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Record referral if a valid referral code was provided
            if ($referralCodeIn !== '' && $settings['referrals']) {
                try {
                    recordReferral($pdo, $newId, $referralCodeIn);
                } catch (\Throwable $e) {
                    error_log('[SoleMate referral] ' . $e->getMessage());
                }
            }

            // ── Auto log in ──────────────────────────────────
            loginCustomer([
                'id'         => $newId,
                'first_name' => $old['first_name'],
                'last_name'  => $old['last_name'],
                'email'      => $old['email'],
            ]);

            // ── Update last_login ────────────────────────────
            try {
                $pdo->prepare("UPDATE dbo.customers SET last_login = GETDATE() WHERE id = ?")
                    ->execute([$newId]);
            } catch (\PDOException $e) {
                error_log('[SoleMate register] last_login update failed: ' . $e->getMessage());
            }

            // ── Welcome email ────────────────────────────────
            try {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $dashUrl  = $protocol . '://' . $host . '/SoleMate/solemate_customer_dashboard.php';
                sendWelcomeEmail([
                    'name'          => $old['first_name'],
                    'email'         => $old['email'],
                    'dashboard_url' => $dashUrl,
                ]);
            } catch (\Throwable $e) {
                error_log('[SoleMate register] welcome email failed: ' . $e->getMessage());
            }

            header('Location: solemate_customer_dashboard.php?welcome=1');
            exit;
        } catch (\PDOException $e) {
            error_log('[SoleMate register] account creation failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong creating your account. Please try again.';
        }
    }
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account — SoleMate</title>
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
  font-family:'DM Sans',system-ui,sans-serif;
  background:var(--dark);color:var(--dark);
  min-height:100vh;display:flex;align-items:flex-start;
  justify-content:center;padding:2rem 1.5rem;
  position:relative;overflow-x:hidden;overflow-y:auto;
}
body::before{
  content:'';position:fixed;inset:0;
  background:
    radial-gradient(ellipse 60% 50% at 20% 50%,rgba(212,86,26,0.07) 0%,transparent 60%),
    radial-gradient(ellipse 50% 60% at 80% 30%,rgba(200,169,126,0.06) 0%,transparent 60%);
  pointer-events:none;
}
body::after{
  content:'';position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(200,169,126,0.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(200,169,126,0.03) 1px,transparent 1px);
  background-size:40px 40px;pointer-events:none;
}
.card{
  background:rgba(250,247,242,0.97);border-radius:18px;
  padding:2.5rem 2.25rem 2rem;width:100%;max-width:440px;
  margin:auto;
  box-shadow:0 0 0 1px rgba(200,169,126,0.15),0 25px 60px rgba(0,0,0,0.5),0 8px 20px rgba(0,0,0,0.3);
  position:relative;z-index:1;
  animation:slideUp 0.4s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.card-accent{position:absolute;top:0;right:0;width:80px;height:80px;
  background:linear-gradient(225deg,rgba(212,86,26,0.08) 0%,transparent 60%);
  border-radius:0 18px 0 0;pointer-events:none}
.card-header{text-align:center;margin-bottom:1.75rem}
.logo{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--brown)}
.logo span{color:var(--accent)}
.tagline{font-size:12px;color:var(--muted);margin-top:4px;letter-spacing:.3px}
.card-icon{width:44px;height:44px;background:var(--dark);border-radius:12px;
  display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;
  font-size:20px;box-shadow:0 4px 12px rgba(26,15,8,.3)}
.card-title{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:700;margin-bottom:4px}
.card-sub{font-size:12px;color:var(--muted)}

.row-2{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem}
.fg{margin-bottom:0.875rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);
  text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.fg input{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;
  padding:11px 14px;font-size:14px;font-family:inherit;background:var(--cream);
  color:var(--dark);outline:none;transition:border-color .18s,box-shadow .18s}
.fg input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.fg input::placeholder{color:rgba(107,66,38,.35)}
.fg .hint{font-size:11px;color:rgba(107,66,38,.5);margin-top:4px}

.pw-wrap{position:relative}
.pw-wrap input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;padding:4px;
  transition:color .15s}
.pw-toggle:hover{color:var(--accent)}

.strength-bar{height:3px;border-radius:2px;background:rgba(107,66,38,.1);
  margin-top:6px;overflow:hidden}
.strength-fill{height:100%;width:0;transition:width .3s,background .3s;border-radius:2px}

.submit-btn{width:100%;background:var(--dark);color:var(--tan);border:none;
  padding:13px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;
  font-family:inherit;margin-top:.25rem;transition:all .18s;position:relative;overflow:hidden}
.submit-btn::after{content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(200,169,126,.1) 0%,transparent 60%);
  opacity:0;transition:opacity .18s}
.submit-btn:hover{background:#2a1a10;color:#e5c98e}
.submit-btn:hover::after{opacity:1}
.submit-btn:active{transform:scale(.99)}

.error-box{background:#fff8f6;border:1.5px solid rgba(212,86,26,.3);border-radius:9px;
  padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#8B2200;
  display:flex;align-items:flex-start;gap:8px}
.error-box ul{margin:.25rem 0 0 1rem;line-height:1.9}
.error-icon{font-size:15px;flex-shrink:0;margin-top:1px}
.inline-link{color:var(--accent);text-decoration:none}
.inline-link:hover{text-decoration:underline}

.divider{display:flex;align-items:center;gap:.75rem;margin:1.25rem 0 1rem;
  font-size:11px;color:rgba(107,66,38,.4)}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(107,66,38,.12)}

.sign-in-link{display:block;text-align:center;font-size:13px;color:var(--muted);text-decoration:none}
.sign-in-link span{color:var(--accent);font-weight:500;text-decoration:none}
.sign-in-link:hover span{text-decoration:underline}
.back-link{display:block;text-align:center;margin-top:1rem;font-size:12px;
  color:var(--muted);text-decoration:none}
.back-link:hover{color:var(--accent)}

.pw-rules{font-size:11px;color:rgba(107,66,38,.5);margin-top:4px;line-height:1.6}
</style>
</head>
<body>

<div class="card">
  <div class="card-accent"></div>

  <div class="card-header">
    <div class="card-icon">👟</div>
    <div class="logo">Sole<span>Mate</span></div>
    <div class="tagline">Shoe Cleaning &amp; Repair · Durban</div>
  </div>

  <div class="card-title" style="margin-bottom:4px">Create your account</div>
  <div class="card-sub" style="margin-bottom:1.5rem">Track orders, save addresses, manage your profile.</div>

  <?php if (!empty($errors)): ?>
  <div class="error-box">
    <span class="error-icon">⚠️</span>
    <div>
      <?php if (count($errors) === 1): ?>
        <?= $errors[0] ?>
      <?php else: ?>
        Please fix the following:
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= $err ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" autocomplete="on" novalidate>

    <div class="row-2">
      <div class="fg">
        <label for="first_name">First name *</label>
        <input type="text" id="first_name" name="first_name" autocomplete="given-name"
               placeholder="Siya" required maxlength="60"
               pattern="[A-Za-z\s'\-]+" title="Letters only"
               value="<?= e($old['first_name'] ?? '') ?>">
      </div>
      <div class="fg">
        <label for="last_name">Last name *</label>
        <input type="text" id="last_name" name="last_name" autocomplete="family-name"
               placeholder="Khoza" required maxlength="60"
               pattern="[A-Za-z\s'\-]+" title="Letters only"
               value="<?= e($old['last_name'] ?? '') ?>">
      </div>
    </div>

    <div class="fg">
      <label for="email">Email address *</label>
      <input type="email" id="email" name="email" autocomplete="email"
             placeholder="siya@example.com" required maxlength="100"
             value="<?= e($old['email'] ?? '') ?>">
    </div>

    <div class="fg">
      <label for="phone">Phone number *</label>
      <input type="tel" id="phone" name="phone" autocomplete="tel" inputmode="numeric"
             placeholder="0821234567" required maxlength="10"
             pattern="[0-9]{10}" title="Exactly 10 digits"
             value="<?= e($old['phone'] ?? '') ?>">
      <div class="hint">10 digits, numbers only.</div>
    </div>

    <div class="fg">
      <label for="password">Password *</label>
      <div class="pw-wrap">
        <input type="password" id="password" name="password" autocomplete="new-password"
               placeholder="Min. 8 characters" required minlength="8" maxlength="72">
        <button type="button" class="pw-toggle" onclick="togglePw('password',this)" title="Show/hide">👁</button>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
      <div class="pw-rules">8+ characters recommended — use a mix of letters, numbers and symbols.</div>
    </div>

    <div class="fg">
      <label for="confirm_password">Confirm password *</label>
      <div class="pw-wrap">
        <input type="password" id="confirm_password" name="confirm_password"
               autocomplete="new-password" placeholder="Repeat your password" required maxlength="72">
        <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)" title="Show/hide">👁</button>
      </div>
    </div>

    <?php if ($settings['referrals']): ?>
    <div class="fg" style="margin-top:.5rem">
      <label for="referral_code" style="display:flex;align-items:center;gap:6px">
        Referral code <span style="font-size:10px;color:rgba(107,66,38,.45);font-weight:400">(optional)</span>
      </label>
      <input type="text" id="referral_code" name="referral_code"
             placeholder="REF-XXXXXX"
             value="<?= htmlspecialchars($referralCodeIn, ENT_QUOTES, 'UTF-8') ?>"
             maxlength="12"
             style="text-transform:uppercase;font-family:monospace;letter-spacing:1px">
      <div style="font-size:11px;color:rgba(107,66,38,.5);margin-top:4px">
        Got an invite from a friend? Enter their code to help them earn rewards.
      </div>
    </div>
    <?php endif; ?>

    <button type="submit" class="submit-btn">Create account →</button>
  </form>

  <div class="divider">already have an account?</div>
  <a href="solemate_customer_login.php" class="sign-in-link">
    Sign in to <span>My SoleMate</span>
  </a>
  <a href="solemate_homepage.php" class="back-link">← Back to SoleMate</a>
</div>

<script>
// Strip disallowed characters as the user types
document.getElementById('first_name').addEventListener('input', function () {
  this.value = this.value.replace(/[^A-Za-z\s'-]/g, '');
});
document.getElementById('last_name').addEventListener('input', function () {
  this.value = this.value.replace(/[^A-Za-z\s'-]/g, '');
});
document.getElementById('phone').addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

function togglePw(fieldId, btn) {
  const f = document.getElementById(fieldId);
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.textContent = f.type === 'password' ? '👁' : '🙈';
}

// Password strength meter
document.getElementById('password').addEventListener('input', function () {
  const v = this.value;
  const fill = document.getElementById('strength-fill');
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const colours = ['#e74c3c','#e67e22','#f1c40f','#2ecc71'];
  const widths  = ['25%','50%','75%','100%'];
  fill.style.width      = v.length ? widths[score - 1] || '10%' : '0';
  fill.style.background = v.length ? colours[score - 1] || '#e74c3c' : '';
});
</script>
</body>
</html>