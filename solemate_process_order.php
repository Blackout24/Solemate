<?php
// ═══════════════════════════════════════════════════════
//  process_order.php  —  Handles booking form POST
//  Inserts into orders table, emails confirmation,
//  then redirects to the tracker page.
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';
require 'includes/solemate_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: solemate_homepage.php');
    exit;
}

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

// ── Validation ──────────────────────────────────────────
$errors = [];

if (!$name)                                      $errors[] = 'Your name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'A valid email address is required.';
if ($service_id < 1)                             $errors[] = 'Please select a service.';
if (!$drop_date || strtotime($drop_date) < strtotime('today'))
                                                 $errors[] = 'Please choose a valid future drop-off date.';
if (!in_array($collection, ['drop-off','courier','home']))
                                                 $collection = 'drop-off';

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
        $chk = $pdo->prepare("SELECT id FROM dbo.orders WHERE order_ref = ?");
        $chk->execute([$ref]);
    } while ($chk->fetch());
    return $ref;
}

$order_ref = generateRef($pdo);

// ── Insert order ────────────────────────────────────────
$sql = "INSERT INTO dbo.orders 
        (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$status = in_array($collection, ['courier','home']) ? 'Pending' : 'Received';

$stmt = $pdo->prepare($sql);

if ($stmt->execute([$order_ref, $name, $email, $phone, $service_id, $shoe, $drop_date, $collection, $notes, $status])) {

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