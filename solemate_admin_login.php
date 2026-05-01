<?php
// ═══════════════════════════════════════════════════════
//  admin_login.php  —  SoleMate Admin Login
// ═══════════════════════════════════════════════════════
session_start();

// Already logged in? Go straight to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: solemate_admin_dashboard.php');
    exit;
}

require 'includes/solemate_database_connection.php';

$error      = null;
$rateLimit  = false;

// ── Brute-force protection ──────────────────────────────
// Track failed attempts in session (simple demo limiter)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts']  = 0;
    $_SESSION['login_locked_until'] = 0;
}

if (time() < $_SESSION['login_locked_until']) {
    $rateLimit  = true;
    $waitSecs   = $_SESSION['login_locked_until'] - time();
    $error      = "Too many failed attempts. Please wait {$waitSecs} second(s) before trying again.";
}

// ── Handle form POST ────────────────────────────────────
if (!$rateLimit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        // Fetch user from DB
        $stmt = $pdo->prepare("SELECT TOP 1 * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // ✅ Login success
            session_regenerate_id(true);
            $_SESSION['admin_logged_in']  = true;
            $_SESSION['admin_user_id']    = $user['id'];
            $_SESSION['admin_username']   = $user['username'];
            $_SESSION['admin_name']       = $user['display_name'] ?: $user['username'];
            $_SESSION['login_attempts']   = 0;

            // Update last_login timestamp
            $pdo->prepare("UPDATE admin_users SET last_login = GETDATE() WHERE id = ?")
                ->execute([$user['id']]);

            header('Location: solemate_admin_dashboard.php');
            exit;
        } else {
            // ❌ Failed attempt
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_locked_until'] = time() + 60; // 60-second lockout
                $error = 'Too many failed attempts. Locked for 60 seconds.';
            } else {
                $remaining = 5 - $_SESSION['login_attempts'];
                $error = "Invalid username or password. {$remaining} attempt(s) remaining.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SoleMate — Admin Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<link rel="icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;
  --accent:#D4561A;--light:#E8D5BE;--muted:#8B7355;
}

body{
  font-family:'DM Sans',system-ui,sans-serif;
  background:var(--dark);
  color:var(--dark);
  min-height:100vh;
  display:flex;
  align-items:flex-start;
  justify-content:center;
  padding:2rem 1.5rem;
  position:relative;
  overflow-x:hidden;
  overflow-y:auto;
}

/* Atmospheric background */
body::before{
  content:'';
  position:fixed;
  inset:0;
  background:
    radial-gradient(ellipse 60% 50% at 20% 50%, rgba(212,86,26,0.07) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 80% 30%, rgba(200,169,126,0.06) 0%, transparent 60%);
  pointer-events:none;
}

/* Subtle grid texture */
body::after{
  content:'';
  position:fixed;
  inset:0;
  background-image:
    linear-gradient(rgba(200,169,126,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(200,169,126,0.03) 1px, transparent 1px);
  background-size:40px 40px;
  pointer-events:none;
}

.login-card{
  background:rgba(250,247,242,0.97);
  border-radius:18px;
  padding:2.5rem 2.25rem 2rem;
  width:100%;
  max-width:400px;
  margin:auto;
  box-shadow:
    0 0 0 1px rgba(200,169,126,0.15),
    0 25px 60px rgba(0,0,0,0.5),
    0 8px 20px rgba(0,0,0,0.3);
  position:relative;
  z-index:1;
  animation:slideUp 0.4s cubic-bezier(0.16,1,0.3,1) both;
}

@keyframes slideUp{
  from{opacity:0;transform:translateY(24px)}
  to{opacity:1;transform:translateY(0)}
}

.card-header{text-align:center;margin-bottom:2rem}
.logo{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--brown);letter-spacing:-0.5px}
.logo span{color:var(--accent)}
.tagline{font-size:12px;color:var(--muted);margin-top:4px;letter-spacing:0.3px}

.admin-shield{
  width:44px;height:44px;
  background:var(--dark);
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 1rem;
  font-size:20px;
  box-shadow:0 4px 12px rgba(26,15,8,0.3);
}

.card-title{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:700;margin-bottom:4px}
.card-sub{font-size:12px;color:var(--muted)}

/* Form elements */
.fg{margin-bottom:1rem}
.fg label{
  display:block;font-size:11px;font-weight:500;
  color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px;
}
.fg input{
  width:100%;
  border:1.5px solid rgba(107,66,38,0.2);
  border-radius:9px;
  padding:11px 14px;
  font-size:14px;
  font-family:inherit;
  background:var(--cream);
  color:var(--dark);
  outline:none;
  transition:border-color 0.18s, box-shadow 0.18s;
}
.fg input:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(212,86,26,0.1);
}
.fg input::placeholder{color:rgba(107,66,38,0.35)}

