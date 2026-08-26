<?php
// solemate_payment_cancel.php  —  PayFast cancel return
session_start();
require 'includes/solemate_database_connection.php';

$cartRef = $_SESSION['sm_cart_ref'] ?? '';
if ($cartRef) {
    $pdo->prepare("UPDATE dbo.payments SET status=N'cancelled' WHERE cart_ref=? AND status=N'pending'")
        ->execute([$cartRef]);
    $pdo->prepare("UPDATE dbo.booking SET payment_status=N'cancelled' WHERE cart_ref=? AND payment_status=N'pending'")
        ->execute([$cartRef]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment cancelled — SoleMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;--accent:#D4561A;--muted:#8B7355}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--dark);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1.5rem}
.card{background:#fff;border-radius:18px;padding:2.5rem 2.25rem;width:100%;max-width:440px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);border:1px solid rgba(107,66,38,.08)}
.icon{font-size:40px;margin-bottom:1rem}
h1{font-family:'Playfair Display',serif;font-size:1.4rem;margin-bottom:.5rem}
p{font-size:14px;color:var(--muted);line-height:1.7;margin-bottom:1.5rem}
.btn-row{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap}
.btn-p{background:var(--dark);color:var(--tan);border:none;padding:11px 24px;border-radius:9px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none}
.btn-p:hover{background:#2a1a10;color:#e5c98e}
.btn-g{background:none;border:1.5px solid rgba(107,66,38,.22);color:var(--muted);padding:10px 22px;border-radius:9px;font-size:13px;text-decoration:none;display:inline-block}
.btn-g:hover{border-color:var(--accent);color:var(--accent)}
</style>
</head>
<body>
<div class="card">
  <div class="icon">❌</div>
  <h1>Payment cancelled</h1>
  <p>Your payment was cancelled and nothing has been charged. Your booking details are still saved — you can try again below.</p>
  <div class="btn-row">
    <a href="solemate_checkout.php" class="btn-p">Try again →</a>
    <a href="solemate_homepage.php" class="btn-g">Back to home</a>
  </div>
</div>
</body></html>
