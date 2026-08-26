<?php
// ═══════════════════════════════════════════════════════
//  solemate_forgot_password.php  —  Request a password reset
// ═══════════════════════════════════════════════════════
session_start();

if (!empty($_SESSION['customer_logged_in'])) {
    header('Location: solemate_customer_dashboard.php');
    exit;
}

require 'includes/solemate_database_connection.php';
require 'includes/solemate_mailer.php';
require 'includes/solemate_customer_mailer.php';

$errors  = [];
$sent    = false;
$email   = '';
$EXPIRES_MINS = 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!$email) {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name, email FROM dbo.customers WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Only generate/send if the account exists, but always show the
            // same success message so we don't leak which emails are registered.
            if ($user) {
                try {
                    $token     = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', time() + $EXPIRES_MINS * 60);

                    $pdo->prepare("
                        INSERT INTO dbo.password_resets (email, token, expires_at)
                        VALUES (?, ?, ?)
                    ")->execute([$user['email'], $token, $expiresAt]);

                    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $resetUrl  = $protocol . '://' . $host . '/SoleMate/solemate_reset_password.php?token=' . urlencode($token);

                    try {
                        sendPasswordResetEmail([
                            'name'         => $user['first_name'],
                            'email'        => $user['email'],
                            'reset_url'    => $resetUrl,
                            'expires_mins' => $EXPIRES_MINS,
                        ]);
                    } catch (\Throwable $e) {
                        error_log('[SoleMate forgot-password] email send failed: ' . $e->getMessage());
                    }
                } catch (\PDOException $e) {
                    error_log('[SoleMate forgot-password] token insert failed: ' . $e->getMessage());
                    // Still show the generic success screen — don't reveal DB trouble to the user.
                }
            }

            $sent = true;
        } catch (\PDOException $e) {
            error_log('[SoleMate forgot-password] lookup failed: ' . $e->getMessage());
            $errors[] = 'We couldn\'t process that request right now. Please try again in a moment.';
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
<title>Forgot Password — SoleMate</title>
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
.fg input{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;
  padding:11px 14px;font-size:14px;font-family:inherit;background:var(--cream);
  color:var(--dark);outline:none;transition:border-color .18s,box-shadow .18s}
.fg input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.fg input::placeholder{color:rgba(107,66,38,.35)}

.submit-btn{width:100%;background:var(--dark);color:var(--tan);border:none;
  padding:13px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;
  font-family:inherit;transition:all .18s;position:relative;overflow:hidden}
.submit-btn:hover{background:#2a1a10;color:#e5c98e}
.submit-btn:active{transform:scale(.99)}

.error-box{background:#fff8f6;border:1.5px solid rgba(212,86,26,.3);border-radius:9px;
  padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#8B2200;
  display:flex;align-items:flex-start;gap:8px}
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

  <?php if ($sent): ?>

    <div class="card-title" style="margin-bottom:4px">Check your inbox</div>
    <div class="success-box" style="margin:1.25rem 0">
      <strong>✅ Email sent</strong>
      If an account exists for <strong><?= e($email) ?></strong>, we've sent a link to reset your password.
      It expires in <?= (int)$EXPIRES_MINS ?> minutes.
    </div>
    <a href="solemate_customer_login.php" class="sign-in-btn">← Back to Sign in</a>

  <?php else: ?>

    <div class="card-title" style="margin-bottom:4px">Forgot your password?</div>
    <div class="card-sub">Enter your email and we'll send you a link to reset it.</div>

    <?php if (!empty($errors)): ?>
    <div class="error-box">
      <span style="flex-shrink:0">⚠️</span>
      <div>
        <?php if (count($errors) === 1): ?>
          <?= e($errors[0]) ?>
        <?php else: ?>
          <ul style="margin:.25rem 0 0 1rem;line-height:1.9">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="fg">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" autocomplete="email"
               placeholder="siya@example.com" required autofocus
               value="<?= e($email) ?>">
      </div>
      <button type="submit" class="submit-btn">Send reset link →</button>
    </form>
    <a href="solemate_customer_login.php" class="back-link">← Back to Sign in</a>

  <?php endif; ?>
</div>

</body>
</html>