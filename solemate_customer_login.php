<?php
// ═══════════════════════════════════════════════════════
//  solemate_customer_login.php  —  Customer Login
// ═══════════════════════════════════════════════════════
session_start();

if (!empty($_SESSION['customer_logged_in'])) {
    header('Location: solemate_customer_dashboard.php');
    exit;
}

require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';

$error     = null;
$rateLimit = false;

// ── Brute-force protection ──────────────────────────────
if (!isset($_SESSION['cust_login_attempts'])) {
    $_SESSION['cust_login_attempts']    = 0;
    $_SESSION['cust_login_locked_until'] = 0;
}

if (time() < (int)$_SESSION['cust_login_locked_until']) {
    $rateLimit = true;
    $wait      = (int)$_SESSION['cust_login_locked_until'] - time();
    $error     = "Too many failed attempts. Please wait {$wait} second(s) before trying again.";
}

// Redirect-after-login destination (e.g. from requireCustomerLogin())
$redirectTo = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
// Sanitise: only allow relative URLs
if (!$redirectTo || !preg_match('/^\//', $redirectTo)) {
    $redirectTo = 'solemate_customer_dashboard.php';
}

// ── Handle POST ─────────────────────────────────────────
if (!$rateLimit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email address and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT TOP 1 * FROM dbo.customers WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // ✅ Success
                loginCustomer($user);
                $_SESSION['cust_login_attempts']    = 0;
                $_SESSION['cust_login_locked_until'] = 0;

                try {
                    $pdo->prepare("UPDATE dbo.customers SET last_login = GETDATE() WHERE id = ?")
                        ->execute([$user['id']]);
                } catch (\PDOException $e) {
                    error_log('[SoleMate login] last_login update failed: ' . $e->getMessage());
                }

                header('Location: ' . $redirectTo);
                exit;
            } else {
                $_SESSION['cust_login_attempts']++;
                if ($_SESSION['cust_login_attempts'] >= 5) {
                    $_SESSION['cust_login_locked_until'] = time() + 60;
                    $error = 'Too many failed attempts. Locked for 60 seconds.';
                } else {
                    $remaining = 5 - $_SESSION['cust_login_attempts'];
                    $error = "Incorrect email or password. {$remaining} attempt(s) remaining.";
                }
            }
        } catch (\PDOException $e) {
            error_log('[SoleMate login] lookup failed: ' . $e->getMessage());
            $error = 'We couldn\'t sign you in right now. Please try again in a moment.';
        }
    }
}

$bye     = isset($_GET['bye']);
$reset   = isset($_GET['reset']) && $_GET['reset'] === 'success';

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In — SoleMate</title>
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

.card-header{text-align:center;margin-bottom:2rem}
.logo{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--brown)}
.logo span{color:var(--accent)}
.tagline{font-size:12px;color:var(--muted);margin-top:4px;letter-spacing:.3px}
.card-icon{width:44px;height:44px;background:var(--dark);border-radius:12px;
  display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;
  font-size:20px;box-shadow:0 4px 12px rgba(26,15,8,.3)}
.card-title{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:700;margin-bottom:4px}
.card-sub{font-size:12px;color:var(--muted)}

.fg{margin-bottom:1rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);
  text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.fg input{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;
  padding:11px 14px;font-size:14px;font-family:inherit;background:var(--cream);
  color:var(--dark);outline:none;transition:border-color .18s,box-shadow .18s}
.fg input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.fg input::placeholder{color:rgba(107,66,38,.35)}
.pw-wrap{position:relative}
.pw-wrap input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;padding:4px}
.pw-toggle:hover{color:var(--accent)}

.forgot{display:block;text-align:right;font-size:11px;color:var(--muted);
  text-decoration:none;margin-top:-2px;margin-bottom:1rem}
.forgot:hover{color:var(--accent)}

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
  display:flex;align-items:flex-start;gap:8px;animation:shake .4s ease}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
.error-icon{font-size:15px;flex-shrink:0;margin-top:1px}

.success-box{background:#f0faf5;border:1.5px solid rgba(45,106,79,.3);border-radius:9px;
  padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#1a4731;
  display:flex;align-items:flex-start;gap:8px}

.divider{display:flex;align-items:center;gap:.75rem;margin:1.25rem 0 1rem;
  font-size:11px;color:rgba(107,66,38,.4)}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(107,66,38,.12)}

.register-link{display:block;text-align:center;font-size:13px;color:var(--muted);text-decoration:none}
.register-link span{color:var(--accent);font-weight:500}
.register-link:hover span{text-decoration:underline}
.back-link{display:block;text-align:center;margin-top:1rem;font-size:12px;
  color:var(--muted);text-decoration:none}
.back-link:hover{color:var(--accent)}
</style>
</head>
<body>

<div class="card">
  <div class="card-accent"></div>

  <div class="card-header">
    <div class="card-icon">🔑</div>
    <div class="logo">Sole<span>Mate</span></div>
    <div class="tagline">Shoe Cleaning &amp; Repair · Durban</div>
  </div>

  <div class="card-title" style="margin-bottom:4px">Sign in</div>
  <div class="card-sub" style="margin-bottom:1.5rem">Access your orders, addresses, and profile.</div>

  <?php if ($bye): ?>
  <div class="success-box">
    <span style="flex-shrink:0">✅</span>
    <span>You've been signed out successfully.</span>
  </div>
  <?php endif; ?>

  <?php if ($reset): ?>
  <div class="success-box">
    <span style="flex-shrink:0">✅</span>
    <span>Password updated! Sign in with your new password below.</span>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="error-box">
    <span class="error-icon">⚠️</span>
    <span><?= $error ?></span>
  </div>
  <?php endif; ?>

  <?php if (!$rateLimit): ?>
  <form method="POST" autocomplete="on">
    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">

    <div class="fg">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" autocomplete="email"
             placeholder="siya@example.com" required autofocus
             value="<?= e($_POST['email'] ?? '') ?>">
    </div>

    <div class="fg">
      <label for="password">Password</label>
      <div class="pw-wrap">
        <input type="password" id="password" name="password"
               autocomplete="current-password" placeholder="••••••••" required>
        <button type="button" class="pw-toggle" onclick="
          const f=document.getElementById('password');
          f.type=f.type==='password'?'text':'password';
          this.textContent=f.type==='password'?'👁':'🙈'
        ">👁</button>
      </div>
    </div>

    <a href="solemate_forgot_password.php" class="forgot">Forgot password?</a>

    <button type="submit" class="submit-btn">Sign in →</button>
  </form>
  <?php endif; ?>

  <div class="divider">new to SoleMate?</div>
  <a href="solemate_customer_register.php" class="register-link">
    Create a <span>free account</span>
  </a>
  <a href="solemate_homepage.php" class="back-link">← Back to SoleMate</a>
</div>

</body>
</html>