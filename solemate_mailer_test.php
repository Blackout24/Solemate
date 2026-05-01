<?php
// ═══════════════════════════════════════════════════════
//  mailer_test.php — Test SMTP connection
//  ⚠️ DELETE THIS FILE after testing!
// ═══════════════════════════════════════════════════════
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load env vars
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($key) . '=' . trim($value));
        $_ENV[trim($key)] = trim($value);
    }
}

echo "<pre>";
echo "MAIL_HOST: "       . (getenv('MAIL_HOST')       ?: '❌ NOT SET') . "\n";
echo "MAIL_PORT: "       . (getenv('MAIL_PORT')       ?: '❌ NOT SET') . "\n";
echo "MAIL_USERNAME: "   . (getenv('MAIL_USERNAME')   ?: '❌ NOT SET') . "\n";
echo "MAIL_PASSWORD: "   . (getenv('MAIL_PASSWORD')   ? '✅ SET' : '❌ NOT SET') . "\n";
echo "MAIL_ENCRYPTION: " . (getenv('MAIL_ENCRYPTION') ?: '❌ NOT SET') . "\n\n";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug  = 2; // Full debug output
    $mail->isSMTP();
    $mail->Host       = getenv('MAIL_HOST')       ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME')   ?: '';
    $mail->Password   = getenv('MAIL_PASSWORD')   ?: '';
    $mail->SMTPSecure = getenv('MAIL_ENCRYPTION') ?: 'tls';
    $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);

    $mail->setFrom(getenv('MAIL_USERNAME'), 'SoleMate Test');
    $mail->addAddress(getenv('MAIL_USERNAME')); // Send test to yourself

    $mail->isHTML(false);
    $mail->Subject = 'SoleMate SMTP Test';
    $mail->Body    = 'If you see this, SMTP is working!';

    $mail->send();
    echo "\n✅ Email sent successfully!";
} catch (Exception $e) {
    echo "\n❌ Email failed: " . $mail->ErrorInfo;
}
echo "</pre>";
