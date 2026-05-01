<?php
// ═══════════════════════════════════════════════════════
//  solemate_mailer.php  —  Booking Confirmation Mailer
//
//  Usage:
//    require 'includes/solemate_mailer.php';
//    $ok = sendBookingConfirmation([
//        'order_ref'  => 'SM-20260423-AB12',
//        'name'       => 'Siya Khoza',
//        'email'      => 'siya@example.com',
//        'service'    => 'Deep Clean',
//        'shoe'       => 'Nike Air Max 90',
//        'drop_date'  => '2026-04-28',
//        'collection' => 'drop-off',
//    ]);
//
//  For production SMTP swap the mail() call at the bottom
//  for PHPMailer/Symfony Mailer (see comment block there).
// ═══════════════════════════════════════════════════════

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Load PHPMailer directly — no Composer needed
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// ─────────────────────────────────────────────────────────────
//  loadEnv() — Parse a .env file and populate $_ENV / getenv()
//
//  Call once at bootstrap (e.g. top of index.php or here).
//  Skips lines that are blank or start with #.
//  Never overwrites a variable already set in the real env
//  (so server-level env vars always take precedence).
// ─────────────────────────────────────────────────────────────
function loadEnv(string $path): void {
    if (!is_readable($path)) {
        // .env is optional on servers that inject env vars natively
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue; // skip comments and blank lines
        }
        // Split on the FIRST '=' only so passwords may contain '='
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value);
        // Strip optional surrounding quotes
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
            $value = $m[2];
        }
        // Don't overwrite variables already set at the OS level
        if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load credentials from the .env file that sits one level above /includes/
loadEnv(__DIR__ . '/../.env');

// ─────────────────────────────────────────────────────────────
//  smtpConfigure() — Apply SMTP credentials to a PHPMailer
//  instance using values read from the environment.
// ─────────────────────────────────────────────────────────────
function smtpConfigure(PHPMailer $mail): void {
    $mail->isSMTP();
    $mail->Host       = getenv('MAIL_HOST')       ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME')   ?: '';
    $mail->Password   = getenv('MAIL_PASSWORD')   ?: '';
    $mail->SMTPSecure = getenv('MAIL_ENCRYPTION') ?: 'tls';
    $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);
}

