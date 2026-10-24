<?php
// ═══════════════════════════════════════════════════════
//  solemate_save_design.php  —  Saved Designs Endpoint
//
//  POST params:
//    action        : save | rename | delete
//    design_name   : string  (save / rename)
//    customization : JSON    (save only)
//    notes         : string  (save only, optional)
//    design_id     : int     (rename / delete)
//
//  Always returns JSON:  { ok: bool, message: string, [id: int] }
//
//  Called by:
//    - 3D customizer "Save Design" button (postMessage → parent JS)
//    - Dashboard "Rename" / "Delete" inline forms
// ═══════════════════════════════════════════════════════
header('Content-Type: application/json');

require 'includes/solemate_customer_auth.php';
if (!isCustomerLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in.']);
    exit;
}

require 'includes/solemate_database_connection.php';

$custId = getCustomerId();
$action = trim($_POST['action'] ?? '');

// ── Helper ──────────────────────────────────────────────
function jsonOut(bool $ok, string $msg, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra));
    exit;
}

// ── Table guard ─────────────────────────────────────────
try {
    $exists = $pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.customer_saved_designs') AND type='U'")->fetchColumn();
    if (!$exists) jsonOut(false, 'Designs table not found. Run the migration SQL.');
} catch (PDOException $e) {
    jsonOut(false, 'DB error: ' . $e->getMessage());
}

// ── SAVE ────────────────────────────────────────────────
if ($action === 'save') {
    $name   = trim($_POST['design_name']   ?? 'My Design');
    $json   = trim($_POST['customization'] ?? '');
    $notes  = trim($_POST['notes']         ?? '');

    if (!$json) jsonOut(false, 'No customization data provided.');

    // Validate JSON
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) jsonOut(false, 'Invalid customization JSON.');

    // Limit to 50 saved designs per customer
    $count = (int)$pdo->prepare("SELECT COUNT(*) FROM dbo.customer_saved_designs WHERE customer_id=?")
                       ->execute([$custId]) && false; // trick: use fetchColumn
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.customer_saved_designs WHERE customer_id=?");
    $countStmt->execute([$custId]);
    if ((int)$countStmt->fetchColumn() >= 50) {
        jsonOut(false, 'Design limit reached (50). Delete some designs first.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO dbo.customer_saved_designs (customer_id, design_name, customization, notes, created_at, updated_at)
        VALUES (?, ?, ?, ?, GETDATE(), GETDATE())
    ");
    $stmt->execute([$custId, $name, $json, $notes]);
    $newId = (int)$pdo->lastInsertId();

    jsonOut(true, 'Design saved.', ['id' => $newId, 'name' => $name]);
}

// ── UPDATE (edit an existing design) ────────────────────
if ($action === 'update') {
    $designId = (int)($_POST['design_id']    ?? 0);
    $name     = trim($_POST['design_name']   ?? '');
    $json     = trim($_POST['customization'] ?? '');

    if (!$designId || !$json) jsonOut(false, 'Missing design_id or customization.');
    json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) jsonOut(false, 'Invalid customization JSON.');
    if ($name === '') $name = 'My Design';
    if (mb_strlen($name) > 100) jsonOut(false, 'Name too long (max 100 characters).');

    $stmt = $pdo->prepare("UPDATE dbo.customer_saved_designs SET design_name=?, customization=?, updated_at=GETDATE() WHERE id=? AND customer_id=?");
    $stmt->execute([$name, $json, $designId, $custId]);

    if ($stmt->rowCount() === 0) jsonOut(false, 'Design not found or access denied.');
    jsonOut(true, 'Design updated.', ['id' => $designId, 'name' => $name]);
}

// ── RENAME ──────────────────────────────────────────────
if ($action === 'rename') {
    $designId = (int)($_POST['design_id']    ?? 0);
    $newName  = trim($_POST['design_name']   ?? '');

    if (!$designId || !$newName) jsonOut(false, 'Missing design_id or design_name.');
    if (mb_strlen($newName) > 100) jsonOut(false, 'Name too long (max 100 characters).');

    $stmt = $pdo->prepare("UPDATE dbo.customer_saved_designs SET design_name=?, updated_at=GETDATE() WHERE id=? AND customer_id=?");
    $stmt->execute([$newName, $designId, $custId]);

    if ($stmt->rowCount() === 0) jsonOut(false, 'Design not found or access denied.');
    jsonOut(true, 'Design renamed.', ['name' => $newName]);
}

// ── DELETE ──────────────────────────────────────────────
if ($action === 'delete') {
    $designId = (int)($_POST['design_id'] ?? 0);
    if (!$designId) jsonOut(false, 'Missing design_id.');

    $pdo->prepare("DELETE FROM dbo.customer_saved_designs WHERE id=? AND customer_id=?")
        ->execute([$designId, $custId]);

    jsonOut(true, 'Design deleted.');
}

jsonOut(false, 'Unknown action.');