<?php
// ═══════════════════════════════════════════════════════
//  process_order.php  —  Handles booking form POST
//  Now routes through checkout → PayFast for payment.
//  Falls back to direct booking if no payment tables exist.
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';
require 'includes/solemate_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: solemate_homepage.php');
    exit;
}

// ── Route through checkout if e-commerce is set up ──────
try {
    $ecomReady = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.payments') AND type='U'")->fetchColumn();
} catch (PDOException $e) { $ecomReady = false; }

if ($ecomReady) {
    // Forward the POST to checkout.php via session and redirect
    session_start();
    // checkout.php reads $_POST directly — just redirect with POST data preserved
    // We bounce through a small forwarding form so POST data is intact
    $fields = [];
    foreach ($_POST as $k => $v) {
        $k = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');
        $v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $fields[] = "<input type='hidden' name='{$k}' value='{$v}'>";
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting…</title>';
    echo '<style>body{font-family:sans-serif;background:#1A0F08;color:#C8A97E;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:1rem}';
    echo '.sp{width:36px;height:36px;border:3px solid rgba(200,169,126,.2);border-top-color:#C8A97E;border-radius:50%;animation:s .7s linear infinite}@keyframes s{to{transform:rotate(360deg)}}</style>';
    echo '</head><body><div class="sp"></div><p>Loading checkout…</p>';
    echo '<form id="f" action="solemate_checkout.php" method="POST">' . implode('', $fields) . '</form>';
    echo '<script>document.getElementById("f").submit();</script></body></html>';
    exit;
}

// ── Legacy path: direct booking (no payment tables) ─────

function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

$name       = clean($_POST['customer_name'] ?? '');
$email      = trim($_POST['email']          ?? '');
$phone      = clean($_POST['phone']         ?? '');
$service_id = (int)($_POST['service_id']    ?? 0);
$shoe       = clean($_POST['shoe_type']     ?? '');
$drop_date  = trim($_POST['drop_date']      ?? '');
$collection = $_POST['collection'] ?? 'drop-off';
$notes      = clean($_POST['notes']         ?? '');
$address    = clean($_POST['delivery_address'] ?? '');

// ── Validation ──────────────────────────────────────────
$errors = [];

if (!$name)                                      $errors[] = 'Your name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'A valid email address is required.';
if ($service_id < 1)                             $errors[] = 'Please select a service.';
if (!$drop_date || strtotime($drop_date) < strtotime('today'))
                                                 $errors[] = 'Please choose a valid future drop-off date.';
if (!in_array($collection, ['drop-off','courier','home']))
                                                 $collection = 'drop-off';
if (in_array($collection, ['courier','home']) && !$address)
                                                 $errors[] = 'Please enter a collection address.';

if (!empty($errors)) {
    $msg = urlencode(implode('|', $errors));
    header("Location: solemate_homepage.php?page=booking&errors={$msg}&service_id={$service_id}");
    exit;
}

// ── Resolve service name for the confirmation email ─────
// Try to fetch the human-readable name from the services table.
// Falls back to "Service #N" if the table doesn't exist yet.
$serviceName = "Service #{$service_id}";
$svcRow      = null;   // ensure always defined
try {
    $svcStmt = $pdo->prepare("SELECT TOP 1 service_name AS name, price, price_from FROM dbo.services WHERE id = ?");
    $svcStmt->execute([$service_id]);
    $svcRow = $svcStmt->fetch();
    if ($svcRow) {
        $serviceName = $svcRow['name'];
    }
} catch (\PDOException $e) {
    // services table not yet migrated — silently use the fallback
}

// ── Generate unique order reference ────────────────────
function generateRef(PDO $pdo): string {
    do {
        $ref = 'SM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $chk = $pdo->prepare("SELECT id FROM dbo.booking WHERE order_ref = ?");
        $chk->execute([$ref]);
    } while ($chk->fetch());
    return $ref;
}

$order_ref = generateRef($pdo);

// ── Insert order ────────────────────────────────────────
// delivery_address is a newer column — guard against DBs not yet migrated
$hasAddressCol = false;
try {
    $hasAddressCol = (bool)$pdo->query("SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name='delivery_address'")->fetchColumn();
} catch (\PDOException $e) {}

$sql = $hasAddressCol
    ? "INSERT INTO dbo.booking
        (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, delivery_address, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO dbo.booking 
        (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
// Any collection method: 'Pending' only while the date is days ahead,
// otherwise (today or earlier) 'Received'.
$status = (strtotime($drop_date) > strtotime('today'))
    ? 'Pending'
    : 'Received';

$params = $hasAddressCol
    ? [$order_ref, $name, $email, $phone, $service_id, $shoe, $drop_date, $collection, $address, $notes, $status]
    : [$order_ref, $name, $email, $phone, $service_id, $shoe, $drop_date, $collection, $notes, $status];

$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {

    // ── Send confirmation email ─────────────────────────
    // Runs after the DB insert so the order is guaranteed to
    // exist before the customer tries to track it.
    $mailSent = sendBookingConfirmation([
        'order_ref'  => $order_ref,
        'name'       => $name,
        'email'      => $email,
        'service'    => $serviceName,
        'shoe'       => $shoe,
        'drop_date'  => $drop_date ? date('d M Y', strtotime($drop_date)) : 'TBC',
        'collection' => ucfirst($collection),
        'delivery_address' => $address,
        'price'      => $svcRow ? (float)($svcRow['price'] ?? 0) : null,
        'price_from' => $svcRow ? (bool)($svcRow['price_from'] ?? false) : false,
    ]);

    if (!$mailSent) {
        error_log("Email failed for order: " . $order_ref);
    }
    // A mail failure is intentionally non-fatal: the order is
    // already saved, so we redirect to the tracker regardless.
    // For stricter error surfacing, capture the return value:
    //   $mailSent = sendBookingConfirmation([...]);
    //   if (!$mailSent) { /* log or surface warning */ }

    header("Location: solemate_homepage.php?page=tracker&oid=" . urlencode($order_ref) . "&new=1");
    exit;

} else {
    header("Location: solemate_homepage.php?page=booking&errors=" . urlencode('Database error. Please try again.'));
    exit;
}