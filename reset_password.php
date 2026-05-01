<?php
// ═══════════════════════════════════════════════════════
//  reset_password.php  —  One-time admin password reset
//
//  ⚠️  DELETE THIS FILE after use!
//  Anyone with the URL can reset the admin password.
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';

$message = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirm     = trim($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirm) {
        $message = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'admin'");
        $stmt->execute([$hash]);
        $success = true;
        $message = 'Password reset successfully! Delete this file now, then log in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SoleMate — Reset Admin Password</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#1A0F08;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:#FAF7F2;border-radius:14px;padding:2rem;width:100%;max-width:380px;box-shadow:0 20px 50px rgba(0,0,0,0.5)}
h1{font-size:1.25rem;font-weight:700;margin-bottom:4px;color:#1A0F08}
.warn{background:#fff8f6;border:1px solid rgba(212,86,26,0.4);border-radius:8px;padding:10px 14px;font-size:12px;color:#8B2200;margin-bottom:1.25rem;line-height:1.6}
.msg-success{background:#f0faf5;border:1px solid rgba(45,106,79,0.3);border-radius:8px;padding:10px 14px;font-size:13px;color:#2D6A4F;margin-bottom:1rem}
.msg-error{background:#fff8f6;border:1px solid rgba(212,86,26,0.3);border-radius:8px;padding:10px 14px;font-size:13px;color:#8B2200;margin-bottom:1rem}
label{display:block;font-size:11px;font-weight:500;color:#8B7355;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;margin-top:0.875rem}
input{width:100%;border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:10px 12px;font-size:14px;font-family:inherit;background:#FAF7F2;color:#1A0F08;outline:none}
input:focus{border-color:#D4561A}
button{width:100%;margin-top:1.25rem;background:#1A0F08;color:#C8A97E;border:none;padding:12px;border-radius:7px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit}
button:hover{background:#2a1a10}
a{display:block;text-align:center;margin-top:1rem;font-size:12px;color:#8B7355;text-decoration:none}
a:hover{color:#D4561A}
</style>
</head>
<body>
<div class="card">
  <h1>🔑 Reset Admin Password</h1>
  <p style="font-size:12px;color:#8B7355;margin-bottom:1rem">SoleMate · admin account</p>

  <div class="warn">
    ⚠️ <strong>Delete this file immediately after use.</strong><br>
    Anyone with this URL can reset your admin password.
  </div>

  <?php if ($message): ?>
    <div class="<?= $success ? 'msg-success' : 'msg-error' ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST">
    <label>New Password</label>
    <input type="password" name="new_password" placeholder="Min. 8 characters" required minlength="8" autofocus>
    <label>Confirm Password</label>
    <input type="password" name="confirm_password" placeholder="Repeat password" required>
    <button type="submit">Reset Password</button>
  </form>
  <?php else: ?>
    <a href="solemate_admin_login.php">→ Go to Admin Login</a>
  <?php endif; ?>
</div>
</body>
</html>
