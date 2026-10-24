<?php
// ═══════════════════════════════════════════════════════
//  solemate_cart.php  —  Care-products basket (session based)
//  POST action: add | set | remove | clear   (GET/POST: get)
//  Returns JSON: { ok, count, subtotal, items[] }
// ═══════════════════════════════════════════════════════
header('Content-Type: application/json; charset=utf-8');

// A missing include is a fatal error PHP can't catch, so check first and say what's wrong
foreach (['includes/solemate_database_connection.php', 'includes/solemate_customer_auth.php', 'includes/solemate_care_orders.php'] as $__f) {
    if (!is_file(__DIR__ . '/' . $__f)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => "Missing file: $__f — copy it into the includes folder."]);
        exit;
    }
}

require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';   // starts the session
require_once 'includes/solemate_care_orders.php';

// Any other crash → JSON (never a blank page) and details go to the PHP error log
set_exception_handler(function (\Throwable $e) {
    error_log('[SoleMate cart] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Cart error — see the PHP error log for details.']);
});

function cartJson(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'get';
if ($action !== 'get' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    cartJson(['ok' => false, 'message' => 'POST required.'], 405);
}

try {
    $hasTable = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.care_products') AND type='U'")->fetchColumn();
} catch (PDOException $e) { $hasTable = false; }
if (!$hasTable) cartJson(['ok' => false, 'message' => 'Shop is not set up yet.'], 503);

$cart = $_SESSION['care_cart'] ?? [];
if (!is_array($cart)) $cart = [];

$id  = (int)($_POST['id']  ?? 0);
$qty = (int)($_POST['qty'] ?? 1);

if (in_array($action, ['add', 'set'], true)) {
    $found = careLoadProducts($pdo, [$id]);
    if (!isset($found[$id])) cartJson(['ok' => false, 'message' => 'Product not available.'], 404);

    if ($action === 'add') {
        $cart[$id] = min(CARE_MAX_QTY, ($cart[$id] ?? 0) + max(1, $qty));
    } else {
        if ($qty <= 0) unset($cart[$id]);
        else           $cart[$id] = min(CARE_MAX_QTY, $qty);
    }
} elseif ($action === 'remove') {
    unset($cart[$id]);
} elseif ($action === 'clear') {
    $cart = [];
}

$lines = careCartLines($pdo, $cart);
$cart  = $lines['cart'];
$items = $lines['items']; $count = $lines['count']; $subtotal = $lines['subtotal'];
$_SESSION['care_cart'] = $cart;

cartJson(['ok' => true, 'count' => $count, 'subtotal' => round($subtotal, 2), 'items' => $items]);