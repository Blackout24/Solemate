<?php
// ═══════════════════════════════════════════════════════
//  solemate_cart_check.php  —  one-off diagnostic for the care shop
//  Open it in the browser, read the ✅/❌ list, then DELETE this file.
//  Read-only: it never changes the database or your cart.
// ═══════════════════════════════════════════════════════
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

$rows = [];
// $hint is shown only when the check fails; $info only when it passes
function chk(string $label, bool $ok, string $hint = '', string $info = ''): void {
    global $rows; $rows[] = [$ok, $label, $ok ? $info : $hint];
}
$base = __DIR__;

// 1. Files ------------------------------------------------
$need = [
    'solemate_cart.php', 'solemate_homepage.php',
    'includes/solemate_care_orders.php', 'includes/solemate_ecommerce.php',
    'includes/solemate_database_connection.php', 'includes/solemate_customer_auth.php',
];
$filesOk = true;
foreach ($need as $f) {
    $ok = is_file("$base/$f");
    if (!$ok) $filesOk = false;
    chk("File exists: $f", $ok, $ok ? '' : 'Not found — upload it to this folder.');
}
if (is_file("$base/solemate_homepage.php")) {
    $h = file_get_contents("$base/solemate_homepage.php");
    chk('solemate_homepage.php is the new version (has Add to cart code)', strpos($h, 'addToCart') !== false, 'Replace it with the latest solemate_homepage.php.');
}
if (is_file("$base/includes/solemate_ecommerce.php")) {
    $e = file_get_contents("$base/includes/solemate_ecommerce.php");
    chk('includes/solemate_ecommerce.php is the new version', strpos($e, 'solemate_care_orders.php') !== false, 'Replace it with the latest includes/solemate_ecommerce.php.');
}
chk('PHP version', true, '', PHP_VERSION);
chk('pdo_sqlsrv extension loaded', extension_loaded('pdo_sqlsrv'), 'Needed for SQL Server.');

// 2. Database -----------------------------------------------
$pdo = null;
if ($filesOk) {
    try {
        require "$base/includes/solemate_database_connection.php";
        chk('Database connection', isset($pdo) && $pdo instanceof PDO);
    } catch (Throwable $t) {
        chk('Database connection', false, $t->getMessage());
        $pdo = null;
    }
}
if ($pdo) {
    require_once "$base/includes/solemate_care_orders.php";
    foreach (['care_products', 'care_orders', 'care_order_items'] as $t) {
        chk("Table dbo.$t exists", careTableExists($pdo, $t), 'Run the matching SQL migration in SSMS (against shoe_repair_db).');
    }
    if (careTableExists($pdo, 'care_products')) {
        try {
            $n = (int)$pdo->query("SELECT COUNT(*) FROM dbo.care_products WHERE is_active=1")->fetchColumn();
            chk('Active care products', $n > 0, 'None found — add or unhide some in Admin → Care Products.', "$n active product(s)");
            $first = $pdo->query("SELECT TOP 1 id, name, price FROM dbo.care_products WHERE is_active=1 ORDER BY sort_order, id")->fetch();
            if ($first) {
                $lines = careCartLines($pdo, [(int)$first['id'] => 1]);
                chk('Pricing test (1 × ' . $first['name'] . ')', count($lines['items']) === 1, 'Product could not be priced.', 'Subtotal R' . number_format($lines['subtotal'], 2));
                $json = json_encode($lines, JSON_UNESCAPED_UNICODE);
                chk('Cart response encodes to JSON', $json !== false, $json === false ? json_last_error_msg() . ' — a product name/icon has bad characters.' : '');
            }
        } catch (Throwable $t) {
            chk('Reading dbo.care_products', false, $t->getMessage());
        }
    }
}

// 3. Session ------------------------------------------------
if (session_status() === PHP_SESSION_NONE) @session_start();
chk('PHP session works', session_status() === PHP_SESSION_ACTIVE, 'Sessions are not working on this server.', 'Session id: ' . substr(session_id(), 0, 6) . '…');
chk('Items currently in your cart', true, '', (string)array_sum($_SESSION['care_cart'] ?? []));

$bad = count(array_filter($rows, fn($r) => !$r[0]));
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Care shop check</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;line-height:1.5}
.r{padding:.5rem .75rem;border-bottom:1px solid #ddd}.ok{color:#1a7f37}.no{color:#b42318;font-weight:600}.d{color:#666;font-size:13px}
.s{padding:1rem;border-radius:8px;margin:1rem 0;font-weight:600}.g{background:#e6f4ea;color:#1a7f37}.b{background:#fde8e6;color:#b42318}</style></head><body>
<h2>Care shop check</h2>
<div class="s <?= $bad ? 'b' : 'g' ?>"><?= $bad ? "$bad problem(s) found — fix the ❌ items below." : 'Everything looks fine on the server side.' ?></div>
<?php foreach ($rows as [$ok, $label, $detail]): ?>
<div class="r"><span class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✅' : '❌' ?> <?= htmlspecialchars($label) ?></span>
<?php if ($detail !== ''): ?><div class="d"><?= htmlspecialchars($detail) ?></div><?php endif; ?></div>
<?php endforeach; ?>
<p class="d" style="margin-top:1.5rem">Delete <code>solemate_cart_check.php</code> when you're done.</p>
</body></html>