.submit-btn{
  width:100%;
  background:var(--dark);
  color:var(--tan);
  border:none;
  padding:13px;
  border-radius:9px;
  font-size:14px;
  font-weight:500;
  cursor:pointer;
  font-family:inherit;
  margin-top:0.25rem;
  transition:all 0.18s;
  position:relative;
  overflow:hidden;
}
.submit-btn::after{
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(135deg,rgba(200,169,126,0.1) 0%,transparent 60%);
  opacity:0;
  transition:opacity 0.18s;
}
.submit-btn:hover{background:#2a1a10;color:#e5c98e}
.submit-btn:hover::after{opacity:1}
.submit-btn:active{transform:scale(0.99)}

.error-box{
  background:#fff8f6;
  border:1.5px solid rgba(212,86,26,0.3);
  border-radius:9px;
  padding:10px 14px;
  margin-bottom:1.25rem;
  font-size:13px;
  color:#8B2200;
  display:flex;
  align-items:flex-start;
  gap:8px;
  animation:shake 0.4s ease;
}
@keyframes shake{
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)}
  60%{transform:translateX(-4px)}
  80%{transform:translateX(4px)}
}
.error-icon{font-size:15px;flex-shrink:0;margin-top:1px}

.back-link{
  display:block;text-align:center;margin-top:1.375rem;
  font-size:12px;color:var(--muted);text-decoration:none;
}
.back-link:hover{color:var(--accent)}

/* Decorative corner accent */
.card-accent{
  position:absolute;top:0;right:0;
  width:80px;height:80px;
  background:linear-gradient(225deg,rgba(212,86,26,0.08) 0%,transparent 60%);
  border-radius:0 18px 0 0;
  pointer-events:none;
}

/* Default credentials hint */
.hint-box{
  background:rgba(107,66,38,0.06);
  border:1px solid rgba(107,66,38,0.1);
  border-radius:8px;
  padding:9px 12px;
  font-size:11px;
  color:var(--muted);
  margin-bottom:1.375rem;
  line-height:1.65;
}
.hint-box strong{color:var(--brown)}
code{
  background:rgba(107,66,38,0.1);
  padding:1px 6px;
  border-radius:4px;
  font-size:11px;
  color:var(--brown);
  font-family:monospace;
}
</style>
</head>
<body>

<div class="login-card">
  <div class="card-accent"></div>

  <div class="card-header">
    <div class="admin-shield">🔐</div>
    <div class="logo">Sole<span>Mate</span></div>
    <div class="tagline">Shoe Cleaning &amp; Repair · Durban</div>
  </div>

  <div class="card-title">Admin access</div>
  <div class="card-sub" style="margin-bottom:1.375rem">Sign in to manage orders and services.</div>

  <?php if ($error): ?>
    <div class="error-box">
      <span class="error-icon">⚠️</span>
      <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <!-- Default credentials hint — remove in production -->
  <div class="hint-box">
    <strong>Demo credentials:</strong><br>
    Username: <code>admin</code> &nbsp;·&nbsp; Password: <code>solemate2024</code><br>
    <em>Remove this hint in production.</em>
  </div>

  <?php if (!$rateLimit): ?>
  <form method="POST" autocomplete="on">
    <div class="fg">
      <label>Username</label>
      <input
        type="text"
        name="username"
        autocomplete="username"
        placeholder="admin"
        value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        required
        autofocus
      >
    </div>
    <div class="fg">
      <label>Password</label>
      <input
        type="password"
        name="password"
        autocomplete="current-password"
        placeholder="••••••••••"
        required
      >
    </div>
    <button type="submit" class="submit-btn">Sign in →</button>
  </form>
  <?php endif; ?>

  <a href="solemate_homepage.php" class="back-link">← Back to SoleMate website</a>
</div>

</body>
</html>