function sendBookingConfirmation($data) {
    $mail = new PHPMailer(true);

    try {
        // 🔐 SMTP SETTINGS — credentials loaded from .env
        smtpConfigure($mail);

        // 👤 Sender & Recipient
        $mail->setFrom(getenv('MAIL_USERNAME'), getenv('MAIL_FROM_NAME') ?: 'SoleMate');
        $mail->addAddress($data['email'], $data['name']);

        // 🔗 Tracker URL
        $trackerUrl = 'https://solemate-production-0043.up.railway.app/solemate_homepage.php?page=tracker&oid=' . urlencode($data['order_ref']);

        // 📧 Email Content — use the full HTML template
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Booking Confirmed — ' . $data['order_ref'] . ' · SoleMate';
        $mail->Body    = buildConfirmationHtml(
            $data['name'],
            $data['order_ref'],
            $data['service']    ?? 'Shoe service',
            $data['shoe']       ?? 'Not specified',
            $data['drop_date']  ?? 'TBC',
            $data['collection'] ?? 'drop-off',
            $trackerUrl,
            $data['price']      ?? null,
            $data['price_from'] ?? false
        );
        $mail->AltBody = buildConfirmationText(
            $data['name'],
            $data['order_ref'],
            $data['service']    ?? 'Shoe service',
            $data['shoe']       ?? 'Not specified',
            $data['drop_date']  ?? 'TBC',
            $data['collection'] ?? 'drop-off',
            $trackerUrl,
            $data['price']      ?? null,
            $data['price_from'] ?? false
        );

        return $mail->send();

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}


// ── HTML email template ──────────────────────────────────────────────────────
function buildConfirmationHtml(
    string $name,
    string $ref,
    string $service,
    string $shoe,
    string $dropDate,
    string $collection,
    string $trackerUrl,
    ?float $price = null,
    bool   $priceFrom = false
): string {
    $year = date('Y');

    // Build receipt block (injected into heredoc below)
    if ($price !== null) {
        $noteText = $priceFrom ? 'Final price confirmed after technician assessment.' : 'Fixed price — payable on collection.';
        $priceLabel  = ($priceFrom ? 'From ' : '') . 'R' . number_format($price, 2);
        $receiptHtml = <<<RECEIPT

        <!-- Receipt -->
        <tr>
          <td style="padding:0 40px 28px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#1A0F08;border-radius:10px;overflow:hidden;">
              <tr>
                <td style="padding:14px 20px 4px;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#8B7355;">Receipt</span>
                </td>
              </tr>
              <tr>
                <td style="padding:4px 20px;">
                  <table width="100%" cellpadding="0" cellspacing="0" border="0"
                         style="border-top:1px solid rgba(200,169,126,0.15);">
                    <tr>
                      <td style="font-size:13px;color:rgba(200,169,126,0.75);padding:10px 0 6px;">$priceLabel service</td>
                      <td style="font-size:13px;color:rgba(200,169,126,0.75);text-align:right;padding:10px 0 6px;">1 pair</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="border-top:1px solid rgba(200,169,126,0.15);padding:10px 0 14px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                          <tr>
                            <td style="font-size:15px;font-weight:700;color:#C8A97E;">Total</td>
                            <td style="font-size:15px;font-weight:700;color:#C8A97E;text-align:right;">$priceLabel</td>
                          </tr>
                        </table>
                        <p style="margin:6px 0 0;font-size:11px;color:#5A4030;">
                          $noteText
                        </p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
RECEIPT;
    } else {
        $receiptHtml = '';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Booking Confirmed — SoleMate</title>
</head>
<body style="margin:0;padding:0;background:#F4F0EA;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#1A0F08;">

<!-- Wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F0EA;padding:40px 16px;">
  <tr>
    <td align="center">

      <!-- Card -->
      <table width="100%" cellpadding="0" cellspacing="0" border="0"
             style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header band -->
        <tr>
          <td style="background:#1A0F08;padding:32px 40px;text-align:center;">
            <p style="margin:0 0 6px;font-size:24px;font-weight:700;color:#C8A97E;letter-spacing:-0.5px;">
              Sole<span style="color:#D4561A;">Mate</span>
            </p>
            <p style="margin:0;font-size:12px;color:#8B7355;letter-spacing:0.5px;text-transform:uppercase;">
              Shoe Cleaning &amp; Repair · Durban
            </p>
          </td>
        </tr>

        <!-- Confirmation badge -->
        <tr>
          <td style="background:#FFF8F0;padding:28px 40px 20px;text-align:center;border-bottom:1px solid #F0E8DC;">
            <p style="margin:0 0 8px;font-size:32px;">✅</p>
            <h1 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1A0F08;">
              Booking confirmed, {$name}!
            </h1>
            <p style="margin:0;font-size:14px;color:#8B7355;line-height:1.6;">
              Your shoes are in good hands. Here is your tracking reference:
            </p>
          </td>
        </tr>

        <!-- Tracking reference -->
        <tr>
          <td style="padding:24px 40px 8px;text-align:center;">
            <p style="margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#8B7355;">
              Order reference
            </p>
            <p style="margin:0;display:inline-block;background:#1A0F08;color:#C8A97E;
                      font-family:monospace;font-size:22px;font-weight:700;letter-spacing:2px;
                      padding:12px 28px;border-radius:10px;">
              {$ref}
            </p>
            <p style="margin:10px 0 0;font-size:12px;color:#8B7355;">
              Keep this reference to track your order at any time.
            </p>
          </td>
        </tr>

        <!-- Order details table -->
        <tr>
          <td style="padding:24px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#FAF7F2;border-radius:10px;overflow:hidden;border:1px solid #EDE4D8;">
              <tr>
                <td style="padding:14px 18px;border-bottom:1px solid #EDE4D8;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Service</span><br>
                  <span style="font-size:14px;font-weight:600;color:#1A0F08;">{$service}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:14px 18px;border-bottom:1px solid #EDE4D8;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Shoe / item</span><br>
                  <span style="font-size:14px;font-weight:600;color:#1A0F08;">{$shoe}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:14px 18px;border-bottom:1px solid #EDE4D8;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Drop-off date</span><br>
                  <span style="font-size:14px;font-weight:600;color:#1A0F08;">{$dropDate}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:14px 18px;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Collection method</span><br>
                  <span style="font-size:14px;font-weight:600;color:#1A0F08;">{$collection}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

{$receiptHtml}
        <!-- CTA button -->
        <tr>
          <td style="padding:0 40px 32px;text-align:center;">
            <a href="{$trackerUrl}"
               style="display:inline-block;background:#D4561A;color:#FFFFFF;text-decoration:none;
                      font-size:14px;font-weight:600;padding:13px 32px;border-radius:9px;
                      letter-spacing:0.2px;">
              Track my order →
            </a>
            <p style="margin:14px 0 0;font-size:12px;color:#8B7355;">
              Or visit <a href="{$trackerUrl}" style="color:#D4561A;text-decoration:none;">solemate.co.za</a>
              and enter your reference manually.
            </p>
          </td>
        </tr>

        <!-- What happens next -->
        <tr>
          <td style="padding:0 40px 32px;">
            <p style="margin:0 0 12px;font-size:13px;font-weight:600;color:#6B4226;
                      text-transform:uppercase;letter-spacing:0.4px;">What happens next</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td width="28" valign="top" style="padding-top:2px;font-size:16px;">📥</td>
                <td style="padding-bottom:10px;font-size:13px;color:#4A3020;line-height:1.55;">
                  <strong>Received</strong> — We have logged your booking and will confirm drop-off arrangements.
                </td>
              </tr>
              <tr>
                <td width="28" valign="top" style="padding-top:2px;font-size:16px;">🔍</td>
                <td style="padding-bottom:10px;font-size:13px;color:#4A3020;line-height:1.55;">
                  <strong>Assessment</strong> — Our technicians inspect your shoes and note any extra work needed.
                </td>
              </tr>
              <tr>
                <td width="28" valign="top" style="padding-top:2px;font-size:16px;">🛠️</td>
                <td style="padding-bottom:10px;font-size:13px;color:#4A3020;line-height:1.55;">
                  <strong>In service</strong> — Your shoes are being cleaned or repaired with care.
                </td>
              </tr>
              <tr>
                <td width="28" valign="top" style="padding-top:2px;font-size:16px;">✅</td>
                <td style="font-size:13px;color:#4A3020;line-height:1.55;">
                  <strong>Ready</strong> — We will notify you by email and SMS when your shoes are ready.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Contact -->
        <tr>
          <td style="background:#FAF7F2;padding:20px 40px;border-top:1px solid #EDE4D8;text-align:center;">
            <p style="margin:0;font-size:12px;color:#8B7355;line-height:1.8;">
              Questions? Reply to this email or call us on <strong style="color:#6B4226;">081 234 5678</strong><br>
              📍 12 Florida Road, Berea, Durban · Mon–Sat 8 am–5 pm
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#1A0F08;padding:16px 40px;text-align:center;">
            <p style="margin:0;font-size:11px;color:#5A4030;line-height:1.6;">
              © {$year} SoleMate Shoe Care, Durban. All rights reserved.<br>
              You received this email because you placed a booking on our website.
            </p>
          </td>
        </tr>

      </table>
      <!-- /Card -->

    </td>
  </tr>
</table>

</body>
</html>
HTML;
}


// ── Plain-text fallback ───────────────────────────────────────────────────────
function buildConfirmationText(
    string $name,
    string $ref,
    string $service,
    string $shoe,
    string $dropDate,
    string $collection,
    string $trackerUrl,
    ?float $price = null,
    bool   $priceFrom = false
): string {
    $year = date('Y');
    $receiptText = '';
    if ($price !== null) {
        $priceLabel   = ($priceFrom ? 'From ' : '') . 'R' . number_format($price, 2);
        $priceNote    = $priceFrom ? 'Final price confirmed after technician assessment.' : 'Fixed price — payable on collection.';
        $receiptText  = "\nRECEIPT\n───────\nService : {$service}\nTotal   : {$priceLabel}\n{$priceNote}\n";
    }
    return <<<TEXT
SOLEMATE SHOE CARE — BOOKING CONFIRMED
=======================================

Hi {$name},

Your booking is confirmed. Here is your tracking reference:

  {$ref}

Keep this reference to track your order online.

ORDER DETAILS
─────────────
Service          : {$service}
Shoe / item      : {$shoe}
Drop-off date    : {$dropDate}
Collection method: {$collection}
{$receiptText}
Track your order online:
{$trackerUrl}

WHAT HAPPENS NEXT
─────────────────
1. Received     — Booking logged, drop-off confirmed.
2. Assessment   — Technicians inspect your shoes.
3. In service   — Cleaning / repair underway.
4. Ready        — We will email and SMS you when done.

Questions? Reply to this email or call 081 234 5678.
12 Florida Road, Berea, Durban. Mon–Sat 8 am–5 pm.

© {$year} SoleMate Shoe Care, Durban.
TEXT;
}


// ── Status update email ───────────────────────────────────────────────────────
/**
 * Send an order status update notification to the customer.
 *
 * @param  array $data  Keys: order_ref, name, email, service, shoe, status, tracker_url
 * @return bool
 */
function sendStatusUpdateEmail(array $data): bool {
    $mail = new PHPMailer(true);
    try {
        // 🔐 SMTP SETTINGS — credentials loaded from .env
        smtpConfigure($mail);

        $mail->setFrom(getenv('MAIL_USERNAME'), getenv('MAIL_FROM_NAME') ?: 'SoleMate');
        $mail->addAddress($data['email'], $data['name']);

        $trackerUrl = $data['tracker_url'] ?? ('https://solemate-production-0043.up.railway.app/solemate_homepage.php?page=tracker&oid=' . urlencode($data['order_ref']));

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Order Update — ' . $data['order_ref'] . ' · SoleMate';
        $mail->Body    = buildStatusUpdateHtml($data, $trackerUrl);
        $mail->AltBody = buildStatusUpdateText($data, $trackerUrl);

        return $mail->send();
    } catch (Exception $e) {
        error_log('Status mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function buildStatusUpdateHtml(array $d, string $trackerUrl): string {
    $year    = date('Y');
    $name    = htmlspecialchars($d['name']      ?? '', ENT_QUOTES, 'UTF-8');
    $ref     = htmlspecialchars($d['order_ref'] ?? '', ENT_QUOTES, 'UTF-8');
    $service = htmlspecialchars($d['service']   ?? '', ENT_QUOTES, 'UTF-8');
    $shoe    = htmlspecialchars($d['shoe']       ?? '', ENT_QUOTES, 'UTF-8');
    $status  = htmlspecialchars($d['status']    ?? '', ENT_QUOTES, 'UTF-8');

    // Status-specific display config
    $statusConfig = [
        'Pending'       => ['emoji' => '⏳', 'color' => '#A0A0A0', 'msg' => 'Your booking is logged and awaiting drop-off confirmation.'],
        'Received'      => ['emoji' => '📥', 'color' => '#8B7355', 'msg' => 'We have received your shoes and they are in our care.'],
        'Assessment'    => ['emoji' => '🔍', 'color' => '#C8A97E', 'msg' => 'Our technicians are inspecting your shoes and noting the work required.'],
        'In service'    => ['emoji' => '🛠️', 'color' => '#D4561A', 'msg' => 'Your shoes are actively being cleaned or repaired — nearly there!'],
        'Quality check' => ['emoji' => '✔️', 'color' => '#6B4226', 'msg' => 'Final quality inspection underway to make sure everything is perfect.'],
        'Ready'         => ['emoji' => '✅', 'color' => '#2D6A4F', 'msg' => 'Your shoes are ready for collection! Come pick them up at your convenience.'],
    ];

    $cfg   = $statusConfig[$d['status'] ?? ''] ?? ['emoji' => '📋', 'color' => '#8B7355', 'msg' => 'Your order status has been updated.'];
    $emoji = $cfg['emoji'];
    $color = $cfg['color'];
    $msg   = $cfg['msg'];

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Update — SoleMate</title>
</head>
<body style="margin:0;padding:0;background:#F4F0EA;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#1A0F08;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F0EA;padding:40px 16px;">
  <tr>
    <td align="center">
      <table width="100%" cellpadding="0" cellspacing="0" border="0"
             style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:#1A0F08;padding:32px 40px;text-align:center;">
            <p style="margin:0 0 6px;font-size:24px;font-weight:700;color:#C8A97E;letter-spacing:-0.5px;">
              Sole<span style="color:#D4561A;">Mate</span>
            </p>
            <p style="margin:0;font-size:12px;color:#8B7355;letter-spacing:0.5px;text-transform:uppercase;">
              Shoe Cleaning &amp; Repair · Durban
            </p>
          </td>
        </tr>

        <!-- Status badge -->
        <tr>
          <td style="background:#FFF8F0;padding:28px 40px 20px;text-align:center;border-bottom:1px solid #F0E8DC;">
            <p style="margin:0 0 8px;font-size:36px;">{$emoji}</p>
            <h1 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1A0F08;">
              Status updated, {$name}!
            </h1>
            <p style="margin:0;font-size:14px;color:#8B7355;line-height:1.6;">{$msg}</p>
          </td>
        </tr>

        <!-- New status pill -->
        <tr>
          <td style="padding:24px 40px 8px;text-align:center;">
            <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#8B7355;">Current status</p>
            <span style="display:inline-block;background:{$color};color:#fff;font-size:15px;font-weight:600;
                         padding:10px 28px;border-radius:20px;letter-spacing:0.3px;">
              {$status}
            </span>
          </td>
        </tr>

        <!-- Order summary -->
        <tr>
          <td style="padding:20px 40px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#FAF7F2;border-radius:10px;overflow:hidden;border:1px solid #EDE4D8;">
              <tr>
                <td style="padding:12px 18px;border-bottom:1px solid #EDE4D8;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Reference</span><br>
                  <span style="font-family:monospace;font-size:14px;font-weight:700;color:#D4561A;">{$ref}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:12px 18px;border-bottom:1px solid #EDE4D8;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Service</span><br>
                  <span style="font-size:14px;font-weight:600;color:#1A0F08;">{$service}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:12px 18px;">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#8B7355;">Shoe / item</span><br>
                  <span style="font-size:14px;font-weight:600;color:#1A0F08;">{$shoe}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CTA -->
        <tr>
          <td style="padding:0 40px 32px;text-align:center;">
            <a href="{$trackerUrl}"
               style="display:inline-block;background:#D4561A;color:#FFFFFF;text-decoration:none;
                      font-size:14px;font-weight:600;padding:13px 32px;border-radius:9px;">
              Track my order →
            </a>
          </td>
        </tr>

        <!-- Contact -->
        <tr>
          <td style="background:#FAF7F2;padding:20px 40px;border-top:1px solid #EDE4D8;text-align:center;">
            <p style="margin:0;font-size:12px;color:#8B7355;line-height:1.8;">
              Questions? Reply to this email or call <strong style="color:#6B4226;">081 234 5678</strong><br>
              📍 12 Florida Road, Berea, Durban · Mon–Sat 8 am–5 pm
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#1A0F08;padding:16px 40px;text-align:center;">
            <p style="margin:0;font-size:11px;color:#5A4030;line-height:1.6;">
              © {$year} SoleMate Shoe Care, Durban. All rights reserved.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
}

function buildStatusUpdateText(array $d, string $trackerUrl): string {
    $year   = date('Y');
    $name   = $d['name']      ?? '';
    $ref    = $d['order_ref'] ?? '';
    $status = $d['status']    ?? '';
    $svc    = $d['service']   ?? '';
    $shoe   = $d['shoe']      ?? '';
    return <<<TEXT
SOLEMATE SHOE CARE — ORDER STATUS UPDATE
=========================================

Hi {$name},

Your order status has been updated.

  Order reference : {$ref}
  Service         : {$svc}
  Shoe / item     : {$shoe}
  New status      : {$status}

Track your order online:
{$trackerUrl}

Questions? Reply to this email or call 081 234 5678.
12 Florida Road, Berea, Durban. Mon–Sat 8 am–5 pm.

© {$year} SoleMate Shoe Care, Durban.
TEXT;
}
