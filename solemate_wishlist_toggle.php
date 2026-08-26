<?php
// ═══════════════════════════════════════════════════════
//  solemate_wishlist_toggle.php  —  Wishlist Add / Remove
//
//  POST params:
//    service_id  : int
//    redirect    : optional URL to redirect to after toggle
//                  (used for non-JS fallback from homepage)
//
//  Returns JSON when no redirect:
//    { ok: bool, in_wishlist: bool, message: string }
//
//  Called by:
//    - Homepage service card ♥ buttons (fetch / AJAX)
//    - Dashboard wishlist tab "Remove" button (form POST + redirect)
// ═══════════════════════════════════════════════════════
require 'includes/solemate_customer_auth.php';

$wantsJson = (
    empty($_POST['redirect']) &&
    (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
     !empty($_SERVER['HTTP_X_REQUESTED_WITH']))
);

if (!isCustomerLoggedIn()) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Not logged in.', 'login_required' => true]);
        exit;
    }
    $back = urlencode($_SERVER['HTTP_REFERER'] ?? 'solemate_customer_dashboard.php?tab=wishlist');
    header('Location: solemate_customer_login.php?redirect=' . $back);
    exit;
}

require 'includes/solemate_database_connection.php';

$custId    = getCustomerId();
$serviceId = (int)($_POST['service_id'] ?? 0);
$redirect  = trim($_POST['redirect'] ?? '');

if ($wantsJson) header('Content-Type: application/json');

if (!$serviceId) {
    $msg = ['ok' => false, 'message' => 'Missing service_id.'];
    $wantsJson ? print(json_encode($msg)) : header('Location: ' . ($redirect ?: 'solemate_homepage.php'));
    exit;
}

// Table guard
try {
    $exists = $pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.customer_wishlist') AND type='U'")->fetchColumn();
    if (!$exists) {
        $msg = ['ok' => false, 'message' => 'Wishlist table not found. Run migration SQL.'];
        $wantsJson ? print(json_encode($msg)) : header('Location: ' . ($redirect ?: 'solemate_homepage.php'));
        exit;
    }
} catch (PDOException $e) {
    $msg = ['ok' => false, 'message' => 'DB error.'];
    $wantsJson ? print(json_encode($msg)) : header('Location: ' . ($redirect ?: 'solemate_homepage.php'));
    exit;
}

// Check current state
$check = $pdo->prepare("SELECT id FROM dbo.customer_wishlist WHERE customer_id=? AND service_id=?");
$check->execute([$custId, $serviceId]);
$alreadyIn = (bool)$check->fetch();

if ($alreadyIn) {
    $pdo->prepare("DELETE FROM dbo.customer_wishlist WHERE customer_id=? AND service_id=?")
        ->execute([$custId, $serviceId]);
    $result = ['ok' => true, 'in_wishlist' => false, 'message' => 'Removed from wishlist.'];
} else {
    try {
        $pdo->prepare("INSERT INTO dbo.customer_wishlist (customer_id, service_id) VALUES (?,?)")
            ->execute([$custId, $serviceId]);
        $result = ['ok' => true, 'in_wishlist' => true, 'message' => 'Added to wishlist.'];
    } catch (PDOException $e) {
        $result = ['ok' => false, 'in_wishlist' => false, 'message' => 'Could not add to wishlist.'];
    }
}

if ($wantsJson) {
    echo json_encode($result);
} else {
    header('Location: ' . ($redirect ?: 'solemate_homepage.php'));
}
exit;
