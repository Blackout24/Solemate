<?php
// ═══════════════════════════════════════════════════════
//  solemate_payfast.php  —  PayFast Integration Helper
//
//  .env keys required:
//    PAYFAST_MERCHANT_ID   (live: your merchant ID)
//    PAYFAST_MERCHANT_KEY  (live: your merchant key)
//    PAYFAST_PASSPHRASE    (live: your passphrase, if set)
//    PAYFAST_SANDBOX       true | false
//    APP_URL               https://yourdomain.co.za/SoleMate
// ═══════════════════════════════════════════════════════

if (!function_exists('loadEnv')) {
    require_once __DIR__ . '/solemate_mailer.php';
}

// ── Config ───────────────────────────────────────────────
function pfConfig(): array {
    $sandbox = filter_var(getenv('PAYFAST_SANDBOX') ?: 'true', FILTER_VALIDATE_BOOLEAN);
    return [
        'merchant_id'  => getenv('PAYFAST_MERCHANT_ID')  ?: '10050502',
        'merchant_key' => getenv('PAYFAST_MERCHANT_KEY') ?: '3mhj8gsaqzlzq',
        'passphrase'   => getenv('PAYFAST_PASSPHRASE')   ?: ($sandbox ? 'jt7NOE43FZPn' : ''),
        'sandbox'      => $sandbox,
        'url'          => $sandbox
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process',
        'api_url'      => $sandbox
            ? 'https://api.sandbox.payfast.co.za/ping'
            : 'https://api.payfast.co.za/ping',
    ];
}

// ── Signature generation ─────────────────────────────────
function pfGenerateSignature(array $data, string $passPhrase = ''): string {
    unset($data['signature']);
    $out = '';
    foreach ($data as $k => $v) {
        if ($v !== '') $out .= $k . '=' . urlencode(trim((string)$v)) . '&';
    }
    $out = rtrim($out, '&');
    if ($passPhrase !== '') $out .= '&passphrase=' . urlencode($passPhrase);
    return md5($out);
}

// ── Build payment data array ─────────────────────────────
require_once __DIR__ . '/solemate_url.php';

function pfBuildPaymentData(array $order): array {
    $cfg = pfConfig();
    $base = appBaseUrl();

    $data = [
        'merchant_id'       => $cfg['merchant_id'],
        'merchant_key'      => $cfg['merchant_key'],
        'return_url'        => $base . '/solemate_payment_return.php?cart_ref=' . urlencode($order['cart_ref']),
        'cancel_url'        => $base . '/solemate_payment_cancel.php?cart_ref='  . urlencode($order['cart_ref']),
        'notify_url'        => $base . '/solemate_payment_notify.php',
        'name_first'        => $order['first_name'],
        'name_last'         => $order['last_name'],
        'email_address'     => $order['email'],
        'm_payment_id'      => $order['cart_ref'],
        'amount'            => number_format((float)$order['amount'], 2, '.', ''),
        'item_name'         => substr('SoleMate — ' . $order['item_name'], 0, 100),
        'item_description'  => substr($order['item_description'] ?? '', 0, 255),
        'custom_str1'       => $order['cart_ref'],
    ];

    $data['signature'] = pfGenerateSignature($data, $cfg['passphrase']);
    return $data;
}

// ── Validate ITN ─────────────────────────────────────────
// Called inside solemate_payment_notify.php
function pfValidateITN(array $post, float $expectedAmount): array {
    $cfg = pfConfig();

    // 1. IP whitelist
    $validIps = [
        '41.74.179.192/27',
        '196.33.227.224/27',
        '197.97.145.128/27',
        // Sandbox
        '197.97.145.144',
        '41.74.179.193',
        '41.74.179.194',
    ];
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$cfg['sandbox'] && !pfIpInRanges($remoteIp, $validIps)) {
        return ['ok' => false, 'reason' => "Invalid source IP: {$remoteIp}"];
    }

    // 2. Signature check
    $sig = pfGenerateSignature($post, $cfg['passphrase']);
    if ($sig !== ($post['signature'] ?? '')) {
        return ['ok' => false, 'reason' => 'Signature mismatch'];
    }

    // 3. Payment status
    if (($post['payment_status'] ?? '') !== 'COMPLETE') {
        return ['ok' => false, 'reason' => 'Payment not complete: ' . ($post['payment_status'] ?? '')];
    }

    // 4. Amount validation (within R0.01 tolerance)
    $pfAmount = (float)($post['amount_gross'] ?? 0);
    if (abs($pfAmount - $expectedAmount) > 0.01) {
        return ['ok' => false, 'reason' => "Amount mismatch: expected {$expectedAmount}, got {$pfAmount}"];
    }

    // 5. (Optional) PayFast server-side confirmation via API
    // Skipped here — signature + IP + amount is sufficient for most stores.

    return ['ok' => true];
}

// ── CIDR range check ─────────────────────────────────────
function pfIpInRanges(string $ip, array $ranges): bool {
    foreach ($ranges as $range) {
        if (strpos($range, '/') !== false) {
            [$subnet, $bits] = explode('/', $range);
            $mask = ~((1 << (32 - (int)$bits)) - 1);
            if ((ip2long($ip) & $mask) === (ip2long($subnet) & $mask)) return true;
        } else {
            if ($ip === $range) return true;
        }
    }
    return false;
}

// ── Cart reference generator ─────────────────────────────
function generateCartRef(PDO $pdo): string {
    do {
        $ref = 'SM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $chk = $pdo->prepare("SELECT id FROM dbo.payments WHERE cart_ref=?");
        $chk->execute([$ref]);
    } while ($chk->fetch());
    return $ref;
}

// ── Booking ref generator ────────────────────────────────
function generateOrderRef(PDO $pdo): string {
    do {
        $ref = 'SM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $chk = $pdo->prepare("SELECT id FROM dbo.booking WHERE order_ref=?");
        $chk->execute([$ref]);
    } while ($chk->fetch());
    return $ref;
}