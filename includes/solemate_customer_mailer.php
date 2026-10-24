<?php
// ═══════════════════════════════════════════════════════
//  solemate_customer_mailer.php  —  Customer Email Functions
//
//  Adds:
//    sendWelcomeEmail($data)       — sent on registration
//    sendPasswordResetEmail($data) — sent on forgot-password request
//
//  Requires solemate_mailer.php (already included by the pages
//  that use it, for smtpConfigure() and loadEnv()).
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/solemate_url.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('smtpConfigure')) {
    // Safety: if this file is included standalone, pull in the main mailer
    require __DIR__ . '/solemate_mailer.php';
}

// ── Shared brand colours (inline CSS for email clients) ──────
define('CM_DARK',   '#1A0F08');
define('CM_TAN',    '#C8A97E');
define('CM_ACCENT', '#D4561A');
define('CM_CREAM',  '#FAF7F2');
define('CM_MUTED',  '#8B7355');

// ─────────────────────────────────────────────────────────────
//  Welcome email — sent immediately after registration
// ─────────────────────────────────────────────────────────────
function sendWelcomeEmail(array $data): bool
{
    $mail = new PHPMailer(true);
    try {
        smtpConfigure($mail);
        $mail->setFrom(getenv('MAIL_USERNAME'), getenv('MAIL_FROM_NAME') ?: 'SoleMate');
        $mail->addAddress($data['email'], $data['name']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Welcome to SoleMate, ' . $data['name'] . '!';
        $mail->Body    = _buildWelcomeHtml($data['name'], $data['dashboard_url']);
        $mail->AltBody = "Hi {$data['name']},\r\n\r\nWelcome to SoleMate! Your account is ready.\r\n\r\nSign in to track orders, save addresses, and manage your profile:\r\n{$data['dashboard_url']}\r\n\r\n— The SoleMate Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('SoleMate welcome email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function _buildWelcomeHtml(string $name, string $dashboardUrl): string
{
    $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:\'DM Sans\',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:40px 20px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">
      <!-- Header -->
      <tr><td style="background:' . CM_DARK . ';padding:32px 40px;text-align:center">
        <div style="font-family:Georgia,serif;font-size:28px;font-weight:700;color:' . CM_TAN . ';letter-spacing:-0.5px">
          Sole<span style="color:' . CM_ACCENT . '">Mate</span>
        </div>
        <div style="font-size:12px;color:rgba(200,169,126,0.6);margin-top:4px">Shoe Cleaning &amp; Repair · Durban</div>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:36px 40px">
        <h2 style="font-family:Georgia,serif;font-size:22px;color:' . CM_DARK . ';margin:0 0 12px">
          Welcome, ' . $esc($name) . '! 👟
        </h2>
        <p style="color:' . CM_MUTED . ';font-size:15px;line-height:1.7;margin:0 0 20px">
          Your SoleMate account is all set. You can now track your orders, save delivery addresses, and manage your profile — all in one place.
        </p>
        <!-- CTA -->
        <table cellpadding="0" cellspacing="0"><tr><td style="background:' . CM_ACCENT . ';border-radius:8px">
          <a href="' . $esc($dashboardUrl) . '" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none">
            Go to My Account →
          </a>
        </td></tr></table>
        <p style="color:rgba(107,66,38,0.5);font-size:12px;margin:24px 0 0;line-height:1.6">
          Need help? Reply to this email or visit <a href="https://solemate.co.za" style="color:' . CM_TAN . '">solemate.co.za</a>
        </p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:' . CM_CREAM . ';padding:20px 40px;text-align:center;border-top:1px solid #ede6db">
        <p style="color:' . CM_MUTED . ';font-size:11px;margin:0">© SoleMate · Durban, South Africa</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';
}

// ─────────────────────────────────────────────────────────────
//  Gift Card email — sent to recipient after payment confirms
//  $data keys:
//    recipient_email, recipient_name
//    sender_name, sender_email
//    code, balance (float), expires_at (Y-m-d|null), message (optional)
// ─────────────────────────────────────────────────────────────
function sendGiftCardEmail(array $data): bool
{
    $mail = new PHPMailer(true);
    try {
        smtpConfigure($mail);
        $mail->setFrom(getenv('MAIL_USERNAME'), getenv('MAIL_FROM_NAME') ?: 'SoleMate');
        $mail->addAddress($data['recipient_email'], $data['recipient_name'] ?? '');

        // BCC the sender so they have confirmation the card was delivered
        if (!empty($data['sender_email'])) {
            $mail->addBCC($data['sender_email'], $data['sender_name'] ?? '');
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'You\'ve received a SoleMate Gift Card 🎁';
        $mail->Body    = _buildGiftCardHtml($data);
        $mail->AltBody = _buildGiftCardText($data);
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('SoleMate gift card email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function _buildGiftCardHtml(array $d): string
{
    $esc      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $recipName= $esc($d['recipient_name'] ?? 'there');
    $senderName= $esc($d['sender_name']   ?? 'Someone');
    $code     = $esc($d['code']           ?? '');
    $balance  = 'R' . number_format((float)($d['balance'] ?? 0), 2);
    $expires  = !empty($d['expires_at'])
        ? date('d M Y', strtotime($d['expires_at']))
        : '12 months from purchase';
    $msgBlock = '';
    if (!empty($d['message'])) {
        $msgBlock = '
        <div style="background:#f4f1ec;border-left:3px solid ' . CM_TAN . ';padding:14px 18px;margin:20px 0;border-radius:0 8px 8px 0;font-size:14px;color:' . CM_MUTED . ';line-height:1.7;font-style:italic">
          &ldquo;' . $esc($d['message']) . '&rdquo;
        </div>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:\'DM Sans\',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:40px 20px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">
      <!-- Header -->
      <tr><td style="background:' . CM_DARK . ';padding:32px 40px;text-align:center">
        <div style="font-family:Georgia,serif;font-size:28px;font-weight:700;color:' . CM_TAN . ';letter-spacing:-0.5px">
          Sole<span style="color:' . CM_ACCENT . '">Mate</span>
        </div>
        <div style="font-size:12px;color:rgba(200,169,126,0.6);margin-top:4px">Shoe Cleaning &amp; Repair · Durban</div>
      </td></tr>
      <!-- Gift card visual -->
      <tr><td style="padding:32px 40px 20px;text-align:center">
        <div style="background:linear-gradient(135deg,' . CM_DARK . ' 0%,#3d1f0e 100%);border-radius:16px;padding:28px;max-width:380px;margin:0 auto;text-align:left;box-shadow:0 8px 28px rgba(0,0,0,0.18)">
          <div style="font-family:Georgia,serif;font-size:22px;font-weight:700;color:' . CM_TAN . '">Sole<span style="color:' . CM_ACCENT . '">Mate</span></div>
          <div style="font-size:10px;color:rgba(200,169,126,0.5);letter-spacing:1px;margin-top:2px">GIFT CARD</div>
          <div style="font-family:Georgia,serif;font-size:2.2rem;font-weight:700;color:#fff;text-align:right;margin-top:16px">' . $balance . '</div>
          <div style="font-family:monospace;font-size:14px;color:rgba(200,169,126,0.7);letter-spacing:2px;margin-top:12px">' . $code . '</div>
          <div style="font-size:10px;color:rgba(200,169,126,0.4);text-align:right;margin-top:4px">Valid until ' . $expires . '</div>
        </div>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:20px 40px 36px">
        <h2 style="font-family:Georgia,serif;font-size:22px;color:' . CM_DARK . ';margin:0 0 8px">
          Hi ' . $recipName . '! 🎁
        </h2>
        <p style="color:' . CM_MUTED . ';font-size:15px;line-height:1.7;margin:0 0 4px">
          <strong style="color:' . CM_DARK . '">' . $senderName . '</strong> has sent you a SoleMate gift card worth <strong style="color:' . CM_DARK . '">' . $balance . '</strong>.
        </p>
        ' . $msgBlock . '
        <p style="color:' . CM_MUTED . ';font-size:14px;line-height:1.7;margin:16px 0 8px">
          <strong style="color:' . CM_DARK . '">How to redeem:</strong>
        </p>
        <ol style="color:' . CM_MUTED . ';font-size:14px;line-height:2;margin:0 0 20px;padding-left:20px">
          <li>Book any SoleMate shoe cleaning or repair service</li>
          <li>At checkout, enter your gift card code in the <em>Gift card</em> field</li>
          <li>Your balance will be applied automatically</li>
        </ol>
        <!-- Code callout -->
        <div style="background:#f4f1ec;border:2px dashed rgba(107,66,38,0.2);border-radius:10px;padding:16px 20px;text-align:center;margin:0 0 24px">
          <div style="font-size:11px;color:' . CM_MUTED . ';text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Your gift card code</div>
          <div style="font-family:monospace;font-size:22px;font-weight:700;color:' . CM_DARK . ';letter-spacing:3px">' . $code . '</div>
          <div style="font-size:11px;color:rgba(107,66,38,0.45);margin-top:6px">Valid until ' . $expires . '</div>
        </div>
        <!-- CTA -->
        <table cellpadding="0" cellspacing="0" width="100%"><tr><td align="center">
          <table cellpadding="0" cellspacing="0"><tr><td style="background:' . CM_ACCENT . ';border-radius:8px">
            <a href="' . appBaseUrl() . '/solemate_homepage.php?page=booking" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none">
              Book a service now →
            </a>
          </td></tr></table>
        </td></tr></table>
        <p style="color:rgba(107,66,38,0.45);font-size:12px;margin:24px 0 0;line-height:1.6;text-align:center">
          Redeemable on any SoleMate service · Durban, South Africa<br>
          Questions? Reply to this email.
        </p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:' . CM_CREAM . ';padding:20px 40px;text-align:center;border-top:1px solid #ede6db">
        <p style="color:' . CM_MUTED . ';font-size:11px;margin:0">© SoleMate · Durban, South Africa</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';
}

function _buildGiftCardText(array $d): string
{
    $code    = $d['code']    ?? '';
    $balance = 'R' . number_format((float)($d['balance'] ?? 0), 2);
    $expires = !empty($d['expires_at']) ? date('d M Y', strtotime($d['expires_at'])) : '12 months from purchase';
    $from    = $d['sender_name']    ?? 'Someone';
    $to      = $d['recipient_name'] ?? '';
    $msg     = !empty($d['message']) ? "\n\"{$d['message']}\"\n" : '';

    return "Hi {$to},\r\n\r\n{$from} has sent you a SoleMate gift card worth {$balance}!{$msg}\r\n"
         . "YOUR GIFT CARD CODE: {$code}\r\n"
         . "Valid until: {$expires}\r\n\r\n"
         . "How to redeem:\r\n"
         . "1. Book any SoleMate shoe cleaning or repair service\r\n"
         . "2. At checkout, enter your gift card code in the 'Gift card' field\r\n"
         . "3. Your balance will be applied automatically\r\n\r\n"
         . "— The SoleMate Team";
}

// ─────────────────────────────────────────────────────────────
//  Password reset email
// ─────────────────────────────────────────────────────────────
function sendPasswordResetEmail(array $data): bool
{
    $mail = new PHPMailer(true);
    try {
        smtpConfigure($mail);
        $mail->setFrom(getenv('MAIL_USERNAME'), getenv('MAIL_FROM_NAME') ?: 'SoleMate');
        $mail->addAddress($data['email'], $data['name']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Reset your SoleMate password';
        $mail->Body    = _buildResetHtml($data['name'], $data['reset_url'], $data['expires_mins']);
        $mail->AltBody = "Hi {$data['name']},\r\n\r\nUse the link below to reset your SoleMate password. It expires in {$data['expires_mins']} minutes.\r\n\r\n{$data['reset_url']}\r\n\r\nIf you didn't request this, ignore this email — your password won't change.\r\n\r\n— The SoleMate Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('SoleMate password reset email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function _buildResetHtml(string $name, string $resetUrl, int $expiresMins): string
{
    $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:\'DM Sans\',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:40px 20px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">
      <tr><td style="background:' . CM_DARK . ';padding:32px 40px;text-align:center">
        <div style="font-family:Georgia,serif;font-size:28px;font-weight:700;color:' . CM_TAN . '">
          Sole<span style="color:' . CM_ACCENT . '">Mate</span>
        </div>
        <div style="font-size:12px;color:rgba(200,169,126,0.6);margin-top:4px">Shoe Cleaning &amp; Repair · Durban</div>
      </td></tr>
      <tr><td style="padding:36px 40px">
        <h2 style="font-family:Georgia,serif;font-size:22px;color:' . CM_DARK . ';margin:0 0 12px">
          Reset your password 🔐
        </h2>
        <p style="color:' . CM_MUTED . ';font-size:15px;line-height:1.7;margin:0 0 8px">
          Hi ' . $esc($name) . ',
        </p>
        <p style="color:' . CM_MUTED . ';font-size:15px;line-height:1.7;margin:0 0 24px">
          We received a request to reset your password. Click the button below — this link expires in <strong style="color:' . CM_DARK . '">' . (int)$expiresMins . ' minutes</strong>.
        </p>
        <table cellpadding="0" cellspacing="0"><tr><td style="background:' . CM_ACCENT . ';border-radius:8px">
          <a href="' . $esc($resetUrl) . '" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none">
            Reset my password →
          </a>
        </td></tr></table>
        <p style="color:rgba(107,66,38,0.5);font-size:12px;margin:20px 0 0;line-height:1.6">
          If you didn\'t request this, no action is needed — your password will remain unchanged.
        </p>
        <p style="color:rgba(107,66,38,0.35);font-size:11px;margin:12px 0 0;word-break:break-all">
          Or copy this URL: ' . $esc($resetUrl) . '
        </p>
      </td></tr>
      <tr><td style="background:' . CM_CREAM . ';padding:20px 40px;text-align:center;border-top:1px solid #ede6db">
        <p style="color:' . CM_MUTED . ';font-size:11px;margin:0">© SoleMate · Durban, South Africa</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';
}