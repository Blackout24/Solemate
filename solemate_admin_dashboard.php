<?php
// ═══════════════════════════════════════════════════════
//  admin.php  —  SoleMate Admin Dashboard
//  Protected by admin_login.php session authentication
// ═══════════════════════════════════════════════════════
session_start();

// ── Auth guard ──────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: solemate_admin_login.php');
    exit;
}

require 'includes/solemate_database_connection.php';
require 'includes/solemate_mailer.php';
require_once 'includes/solemate_ecommerce.php';

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$flash     = null;
$settings  = getSiteSettings($pdo);

// ── Handle logout ───────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: solemate_admin_login.php?bye=1');
    exit;
}

// ── Save feature toggles ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feature_settings'])) {
    $hasSettingsTable = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.site_settings') AND type='U'")->fetchColumn();
    if ($hasSettingsTable) {
        $upsert = $pdo->prepare("
            IF EXISTS (SELECT 1 FROM dbo.site_settings WHERE setting_key=?)
                UPDATE dbo.site_settings SET setting_value=?, updated_at=GETDATE() WHERE setting_key=?
            ELSE
                INSERT INTO dbo.site_settings (setting_key, setting_value) VALUES (?, ?)
        ");
        foreach (['gift_cards','coupons','loyalty','referrals'] as $key) {
            $val = isset($_POST['feat_'.$key]) ? '1' : '0';
            $upsert->execute([$key, $val, $key, $key, $val]);
        }
        header('Location: solemate_admin_dashboard.php?flash=settings_saved#settings');
        exit;
    }
    header('Location: solemate_admin_dashboard.php?flash=settings_migration_needed#settings');
    exit;
}

// ── Handle status update ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id      = (int)$_POST['order_id'];
    $status  = $_POST['status'] ?? '';
    $allowed = ['Pending','Received','Assessment','In service','Quality check','Ready',
                'Design confirmed','Pattern cut','Stitching','Sole bonding','Finishing','Dispatched'];
    if ($id > 0 && in_array($status, $allowed)) {
        $pdo->prepare("UPDATE booking SET status = ? WHERE id = ?")->execute([$status, $id]);

        // Fetch order details to send notification email
        $orderStmt = $pdo->prepare("
            SELECT o.order_ref, o.customer_name, o.email, o.shoe_type, s.service_name
            FROM booking o
            LEFT JOIN services s ON o.service_id = s.id
            WHERE o.id = ?
        ");
        $orderStmt->execute([$id]);
        $orderRow = $orderStmt->fetch();

        if ($orderRow && !empty($orderRow['email'])) {
            $trackerUrl = appBaseUrl() . '/solemate_homepage.php?page=tracker&oid=' . urlencode($orderRow['order_ref']);
            sendStatusUpdateEmail([
                'order_ref'   => $orderRow['order_ref'],
                'name'        => $orderRow['customer_name'],
                'email'       => $orderRow['email'],
                'service'     => $orderRow['service_name'] ?? 'Shoe service',
                'shoe'        => $orderRow['shoe_type'] ?? 'Not specified',
                'status'      => $status,
                'tracker_url' => $trackerUrl,
            ]);
        }

        // PRG: redirect so a page refresh doesn't re-submit the form
        header('Location: solemate_admin_dashboard.php?flash=status_updated&order=' . $id . '&status=' . urlencode($status) . '#booking');
        exit;
    }
    header('Location: solemate_admin_dashboard.php?flash=status_error#booking');
    exit;
}

// ── Handle delete ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $id = (int)$_POST['order_id'];
    if ($id > 0) {
        $pdo->prepare("DELETE FROM booking WHERE id = ?")->execute([$id]);
        header('Location: solemate_admin_dashboard.php?flash=deleted&order=' . $id . '#booking');
        exit;
    }
    header('Location: solemate_admin_dashboard.php?flash=delete_error#booking');
    exit;
}

// ── Handle password change ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPw  = $_POST['current_password']  ?? '';
    $newPw      = $_POST['new_password']       ?? '';
    $confirmPw  = $_POST['confirm_password']   ?? '';

    $userId = $_SESSION['admin_user_id'];
    $stmt   = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    $row    = $stmt->fetch();

    if (!$row || !password_verify($currentPw, $row['password_hash'])) {
        header('Location: solemate_admin_dashboard.php?flash=pw_wrong#settings');
        exit;
    } elseif (strlen($newPw) < 8) {
        header('Location: solemate_admin_dashboard.php?flash=pw_short#settings');
        exit;
    } elseif ($newPw !== $confirmPw) {
        header('Location: solemate_admin_dashboard.php?flash=pw_mismatch#settings');
        exit;
    } else {
        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
        header('Location: solemate_admin_dashboard.php?flash=pw_updated#settings');
        exit;
    }
}

// ── Inventory: Add item ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory'])) {
    $itemName  = trim($_POST['inv_name']     ?? '');
    $category  = trim($_POST['inv_category'] ?? 'General');
    $qty       = (float)($_POST['inv_qty']   ?? 0);
    $unit      = trim($_POST['inv_unit']     ?? 'unit');
    $reorder   = (float)($_POST['inv_reorder']   ?? 5);
    $cost      = (float)($_POST['inv_cost']       ?? 0);
    $supplier  = trim($_POST['inv_supplier'] ?? '');
    $notes     = trim($_POST['inv_notes']    ?? '');
    if ($itemName && $hasInventory) {
        $pdo->prepare("INSERT INTO dbo.inventory (item_name,category,quantity,unit,reorder_level,cost_per_unit,supplier,notes,updated_at) VALUES (?,?,?,?,?,?,?,?,GETDATE())")
            ->execute([$itemName,$category,$qty,$unit,$reorder,$cost,$supplier,$notes]);
    }
    header('Location: solemate_admin_dashboard.php?flash=inv_added#analytics'); exit;
}

// ── Inventory: Adjust stock ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $invId    = (int)($_POST['inv_id']  ?? 0);
    $newQty   = (float)($_POST['new_qty'] ?? 0);
    if ($invId > 0 && $hasInventory) {
        $pdo->prepare("UPDATE dbo.inventory SET quantity=?, updated_at=GETDATE() WHERE id=?")
            ->execute([$newQty, $invId]);
    }
    header('Location: solemate_admin_dashboard.php?flash=inv_updated#analytics'); exit;
}

// ── Inventory: Delete item ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inventory'])) {
    $invId = (int)($_POST['inv_id'] ?? 0);
    if ($invId > 0 && $hasInventory) {
        $pdo->prepare("DELETE FROM dbo.inventory WHERE id=?")->execute([$invId]);
    }
    header('Location: solemate_admin_dashboard.php?flash=inv_deleted#analytics'); exit;
}

// ── Care orders: status update ───────────────────────────
require_once __DIR__ . '/includes/solemate_care_orders.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_care_order'])) {
    $coId = (int)($_POST['order_id'] ?? 0);
    $coSt = $_POST['order_status'] ?? '';
    if ($coId > 0 && in_array($coSt, CARE_ORDER_STATUSES, true) && careTableExists($pdo, 'care_orders')) {
        $pdo->prepare("UPDATE dbo.care_orders SET status=? WHERE id=?")->execute([$coSt, $coId]);
    }
    header('Location: solemate_admin_dashboard.php?flash=care_order_saved#care'); exit;
}

// ── Care products: image helpers + CRUD ─────────────────
function careImageSave(array $file, ?string &$err): ?string {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK)  { $err = 'Image upload failed.'; return null; }
    if ($file['size'] > 3 * 1024 * 1024)   { $err = 'Image must be under 3MB.'; return null; }
    $info = @getimagesize($file['tmp_name']);
    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$info['mime'] ?? ''] ?? null;
    if (!$ext) { $err = 'Image must be JPG, PNG, WEBP or GIF.'; return null; }
    $dir = __DIR__ . '/uploads/care';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'care_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { $err = 'Could not save image.'; return null; }
    return 'uploads/care/' . $name;
}
function careImageDelete(?string $path): void {
    if ($path && strpos($path, 'uploads/care/') === 0) {
        $f = __DIR__ . '/' . $path;
        if (is_file($f)) @unlink($f);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_care']) || isset($_POST['update_care']) || isset($_POST['delete_care']))) {
    $careTable = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.care_products') AND type='U'")->fetchColumn();
    if (!$careTable) { header('Location: solemate_admin_dashboard.php?flash=care_table_missing#care'); exit; }

    $cName  = trim($_POST['care_name'] ?? '');
    $cDesc  = mb_substr(trim($_POST['care_desc'] ?? ''), 0, 500);
    $cPrice = (float)($_POST['care_price'] ?? 0);
    $cIcon  = mb_substr(trim($_POST['care_icon'] ?? ''), 0, 16);
    $cId    = (int)($_POST['care_id'] ?? 0);

    if (isset($_POST['add_care'])) {
        if ($cName === '' || $cPrice < 0) { header('Location: solemate_admin_dashboard.php?flash=care_error&msg=' . urlencode('Name and a valid price are required.') . '#care'); exit; }
        $err = null; $img = careImageSave($_FILES['care_image'] ?? [], $err);
        if ($err) { header('Location: solemate_admin_dashboard.php?flash=care_error&msg=' . urlencode($err) . '#care'); exit; }
        $next = (int)$pdo->query("SELECT ISNULL(MAX(sort_order),0)+10 FROM dbo.care_products")->fetchColumn();
        $pdo->prepare("INSERT INTO dbo.care_products (name,description,price,icon,image_path,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$cName, $cDesc ?: null, $cPrice, $cIcon ?: null, $img, $next]);
        header('Location: solemate_admin_dashboard.php?flash=care_saved#care'); exit;
    }

    if (isset($_POST['update_care']) && $cId > 0) {
        if ($cName === '' || $cPrice < 0) { header('Location: solemate_admin_dashboard.php?flash=care_error&msg=' . urlencode('Name and a valid price are required.') . '#care'); exit; }
        $old = $pdo->prepare("SELECT image_path FROM dbo.care_products WHERE id=?"); $old->execute([$cId]);
        $oldImg = $old->fetchColumn();
        $err = null; $newImg = careImageSave($_FILES['care_image'] ?? [], $err);
        if ($err) { header('Location: solemate_admin_dashboard.php?flash=care_error&msg=' . urlencode($err) . '#care'); exit; }
        $img = $oldImg ?: null;
        if ($newImg)                              { careImageDelete($oldImg); $img = $newImg; }
        elseif (!empty($_POST['care_remove_img'])) { careImageDelete($oldImg); $img = null; }
        $active = isset($_POST['care_active']) ? 1 : 0;
        $pdo->prepare("UPDATE dbo.care_products SET name=?,description=?,price=?,icon=?,image_path=?,is_active=? WHERE id=?")
            ->execute([$cName, $cDesc ?: null, $cPrice, $cIcon ?: null, $img, $active, $cId]);
        header('Location: solemate_admin_dashboard.php?flash=care_saved#care'); exit;
    }

    if (isset($_POST['delete_care']) && $cId > 0) {
        $old = $pdo->prepare("SELECT image_path FROM dbo.care_products WHERE id=?"); $old->execute([$cId]);
        careImageDelete($old->fetchColumn() ?: null);
        $pdo->prepare("DELETE FROM dbo.care_products WHERE id=?")->execute([$cId]);
        header('Location: solemate_admin_dashboard.php?flash=care_deleted#care'); exit;
    }
}

// ── E-Commerce: Coupon CRUD ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code  = strtoupper(trim($_POST['cp_code']  ?? ''));
    $type  = in_array($_POST['cp_type'] ?? '', ['percent','fixed']) ? $_POST['cp_type'] : 'percent';
    $value = (float)($_POST['cp_value'] ?? 0);
    $min   = (float)($_POST['cp_min']   ?? 0);
    $max   = (int)  ($_POST['cp_max']   ?? 0);
    $from  = trim($_POST['cp_from']  ?? '') ?: null;
    $until = trim($_POST['cp_until'] ?? '') ?: null;
    if ($code && $value > 0) {
        try {
            $pdo->prepare("INSERT INTO dbo.coupons (code,type,value,min_order,max_uses,valid_from,valid_until) VALUES (?,?,?,?,?,?,?)")
                ->execute([$code,$type,$value,$min,$max,$from,$until]);
        } catch (PDOException $e) { /* duplicate code */ }
    }
    header('Location: solemate_admin_dashboard.php?flash=coupon_added#ecommerce'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_coupon'])) {
    $cid = (int)($_POST['coupon_id'] ?? 0);
    if ($cid > 0)
        $pdo->prepare("UPDATE dbo.coupons SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$cid]);
    header('Location: solemate_admin_dashboard.php?flash=coupon_updated#ecommerce'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $cid = (int)($_POST['coupon_id'] ?? 0);
    if ($cid > 0) $pdo->prepare("DELETE FROM dbo.coupons WHERE id=?")->execute([$cid]);
    header('Location: solemate_admin_dashboard.php?flash=coupon_deleted#ecommerce'); exit;
}

// ── E-Commerce: Gift Card CRUD ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gift_card'])) {
    require_once 'includes/solemate_ecommerce.php';
    require_once 'includes/solemate_payfast.php';
    $amt   = (float)($_POST['gc_amount']          ?? 0);
    $recip = trim($_POST['gc_recipient_name']      ?? '');
    $remail= strtolower(trim($_POST['gc_recipient_email'] ?? ''));
    $msg   = trim($_POST['gc_message'] ?? '');
    if ($amt >= 50 && $recip) {
        $code = generateGiftCardCode($pdo);
        $pdo->prepare("INSERT INTO dbo.gift_cards (code,initial_balance,balance,purchased_by,recipient_email,recipient_name,message,is_active,expires_at) VALUES (?,?,?,N'Admin',?,?,?,1,?)")
            ->execute([$code,$amt,$amt,$remail,$recip,$msg,date('Y-m-d',strtotime('+1 year'))]);
    }
    header('Location: solemate_admin_dashboard.php?flash=gc_added#ecommerce'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_gift_card'])) {
    $gcid = (int)($_POST['gc_id'] ?? 0);
    if ($gcid > 0)
        $pdo->prepare("UPDATE dbo.gift_cards SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$gcid]);
    header('Location: solemate_admin_dashboard.php?flash=gc_updated#ecommerce'); exit;
}

// ── E-Commerce: Loyalty Adjust ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_loyalty'])) {
    require_once 'includes/solemate_ecommerce.php';
    $cid    = (int)  ($_POST['loyalty_customer_id'] ?? 0);
    $delta  = (int)  ($_POST['loyalty_delta']       ?? 0);
    $reason = trim($_POST['loyalty_reason']          ?? 'Admin adjustment');
    if ($cid > 0 && $delta !== 0) adjustLoyaltyPoints($pdo, $cid, $delta, $reason);
    header('Location: solemate_admin_dashboard.php?flash=loyalty_adjusted#ecommerce'); exit;
}

// ── Load e-commerce data ────────────────────────────────
$coupons = $giftCards = $loyalCustomers = [];
$ecomReady = $hasGC = $hasPayments = false;
$payStats  = ['total'=>0,'revenue'=>0,'pending'=>0];
try {
    $ecomReady = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.coupons') AND type='U'")->fetchColumn();
} catch (PDOException $e) {}
if ($ecomReady) {
    $coupons = $pdo->query("SELECT * FROM dbo.coupons ORDER BY created_at DESC")->fetchAll();
    $hasGC   = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.gift_cards') AND type='U'")->fetchColumn();
    if ($hasGC) $giftCards = $pdo->query("SELECT * FROM dbo.gift_cards ORDER BY created_at DESC")->fetchAll();
    $hasLoyCol = (bool)$pdo->query("SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.customers') AND name='loyalty_points'")->fetchColumn();
    if ($hasLoyCol) $loyalCustomers = $pdo->query("SELECT id,first_name,last_name,email,loyalty_points FROM dbo.customers ORDER BY loyalty_points DESC")->fetchAll();
    // Referrals
    $hasReferrals = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.referrals') AND type='U'")->fetchColumn();
    if ($hasReferrals) {
        $referralRows = $pdo->query("
            SELECT r.*, 
                   c1.first_name+' '+c1.last_name AS referrer_name, c1.email AS referrer_email, c1.referral_code,
                   c2.first_name+' '+c2.last_name AS referee_name,  c2.email AS referee_email
            FROM dbo.referrals r
            JOIN dbo.customers c1 ON r.referrer_id = c1.id
            JOIN dbo.customers c2 ON r.referee_id  = c2.id
            ORDER BY r.created_at DESC
        ")->fetchAll();
    }
    $hasPayments = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.payments') AND type='U'")->fetchColumn();
    if ($hasPayments) {
        $ps = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount_gross),0) AS rev FROM dbo.payments WHERE status='complete'")->fetch();
        $pp = (int)$pdo->query("SELECT COUNT(*) FROM dbo.payments WHERE status='pending'")->fetchColumn();
        $payStats = ['total'=>(int)$ps['cnt'],'revenue'=>(float)$ps['rev'],'pending'=>$pp];
    }
}

// ── Load customer records ───────────────────────────────
$customers    = [];
$hasActiveCol = false;
try {
    $hasActiveCol = (bool)$pdo->query("SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.customers') AND name='is_active'")->fetchColumn();
    $activeSelect = $hasActiveCol ? 'is_active' : '1 AS is_active';
    $customers = $pdo->query("
        SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.created_at, c.total_spent, c.$activeSelect,
               (SELECT COUNT(*) FROM dbo.booking b WHERE b.email = c.email) AS booking_count
        FROM dbo.customers c
        ORDER BY c.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Load payment records (for verification) ─────────────
$payments = [];
$hasVerifyCols = false;
if ($hasPayments) {
    try {
        $hasVerifyCols = (bool)$pdo->query("SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.payments') AND name='verified_by'")->fetchColumn();
        $payments = $pdo->query("SELECT * FROM dbo.payments ORDER BY created_at DESC")->fetchAll();
    } catch (PDOException $e) {}
}

// ── Update / deactivate / reactivate a customer ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    $cid   = (int)($_POST['customer_id'] ?? 0);
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name']  ?? '');
    $email = trim($_POST['email']      ?? '');
    $phone = trim($_POST['phone']      ?? '');
    if ($cid > 0 && $fname !== '' && $email !== '') {
        $pdo->prepare("UPDATE dbo.customers SET first_name=?, last_name=?, email=?, phone=? WHERE id=?")
            ->execute([$fname, $lname, $email, $phone, $cid]);
    }
    header('Location: solemate_admin_dashboard.php?flash=customer_saved#customers'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_customer_active']) && $hasActiveCol) {
    $cid = (int)($_POST['customer_id'] ?? 0);
    $newState = (int)($_POST['new_state'] ?? 1);
    if ($cid > 0) {
        $pdo->prepare("UPDATE dbo.customers SET is_active=? WHERE id=?")->execute([$newState, $cid]);
    }
    header('Location: solemate_admin_dashboard.php?flash=' . ($newState ? 'customer_reactivated' : 'customer_deactivated') . '#customers'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $cid = (int)($_POST['customer_id'] ?? 0);
    if ($cid > 0) {
        try {
            $pdo->prepare("DELETE FROM dbo.customers WHERE id=?")->execute([$cid]);
            header('Location: solemate_admin_dashboard.php?flash=customer_deleted#customers'); exit;
        } catch (PDOException $e) {
            header('Location: solemate_admin_dashboard.php?flash=customer_delete_blocked#customers'); exit;
        }
    }
    header('Location: solemate_admin_dashboard.php?flash=customer_delete_blocked#customers'); exit;
}

// ── Verify / reject a payment ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment']) && $hasVerifyCols) {
    $cartRef = trim($_POST['cart_ref'] ?? '');
    if ($cartRef !== '') {
        $result = completePaymentManually($pdo, $cartRef, $_SESSION['admin_username'] ?? 'admin');
        header('Location: solemate_admin_dashboard.php?flash=' . ($result['ok'] ? 'payment_verified' : 'payment_verify_failed') . '#payments'); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment']) && $hasVerifyCols) {
    $cartRef = trim($_POST['cart_ref'] ?? '');
    $notes   = trim($_POST['admin_notes'] ?? '');
    if ($cartRef !== '') {
        $pdo->prepare("UPDATE dbo.payments SET status=N'failed', verified_by=?, verified_at=GETDATE(), admin_notes=? WHERE cart_ref=? AND status<>N'complete'")
            ->execute([$_SESSION['admin_username'] ?? 'admin', $notes ?: null, $cartRef]);
    }
    header('Location: solemate_admin_dashboard.php?flash=payment_rejected#payments'); exit;
}

// ── Resolve flash message from GET param (PRG pattern) ──
$flashMap = [
    'status_updated' => ['success', 'Order #' . ((int)($_GET['order'] ?? 0)) . ' updated to <strong>' . htmlspecialchars($_GET['status'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong>.'],
    'status_error'   => ['error',   'Invalid status update — please try again.'],
    'deleted'        => ['error',   'Order #' . ((int)($_GET['order'] ?? 0)) . ' has been deleted.'],
    'delete_error'   => ['error',   'Could not delete order — please try again.'],
    'pw_updated'     => ['success', 'Password updated successfully.'],
    'pw_wrong'       => ['error',   'Current password is incorrect.'],
    'pw_short'       => ['error',   'New password must be at least 8 characters.'],
    'pw_mismatch'    => ['error',   'New passwords do not match.'],
    'inv_added'      => ['success', 'Inventory item added.'],
    'care_saved'     => ['success', 'Care product saved.'],
    'care_order_saved' => ['success', 'Care order updated.'],
    'care_deleted'   => ['error',   'Care product deleted.'],
    'care_table_missing' => ['error', 'Run solemate_care_products_migration.sql in SSMS first.'],
    'care_error'     => ['error',   htmlspecialchars($_GET['msg'] ?? 'Could not save product.', ENT_QUOTES, 'UTF-8')],
    'coupon_added'   => ['success', 'Coupon created.'],
    'coupon_updated' => ['success', 'Coupon updated.'],
    'coupon_deleted' => ['error',   'Coupon deleted.'],
    'gc_added'       => ['success', 'Gift card created.'],
    'gc_updated'     => ['success', 'Gift card updated.'],
    'loyalty_adjusted'=> ['success', 'Loyalty points adjusted.'],
    'inv_updated'    => ['success', 'Stock level updated.'],
    'inv_deleted'    => ['error',   'Inventory item removed.'],
    'settings_saved' => ['success', 'Feature settings saved.'],
    'settings_migration_needed' => ['error', 'Run solemate_settings_migration.sql first.'],
    'customer_saved'         => ['success', 'Customer details updated.'],
    'customer_deactivated'   => ['success', 'Customer account deactivated.'],
    'customer_reactivated'   => ['success', 'Customer account reactivated.'],
    'customer_deleted'       => ['error',   'Customer account permanently deleted.'],
    'customer_delete_blocked'=> ['error',   'Could not delete — this customer has referral history. Deactivate the account instead.'],
    'payment_verified'       => ['success', 'Payment verified and marked complete.'],
    'payment_verify_failed'  => ['error',   'Could not verify that payment — it may already be complete.'],
    'payment_rejected'       => ['success', 'Payment marked as failed.'],
];
$flashKey = $_GET['flash'] ?? '';
if (isset($flashMap[$flashKey])) {
    [$flashType, $flashMsg] = $flashMap[$flashKey];
    $flash = ['type' => $flashType, 'msg' => $flashMsg];
}

// ── Fetch data ──────────────────────────────────────────
// Detect if customization_data column exists
$hasCustCol = false;
try {
    $colCheck = $pdo->query("SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.booking') AND name = N'customization_data'");
    $hasCustCol = (bool)$colCheck->fetch();
} catch (\PDOException $e) { /* ignore */ }

$custColSelect = $hasCustCol ? ', o.customization_data' : ", '' AS customization_data";

$booking = $pdo->query("
    SELECT
        o.id,
        o.order_ref,
        o.customer_name,
        o.email,
        o.phone,
        o.service_id,
        o.shoe_type,
        o.drop_date,
        o.collection,
        o.notes,
        o.status,
        o.order_date,
        s.service_name,
        s.price
        {$custColSelect}
    FROM booking o
    LEFT JOIN services s ON o.service_id = s.id
    ORDER BY o.order_date DESC
")->fetchAll();

$services = $pdo->query("SELECT * FROM services ORDER BY sort_order")->fetchAll();

// Separate custom shoe booking
$customOrders = array_filter($booking, function($o) {
    if (!empty($o['customization_data'])) return true;
    // Fallback: detect from shoe_type or service_name
    if (strpos($o['shoe_type'] ?? '', 'Custom') === 0) return true;
    if (stripos($o['service_name'] ?? '', 'Custom Shoe') !== false) return true;
    if (strpos($o['notes'] ?? '', '[DESIGN]') === 0) return true;
    return false;
});
$customOrders = array_values($customOrders);

// Stats
$total    = count($booking);
$ready   = count(array_filter($booking, fn($o) => ($o['status'] ?? '') === 'Ready'));
$pending = count(array_filter($booking, fn($o) => ($o['status'] ?? '') === 'Received'));
$revenue  = array_sum(array_column($booking, 'price'));
$customTotal = count($customOrders);

// ── Analytics data ──────────────────────────────────────
$monthlyData = $pdo->query("
    SELECT
        FORMAT(b.order_date,'yyyy-MM')  AS ym,
        FORMAT(b.order_date,'MMM yy')   AS label,
        COUNT(*)                         AS orders,
        COALESCE(SUM(s.price), 0)        AS revenue
    FROM dbo.booking b
    LEFT JOIN dbo.services s ON b.service_id = s.id
    WHERE b.order_date >= DATEADD(MONTH,-11,DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1))
    GROUP BY FORMAT(b.order_date,'yyyy-MM'), FORMAT(b.order_date,'MMM yy')
    ORDER BY 1
")->fetchAll();

$serviceRevenue = $pdo->query("
    SELECT TOP 8 s.service_name, COUNT(*) AS orders, COALESCE(SUM(s.price),0) AS revenue
    FROM dbo.booking b
    INNER JOIN dbo.services s ON b.service_id = s.id
    GROUP BY s.service_name ORDER BY revenue DESC
")->fetchAll();

$statusBreakdown = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM dbo.booking GROUP BY status ORDER BY cnt DESC
")->fetchAll();

$collBreakdown = $pdo->query("
    SELECT collection, COUNT(*) AS cnt FROM dbo.booking GROUP BY collection
")->fetchAll();

$revenueTotal     = (float)$pdo->query("SELECT COALESCE(SUM(s.price),0) FROM dbo.booking b LEFT JOIN dbo.services s ON b.service_id=s.id")->fetchColumn();
$revenueThisMonth = (float)$pdo->query("SELECT COALESCE(SUM(s.price),0) FROM dbo.booking b LEFT JOIN dbo.services s ON b.service_id=s.id WHERE MONTH(b.order_date)=MONTH(GETDATE()) AND YEAR(b.order_date)=YEAR(GETDATE())")->fetchColumn();
$ordersThisMonth  = (int)  $pdo->query("SELECT COUNT(*) FROM dbo.booking WHERE MONTH(order_date)=MONTH(GETDATE()) AND YEAR(order_date)=YEAR(GETDATE())")->fetchColumn();
$avgOrderValue    = (float)$pdo->query("SELECT COALESCE(AVG(s.price),0) FROM dbo.booking b LEFT JOIN dbo.services s ON b.service_id=s.id WHERE s.price IS NOT NULL")->fetchColumn();

// Aggregate colours & sizes from customization_data (PHP-side)
$colourCounts = [];
$sizeCounts   = [];
foreach ($booking as $bk) {
    if (empty($bk['customization_data'])) continue;
    $dec = json_decode($bk['customization_data'], true) ?? [];
    if (!empty($dec['colors']) && is_array($dec['colors'])) {
        foreach ($dec['colors'] as $hex) {
            $h = strtoupper(trim($hex));
            if ($h) $colourCounts[$h] = ($colourCounts[$h] ?? 0) + 1;
        }
    }
    if (!empty($dec['size'])) {
        $sz = trim($dec['size']);
        if ($sz) $sizeCounts[$sz] = ($sizeCounts[$sz] ?? 0) + 1;
    }
}
arsort($colourCounts);
arsort($sizeCounts);
$topColours = $colourCounts; // used by the Analysis tab swatches

// ── Inventory data ──────────────────────────────────────
$inventory    = [];
$hasInventory = false;
try {
    $hasInventory = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.inventory') AND type='U'")->fetchColumn();
} catch (PDOException $e) {}
if ($hasInventory) {
    $inventory = $pdo->query("SELECT * FROM dbo.inventory ORDER BY category, item_name")->fetchAll();
}
// ── Care orders data ────────────────────────────────────
$careOrdersAdmin = []; $careOrderItems = [];
try {
    if (careTableExists($pdo, 'care_orders')) {
        $careOrdersAdmin = $pdo->query("SELECT TOP 100 * FROM dbo.care_orders WHERE payment_status<>N'cancelled' ORDER BY created_at DESC")->fetchAll();
        $oids = array_map(fn($r) => (int)$r['id'], $careOrdersAdmin);
        if ($oids) {
            $ph = implode(',', $oids);
            foreach ($pdo->query("SELECT order_id, name, qty FROM dbo.care_order_items WHERE order_id IN ($ph) ORDER BY id")->fetchAll() as $ci) {
                $careOrderItems[(int)$ci['order_id']][] = $ci['name'] . ' × ' . (int)$ci['qty'];
            }
        }
    }
} catch (\Throwable $e) {}
// ── Care products data ──────────────────────────────────
$careAdmin = []; $hasCare = false;
try {
    $hasCare = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.care_products') AND type='U'")->fetchColumn();
    if ($hasCare) $careAdmin = $pdo->query("SELECT * FROM dbo.care_products ORDER BY sort_order, id")->fetchAll();
} catch (\PDOException $e) {}
$invLow = array_values(array_filter($inventory, fn($i)=> (float)$i['quantity']>0 && (float)$i['quantity']<=(float)$i['reorder_level']));
$invOut = array_values(array_filter($inventory, fn($i)=> (float)$i['quantity']<=0));

$statusColors = [
    'Pending'          => '#A0A0A0',
    'Received'         => '#8B7355',
    'Assessment'       => '#C8A97E',
    'In service'       => '#D4561A',
    'Quality check'    => '#6B4226',
    'Ready'            => '#2D6A4F',
    // Custom shoe statuses
    'Design confirmed' => '#5B8DD9',
    'Pattern cut'      => '#7B68C8',
    'Stitching'        => '#B05CC8',
    'Sole bonding'     => '#C87843',
    'Finishing'        => '#C8A020',
    'Dispatched'       => '#2D6A4F',
];
$allStatuses    = ['Pending','Received','Assessment','In service','Quality check','Ready'];
$customStatuses = ['Design confirmed','Pattern cut','Stitching','Sole bonding','Finishing','Dispatched'];

function eh(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SoleMate — Admin Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<link rel="icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;
  --accent:#D4561A;--light:#E8D5BE;--muted:#8B7355;--success:#2D6A4F;
}
body{font-family:'DM Sans',system-ui,sans-serif;background:#f0ece4;color:var(--dark);min-height:100vh}

/* ── HEADER ── */
header{
  background:var(--dark);padding:0 2rem;height:60px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:10;
  box-shadow:0 2px 16px rgba(0,0,0,0.35);
}
.logo{font-family:'Playfair Display',serif;color:var(--tan);font-size:20px;text-decoration:none}
.logo span{color:var(--accent)}
.header-right{display:flex;align-items:center;gap:0.75rem}
.admin-badge{
  background:rgba(212,86,26,0.15);color:var(--accent);
  border:1px solid rgba(212,86,26,0.35);
  font-size:11px;padding:3px 11px;border-radius:20px;
  text-transform:uppercase;letter-spacing:0.5px;
}
.user-chip{
  display:flex;align-items:center;gap:7px;
  background:rgba(255,255,255,0.06);
  border:1px solid rgba(255,255,255,0.1);
  border-radius:20px;padding:4px 12px 4px 6px;
}
.user-avatar{
  width:26px;height:26px;border-radius:50%;
  background:var(--brown);color:var(--tan);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:500;
}
.user-name{font-size:12px;color:rgba(255,255,255,0.7)}
.logout-btn{
  background:transparent;border:1px solid rgba(255,255,255,0.15);
  color:rgba(255,255,255,0.45);font-size:11px;
  padding:4px 11px;border-radius:6px;cursor:pointer;
  font-family:inherit;transition:all 0.15s;text-decoration:none;display:inline-block;
}
.logout-btn:hover{border-color:rgba(212,86,26,0.5);color:var(--accent)}
.back-link{color:rgba(255,255,255,0.35);font-size:12px;text-decoration:none}
.back-link:hover{color:var(--tan)}

/* ── LAYOUT ── */
.wrap{max-width:1140px;margin:0 auto;padding:2rem 1.5rem}
.page-title{font-family:'Playfair Display',serif;font-size:1.7rem;margin-bottom:3px}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:1.75rem}

/* ── FLASH ── */
.flash{padding:11px 16px;border-radius:8px;font-size:13px;margin-bottom:1.375rem;font-weight:500;display:flex;align-items:center;gap:8px}
.flash-success{background:#f0faf5;border:1px solid rgba(45,106,79,0.3);color:var(--success)}
.flash-error{background:#fff8f6;border:1px solid rgba(212,86,26,0.3);color:#8B2200}

/* ── STATS ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem}
.stat-card{background:#fff;border-radius:11px;padding:1.25rem;border:1px solid rgba(107,66,38,0.1)}
.stat-n{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:var(--accent);line-height:1}
.stat-l{font-size:12px;color:var(--muted);margin-top:4px}

/* ── TABS ── */
.tabs{display:flex;gap:4px;margin-bottom:1.25rem;border-bottom:1px solid rgba(107,66,38,0.12);padding-bottom:0}
.tab-btn{
  background:none;border:none;padding:9px 18px;font-size:13px;font-family:inherit;
  cursor:pointer;color:var(--muted);border-bottom:2px solid transparent;margin-bottom:-1px;transition:all 0.15s;
}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);font-weight:500}
.tab-btn:hover{color:var(--dark)}
.tab-panel{display:none}.tab-panel.active{display:block}

/* ── TABLE ── */
.tbl-wrap{overflow-x:auto;border-radius:12px;border:1px solid rgba(107,66,38,0.1)}
table{width:100%;border-collapse:collapse;background:#fff;min-width:700px}
th{
  background:var(--dark);color:rgba(255,255,255,0.65);
  font-size:11px;text-transform:uppercase;letter-spacing:0.5px;
  padding:12px 14px;text-align:left;font-weight:500;
}
td{padding:11px 14px;font-size:13px;border-bottom:1px solid rgba(107,66,38,0.07);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:500;color:#fff;white-space:nowrap}
.order-ref{font-family:monospace;font-size:12px;color:var(--accent);font-weight:700}
.muted-text{font-size:11px;color:var(--muted)}

/* ── STATUS FORM ── */
.status-form{display:flex;gap:6px;align-items:center}
.step-sel{
  border:1px solid rgba(107,66,38,0.2);border-radius:6px;
  padding:5px 8px;font-size:12px;font-family:inherit;
  background:var(--cream);color:var(--dark);outline:none;
}
.upd-btn{
  background:var(--brown);color:#fff;border:none;
  padding:5px 12px;border-radius:5px;font-size:11px;font-weight:500;
  cursor:pointer;font-family:inherit;white-space:nowrap;transition:opacity 0.15s;
}
.upd-btn:hover{opacity:0.82}
.del-btn{
  background:transparent;color:#c0392b;
  border:1px solid rgba(192,57,43,0.3);
  padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;font-family:inherit;
  transition:all 0.15s;
}
.del-btn:hover{background:#fff0ee;border-color:rgba(192,57,43,0.6)}

/* ── CUSTOM ORDER CARDS ── */
.custom-order-card{background:#fff;border:1px solid rgba(107,66,38,0.1);border-radius:13px;overflow:hidden}
.coc-header{display:flex;justify-content:space-between;align-items:center;padding:.875rem 1.125rem;background:var(--dark);color:#fff}
.coc-body{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;padding:1rem 1.125rem}
.coc-section{min-width:0}
.coc-section-title{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;font-weight:600}
.coc-swatches{display:flex;flex-wrap:wrap;gap:.5rem}
.coc-swatch-item{display:flex;align-items:center;gap:5px;background:var(--cream);padding:3px 8px 3px 5px;border-radius:20px;border:1px solid rgba(107,66,38,0.1)}
.coc-swatch-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0;border:1px solid rgba(0,0,0,0.12);color-scheme:only light}
.coc-swatch-label{font-size:10px;color:var(--muted);white-space:nowrap}
.coc-footer{display:flex;align-items:center;gap:.625rem;padding:.75rem 1.125rem;border-top:1px solid rgba(107,66,38,0.07);background:var(--cream)}

/* ── SERVICES TAB ── */
.svc-icon{font-size:18px}
.price-cell{font-family:'Playfair Display',serif;font-size:14px;color:var(--accent)}

/* ── SETTINGS TAB ── */
.settings-section{
  background:#fff;border-radius:12px;border:1px solid rgba(107,66,38,0.1);
  padding:1.5rem;max-width:460px;margin-bottom:1.25rem;
}
.settings-title{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:1rem}
.fg{margin-bottom:0.875rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px}
.fg input{
  width:100%;border:1px solid rgba(107,66,38,0.2);border-radius:7px;
  padding:9px 12px;font-size:13px;font-family:inherit;
  background:var(--cream);color:var(--dark);outline:none;transition:border-color 0.18s;
}
.fg input:focus{border-color:var(--accent)}
.save-btn{
  background:var(--accent);color:#fff;border:none;padding:10px 22px;
  border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;transition:opacity 0.15s;
}
.save-btn:hover{opacity:0.87}

.modal-overlay{position:fixed;inset:0;background:rgba(26,15,8,.55);z-index:400;display:none;align-items:center;justify-content:center;padding:1.5rem}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:1.75rem;width:100%;max-width:460px;position:relative}
.modal-close{position:absolute;top:14px;right:14px;background:none;border:none;font-size:16px;cursor:pointer;color:var(--muted)}
.modal-title{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;margin-bottom:1.1rem}

/* ── INFO GRID ── */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem}
.info-card{background:#fff;border-radius:11px;padding:1.25rem;border:1px solid rgba(107,66,38,0.1)}
.info-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px}
.info-value{font-size:14px;font-weight:500}

@media(max-width:700px){
  .stats-row{grid-template-columns:1fr 1fr}
  .info-grid{grid-template-columns:1fr}
  .header-right .admin-badge,.header-right .user-chip{display:none}
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>

<header>
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <div class="header-right">
    <span class="admin-badge">Admin Panel</span>
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?></div>
      <span class="user-name"><?= $adminName ?></span>
    </div>
    <a href="solemate_homepage.php" class="back-link">← Site</a>
    <a href="admin.php?logout=1" class="logout-btn"
       onclick="return confirm('Sign out of the admin panel?')">Sign out</a>
  </div>
</header>

<div class="wrap">
  <div class="page-title">Booking Management</div>
  <div class="page-sub">Manage all bookings, update statuses, and configure services.</div>

  <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
      <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?> <?= $flash['msg'] ?>
    </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card"><div class="stat-n"><?= $total ?></div><div class="stat-l">Total Booking</div></div>
    <div class="stat-card"><div class="stat-n" style="color:var(--success)"><?= $ready ?></div><div class="stat-l">Ready for collection</div></div>
    <div class="stat-card"><div class="stat-n" style="color:var(--brown)"><?= $pending ?></div><div class="stat-l">New / pending</div></div>
    <div class="stat-card"><div class="stat-n" style="color:var(--tan)">R<?= number_format($revenue, 0) ?></div><div class="stat-l">Est. revenue</div></div>
    <div class="stat-card"><div class="stat-n" style="font-size:1.4rem">4.9 ★</div><div class="stat-l">Avg. review score</div></div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('booking',this)">All Booking (<?= $total ?>)</button>
    <button class="tab-btn" onclick="switchTab('custom',this)">🎨 Custom Shoes (<?= $customTotal ?>)</button>
    <button class="tab-btn" onclick="switchTab('customers',this)">👥 Customers (<?= count($customers) ?>)</button>
    <button class="tab-btn" onclick="switchTab('payments',this)">💳 Payments</button>
    <button class="tab-btn" onclick="switchTab('services',this)">Services</button>
    <button class="tab-btn" onclick="switchTab('settings',this)">Settings</button>
    <button class="tab-btn" onclick="switchTab('backup',this)">Backup</button>
    <button class="tab-btn" onclick="switchTab('ecommerce',this)">🛒 E-Commerce</button>
    <button class="tab-btn" onclick="switchTab('care',this)">🧴 Care Products</button>
    <button class="tab-btn" onclick="switchTab('analytics',this)">📊 Analytics</button>
    <button class="tab-btn" onclick="switchTab('inventory',this)">📦 Inventory
      <?php if (count($invOut)>0): ?><span style="background:#c0392b;color:#fff;font-size:10px;padding:1px 6px;border-radius:20px;margin-left:4px"><?= count($invOut) ?></span>
      <?php elseif (count($invLow)>0): ?><span style="background:var(--accent);color:#fff;font-size:10px;padding:1px 6px;border-radius:20px;margin-left:4px"><?= count($invLow) ?></span><?php endif; ?>
    </button>
  </div>

  <!-- ══ ORDERS TAB ══════════════════════════════════════ -->
  <div class="tab-panel active" id="tab-booking">
    <!-- Filter bar -->
    <div style="display:flex;gap:0.5rem;margin-bottom:0.875rem;flex-wrap:wrap;align-items:center">
      <input type="text" id="searchInput" placeholder="Search booking…"
             oninput="filterTable()"
             style="border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:7px 12px;font-size:13px;font-family:inherit;background:#fff;color:var(--dark);outline:none;min-width:200px;flex:1">
      <select id="statusFilter" onchange="filterTable()"
              style="border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:7px 11px;font-size:12px;font-family:inherit;background:#fff;color:var(--dark);outline:none">
        <option value="">All statuses</option>
        <?php foreach ($allStatuses as $s) echo "<option value=\"{$s}\">{$s}</option>"; ?>
      </select>
    </div>

    <div class="tbl-wrap">
      <table id="bookingTable">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Shoe</th>
            <th>Method</th>
            <th>Drop-off</th>
            <th>Status</th>
            <th>Update</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($booking as $o):
          $color = $statusColors[$o['status'] ?? ''] ?? '#999';
          $collLabel = ['drop-off'=>'Drop-off','courier'=>'Courier','home'=>'Home'][$o['collection']] ?? $o['collection'];
          $isCustomRow = (!empty($o['customization_data']) || strpos($o['shoe_type'] ?? '', 'Custom') === 0);
          $rowStatuses  = $isCustomRow ? $customStatuses : $allStatuses;
        ?>
          <tr data-status="<?= eh($o['status'] ?? '') ?>">
            <td><?= eh($o['order_ref'] ?? '—') ?></td>
            <td>
              <?= eh($o['customer_name']) ?><br>
              <span class="muted-text"><?= eh($o['email']) ?></span>
              <?php if ($o['phone']): ?>
                <br><span class="muted-text"><?= eh($o['phone']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= eh($o['service_name'] ?? '—') ?></td>
            <td><?= eh($o['shoe_type'] ?: '—') ?></td>
            <td><?= $collLabel ?></td>
            <td><?= $o['drop_date'] ? date('d M Y', strtotime($o['drop_date'])) : '—' ?></td>
            <td><span class="badge" style="background:<?= $color ?>"><?= eh($o['status'] ?? 'Unknown') ?></span></td>
            <td>
              <form method="POST" class="status-form">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="status" class="step-sel">
                  <?php foreach ($rowStatuses as $s): ?>
                    <option value="<?= $s ?>"<?= $s === ($o['status'] ?? '') ? ' selected' : '' ?>><?= eh($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="upd-btn">Save</button>
              </form>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('Permanently delete order <?= eh($o['order_ref']) ?>?')">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <button type="submit" name="delete_order" class="del-btn" title="Delete order">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($booking)): ?>
          <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2.5rem">No Booking yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /tab-booking -->

  <!-- ══ CUSTOM ORDERS TAB ═════════════════════════════ -->
  <div class="tab-panel" id="tab-custom">
    <?php if (empty($customOrders)): ?>
      <div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
        <div style="font-size:2.5rem;margin-bottom:.75rem">🎨</div>
        <div style="font-family:'Playfair Display',serif;font-size:1.1rem;margin-bottom:.4rem">No custom booking yet</div>
        <div style="font-size:13px">Orders placed through the 3D Customizer will appear here.</div>
      </div>
    <?php else: ?>
      <div style="display:grid;gap:1rem;">
      <?php foreach ($customOrders as $o):
        $color = $statusColors[$o['status'] ?? ''] ?? '#999';
        $colors = [];
        if (!empty($o['customization_data'])) {
            $dec = json_decode($o['customization_data'], true);
            $colors = $dec['colors'] ?? [];
            $shoeSize = $dec['size'] ?? '—';
        } else {
            // Parse from notes [DESIGN] fallback
            $shoeSize = preg_match('/Custom — (UK \d+)/i', $o['shoe_type'] ?? '', $m) ? $m[1] : '—';
            if (preg_match('/\[DESIGN\] (.+)/i', $o['notes'] ?? '', $m2)) {
                foreach (explode(', ', $m2[1]) as $pair) {
                    [$k,$v] = array_pad(explode(':', $pair, 2), 2, '');
                    if ($k && $v) $colors[strtolower(trim($k))] = trim($v);
                }
            }
        }
        $collLabel = ['drop-off'=>'Drop-off','courier'=>'Courier','home'=>'Home'][$o['collection'] ?? ''] ?? ($o['collection'] ?? '');
      ?>
        <div class="custom-order-card">
          <!-- Card header -->
          <div class="coc-header">
            <div>
              <span class="order-ref"><?= eh($o['order_ref'] ?? '—') ?></span>
              <span class="badge" style="background:<?= $color ?>;margin-left:.5rem"><?= eh($o['status'] ?? 'Unknown') ?></span>
            </div>
            <div style="font-size:12px;color:var(--muted)"><?= $o['order_date'] ? date('d M Y, H:i', strtotime($o['order_date'])) : '' ?></div>
          </div>

          <div class="coc-body">
            <!-- Customer info -->
            <div class="coc-section">
              <div class="coc-section-title">Customer</div>
              <div style="font-size:13px;font-weight:500"><?= eh($o['customer_name']) ?></div>
              <div class="muted-text"><?= eh($o['email']) ?></div>
              <?php if ($o['phone']): ?><div class="muted-text"><?= eh($o['phone']) ?></div><?php endif; ?>
              <div class="muted-text" style="margin-top:4px">
                <?= $collLabel ?> &nbsp;·&nbsp;
                Size: <?= htmlspecialchars($shoeSize, ENT_QUOTES, 'UTF-8') ?> &nbsp;·&nbsp;
                <?= $o['drop_date'] ? date('d M Y', strtotime($o['drop_date'])) : 'TBC' ?>
              </div>
            </div>

            <!-- Colour design -->
            <?php if (!empty($colors)): ?>
            <div class="coc-section">
              <div class="coc-section-title">Colour Design</div>
              <div class="coc-swatches">
                <?php
                $partLabels = ['laces'=>'Laces','mesh'=>'Body','caps'=>'Toe Cap','inner'=>'Inner','sole'=>'Sole','stripes'=>'Stripes','band'=>'Band','patch'=>'Patch'];
                foreach ($colors as $part => $hex):
                    $label = $partLabels[strtolower($part)] ?? ucfirst($part);
                    $safehex = htmlspecialchars($hex, ENT_QUOTES, 'UTF-8');
                ?>
                <div class="coc-swatch-item" title="<?= $label ?>: <?= $safehex ?>">
                  <div class="coc-swatch-dot" style="background:<?= $safehex ?>;color-scheme:only light"></div>
                  <div class="coc-swatch-label"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php
            $displayNotes = preg_replace('/^\[DESIGN\][^\n]*\n?/m', '', $o['notes'] ?? '');
            if (trim($displayNotes)):
            ?>
            <div class="coc-section">
              <div class="coc-section-title">Notes</div>
              <div style="font-size:12px;color:var(--muted);line-height:1.6"><?= nl2br(eh(trim($displayNotes))) ?></div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Status update -->
          <div class="coc-footer">
            <form method="POST" class="status-form">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <select name="status" class="step-sel">
                <?php foreach ($customStatuses as $s): ?>
                  <option value="<?= $s ?>"<?= $s === ($o['status'] ?? '') ? ' selected' : '' ?>><?= eh($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" name="update_status" class="upd-btn">Update Status</button>
            </form>
            <form method="POST" onsubmit="return confirm('Permanently delete order <?= eh($o['order_ref']) ?>?')" style="margin-left:auto">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <button type="submit" name="delete_order" class="del-btn" title="Delete">✕</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div><!-- /tab-custom -->

  <!-- ══ CUSTOMERS TAB ═══════════════════════════════════ -->
  <div class="tab-panel" id="tab-customers">

    <?php if (!$hasActiveCol): ?>
    <div style="background:#fff8f6;border:1.5px solid rgba(212,86,26,0.3);border-radius:10px;padding:1rem 1.25rem;font-size:13px;color:#8B2200;margin-bottom:1.25rem">
      ⚠️ Run <strong>solemate_customer_payments_migration.sql</strong> in SSMS to enable deactivating accounts.
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.25rem">
      <div class="stat-card"><div class="stat-n"><?= count($customers) ?></div><div class="stat-l">Total customers</div></div>
      <div class="stat-card"><div class="stat-n" style="color:var(--success)"><?= count(array_filter($customers, fn($c) => !empty($c['is_active']))) ?></div><div class="stat-l">Active</div></div>
      <div class="stat-card"><div class="stat-n" style="color:#c0392b"><?= count(array_filter($customers, fn($c) => empty($c['is_active']))) ?></div><div class="stat-l">Deactivated</div></div>
    </div>

    <input type="text" id="custSearch" placeholder="Search by name or email…" class="inv-input"
           style="max-width:320px;margin-bottom:1rem" onkeyup="filterCustomers()">

    <div style="overflow-x:auto">
      <table class="inv-table" id="customerTable">
        <thead><tr>
          <th>Name</th><th>Email</th><th>Phone</th><th>Joined</th>
          <th style="text-align:right">Bookings</th><th style="text-align:right">Total spent</th>
          <th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($customers as $c): $active = !empty($c['is_active']); ?>
        <tr>
          <td style="font-weight:500"><?= eh($c['first_name'].' '.$c['last_name']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= eh($c['email']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= eh($c['phone'] ?? '') ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= eh(date('d M Y', strtotime($c['created_at']))) ?></td>
          <td style="text-align:right"><?= (int)$c['booking_count'] ?></td>
          <td style="text-align:right">R<?= number_format((float)($c['total_spent'] ?? 0), 2) ?></td>
          <td>
            <?php if ($active): ?>
              <span class="inv-badge inv-badge-ok">Active</span>
            <?php else: ?>
              <span class="inv-badge inv-badge-out">Deactivated</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:.4rem">
              <button type="button" class="inv-cancel" style="padding:5px 10px"
                onclick="openCustomerModal(<?= (int)$c['id'] ?>,<?= htmlspecialchars(json_encode($c['first_name']), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($c['last_name']), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($c['email']), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($c['phone'] ?? ''), ENT_QUOTES) ?>)">Edit</button>
              <?php if ($hasActiveCol): ?>
              <form method="POST" onsubmit="return confirm('<?= $active ? 'Deactivate' : 'Reactivate' ?> this account?')">
                <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="new_state" value="<?= $active ? 0 : 1 ?>">
                <button type="submit" name="toggle_customer_active"
                  style="background:none;border:1px solid rgba(212,86,26,0.25);color:var(--accent);padding:5px 10px;border-radius:6px;font-size:11px;cursor:pointer">
                  <?= $active ? 'Deactivate' : 'Reactivate' ?>
                </button>
              </form>
              <?php endif; ?>
              <form method="POST" onsubmit="return confirm('Permanently delete <?= eh(addslashes($c['first_name'].' '.$c['last_name'])) ?>? This cannot be undone.')">
                <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                <button type="submit" name="delete_customer" class="del-btn" title="Delete customer">✕</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No customers yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /tab-customers -->

  <!-- ══ PAYMENTS TAB ═════════════════════════════════════ -->
  <div class="tab-panel" id="tab-payments">

    <?php if (!$hasPayments): ?>
    <div style="background:#fff8f6;border:1.5px solid rgba(212,86,26,0.3);border-radius:10px;padding:1rem 1.25rem;font-size:13px;color:#8B2200;margin-bottom:1.25rem">
      ⚠️ The <code>dbo.payments</code> table does not exist yet.
    </div>
    <?php else: ?>

    <?php if (!$hasVerifyCols): ?>
    <div style="background:#fff8f6;border:1.5px solid rgba(212,86,26,0.3);border-radius:10px;padding:1rem 1.25rem;font-size:13px;color:#8B2200;margin-bottom:1.25rem">
      ⚠️ Run <strong>solemate_customer_payments_migration.sql</strong> in SSMS to enable verifying EFTs and other transactions.
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.25rem">
      <div class="stat-card"><div class="stat-n" style="color:var(--success)">R<?= number_format($payStats['revenue'],0) ?></div><div class="stat-l">Verified revenue</div></div>
      <div class="stat-card"><div class="stat-n"><?= $payStats['total'] ?></div><div class="stat-l">Completed payments</div></div>
      <div class="stat-card"><div class="stat-n" style="color:var(--accent)"><?= $payStats['pending'] ?></div><div class="stat-l">Awaiting verification</div></div>
    </div>

    <div style="overflow-x:auto">
      <table class="inv-table">
        <thead><tr>
          <th>Cart ref</th><th>Customer</th><th style="text-align:right">Amount</th>
          <th>Method</th><th>Status</th><th>Date</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td style="font-family:monospace;font-size:12px"><?= eh($p['cart_ref']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= eh($p['customer_email']) ?></td>
          <td style="text-align:right;font-family:'Playfair Display',serif">R<?= number_format((float)$p['amount_gross'],2) ?></td>
          <td style="font-size:12px;text-transform:uppercase;color:var(--muted)"><?= eh($p['payment_method'] ?? '—') ?></td>
          <td>
            <?php if ($p['status']==='complete'): ?>
              <span class="inv-badge inv-badge-ok">Complete</span>
              <?php if (!empty($p['verified_by'])): ?>
                <div style="font-size:10px;color:var(--muted);margin-top:2px">Verified by <?= eh($p['verified_by']) ?></div>
              <?php endif; ?>
            <?php elseif ($p['status']==='pending'): ?>
              <span class="inv-badge inv-badge-low">Pending</span>
            <?php else: ?>
              <span class="inv-badge inv-badge-out"><?= eh(ucfirst($p['status'])) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= eh(date('d M Y', strtotime($p['created_at']))) ?></td>
          <td>
            <?php if ($hasVerifyCols && $p['status']==='pending'): ?>
            <div style="display:flex;gap:.4rem">
              <form method="POST" onsubmit="return confirm('Mark this payment as verified and complete? This will finalise the linked booking.')">
                <input type="hidden" name="cart_ref" value="<?= eh($p['cart_ref']) ?>">
                <button type="submit" name="verify_payment" class="save-btn" style="padding:5px 12px;font-size:11px">Verify</button>
              </form>
              <form method="POST" onsubmit="return confirm('Mark this payment as failed?')">
                <input type="hidden" name="cart_ref" value="<?= eh($p['cart_ref']) ?>">
                <button type="submit" name="reject_payment"
                  style="background:none;border:1px solid rgba(212,86,26,0.25);color:var(--accent);padding:5px 10px;border-radius:6px;font-size:11px;cursor:pointer">Reject</button>
              </form>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No payments yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div><!-- /tab-payments -->

  <!-- Edit customer modal -->
  <div class="modal-overlay" id="customerModal">
    <div class="modal" style="max-width:420px">
      <button class="modal-close" onclick="closeCustomerModal()">✕</button>
      <div class="modal-title">Edit customer</div>
      <form method="POST">
        <input type="hidden" name="customer_id" id="cmCustomerId">
        <div class="fg"><label>First name</label><input type="text" name="first_name" id="cmFirstName" required></div>
        <div class="fg"><label>Last name</label><input type="text" name="last_name" id="cmLastName"></div>
        <div class="fg"><label>Email</label><input type="email" name="email" id="cmEmail" required></div>
        <div class="fg"><label>Phone</label><input type="text" name="phone" id="cmPhone"></div>
        <button type="submit" name="save_customer" class="save-btn" style="width:100%;padding:11px">Save changes</button>
      </form>
    </div>
  </div>

  <!-- ══ SERVICES TAB ════════════════════════════════════ -->
  <div class="tab-panel" id="tab-services">
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Icon</th>
            <th>Service</th>
            <th>Description</th>
            <th>Price</th>
            <th>Pricing type</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($services as $s): ?>
          <tr>
            <td><?= $s['id'] ?></td>
            <td class="svc-icon"><?= $s['icon'] ?></td>
            <td><strong><?= eh($s['service_name']) ?></strong></td>
            <td style="color:var(--muted);font-size:12px;max-width:260px"><?= eh($s['description']) ?></td>
            <td class="price-cell">R<?= number_format($s['price'], 2) ?></td>
            <td>
              <?php if ($s['price_from']): ?>
                <span style="color:var(--muted);font-size:12px">from (variable)</span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px">fixed per pair</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:12px;color:var(--muted);margin-top:0.75rem">To modify services, edit the <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px">services</code> table directly in phpMyAdmin.</p>
  </div><!-- /tab-services -->

  <!-- ══ SETTINGS TAB ════════════════════════════════════ -->
  <div class="tab-panel" id="tab-settings">

    <!-- Account info -->
    <div class="info-grid">
      <div class="info-card">
        <div class="info-label">Signed in as</div>
        <div class="info-value"><?= $adminName ?></div>
        <div class="muted-text" style="margin-top:3px"><?= eh($_SESSION['admin_username'] ?? '') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Session started</div>
        <div class="info-value"><?= date('d M Y, H:i') ?></div>
        <div class="muted-text" style="margin-top:3px">Active session</div>
      </div>
    </div>

    <!-- Feature toggles -->
    <div class="settings-section">
      <div class="settings-title">Feature toggles</div>
      <p style="font-size:12px;color:var(--muted);margin:-.5rem 0 1rem">Switch these off to hide them from both customers and this dashboard.</p>
      <form method="POST">
        <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.25rem">
          <label style="display:flex;align-items:center;gap:.6rem;font-size:13px;cursor:pointer">
            <input type="checkbox" name="feat_gift_cards" <?= $settings['gift_cards'] ? 'checked' : '' ?>> Gift cards
          </label>
          <label style="display:flex;align-items:center;gap:.6rem;font-size:13px;cursor:pointer">
            <input type="checkbox" name="feat_coupons" <?= $settings['coupons'] ? 'checked' : '' ?>> Coupons
          </label>
          <label style="display:flex;align-items:center;gap:.6rem;font-size:13px;cursor:pointer">
            <input type="checkbox" name="feat_loyalty" <?= $settings['loyalty'] ? 'checked' : '' ?>> Loyalty points — customer balances
          </label>
          <label style="display:flex;align-items:center;gap:.6rem;font-size:13px;cursor:pointer">
            <input type="checkbox" name="feat_referrals" <?= $settings['referrals'] ? 'checked' : '' ?>> Referral programme
          </label>
        </div>
        <button type="submit" name="save_feature_settings" class="save-btn">Save feature settings</button>
      </form>
    </div>

    <!-- Change password -->
    <div class="settings-section">
      <div class="settings-title">Change password</div>
      <form method="POST">
        <div class="fg">
          <label>Current password</label>
          <input type="password" name="current_password" placeholder="••••••••" required autocomplete="current-password">
        </div>
        <div class="fg">
          <label>New password</label>
          <input type="password" name="new_password" placeholder="Min. 8 characters" required autocomplete="new-password" minlength="8">
        </div>
        <div class="fg">
          <label>Confirm new password</label>
          <input type="password" name="confirm_password" placeholder="Repeat new password" required autocomplete="new-password">
        </div>
        <button type="submit" name="change_password" class="save-btn">Update password</button>
      </form>
    </div>

    <!-- Info note -->
    <div style="background:rgba(107,66,38,0.05);border:1px solid rgba(107,66,38,0.1);border-radius:9px;padding:1rem 1.125rem;font-size:12px;color:var(--muted);max-width:460px;line-height:1.7">
      <strong style="color:var(--dark)">Security notes</strong><br>
      Passwords are stored as bcrypt hashes — never in plain text.<br>
      For production: restrict <code style="background:var(--light);padding:1px 5px;border-radius:3px">admin.php</code> and <code style="background:var(--light);padding:1px 5px;border-radius:3px">admin_login.php</code> by IP in your web server config.<br>
      To add more admin users, insert rows into the <code style="background:var(--light);padding:1px 5px;border-radius:3px">admin_users</code> table with a bcrypt-hashed password.
    </div>

  </div><!-- /tab-settings -->

  <!-- ══ BACKUP TAB ═════════════════════════════════════ -->
  <div class="tab-panel" id="tab-backup">

    <div class="settings-section" style="max-width:560px;">
      <div class="settings-title">Database Backup</div>
      <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:1.25rem;">
        Download a full SQL dump of all tables — <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px;">services</code>,
        <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px;">booking</code>,
        <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px;">reviews</code>, and
        <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px;">admin_users</code>.
        The file can be restored in SSMS by running it against <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px;">shoe_repair_db</code>
        after the schema is in place.
      </p>

      <a href="solemate_backup.php"
         style="display:inline-flex;align-items:center;gap:8px;
                background:var(--dark);color:var(--tan);text-decoration:none;
                font-size:13px;font-weight:500;padding:11px 22px;
                border-radius:8px;transition:opacity 0.15s;"
         onmouseover="this.style.opacity='.82'" onmouseout="this.style.opacity='1'">
        ⬇ Download SQL backup
      </a>

      <p style="font-size:11px;color:var(--muted);margin-top:1rem;line-height:1.7;">
        The file is named <strong>solemate_backup_YYYY-MM-DD_HHmmss.sql</strong> and includes
        <code style="background:var(--light);padding:1px 5px;border-radius:3px;font-size:10px;">SET IDENTITY_INSERT</code>
        guards so IDs are preserved on restore. Schedule regular backups manually or automate with a CRON job.
      </p>
    </div>

    <!-- Restore note -->
    <div style="background:rgba(212,86,26,0.05);border:1px solid rgba(212,86,26,0.15);
                border-radius:9px;padding:1rem 1.125rem;font-size:12px;color:var(--muted);
                max-width:560px;line-height:1.8;">
      <strong style="color:var(--dark);">How to restore</strong><br>
      1. Open SSMS and connect to your SQL Server instance.<br>
      2. Make sure <code style="background:var(--light);padding:1px 5px;border-radius:3px">shoe_repair_db</code> exists and the schema is applied (<code style="background:var(--light);padding:1px 5px;border-radius:3px">SQLQuery1.sql</code>).<br>
      3. Open the backup <code style="background:var(--light);padding:1px 5px;border-radius:3px">.sql</code> file in SSMS and execute it.<br>
      4. All rows will be re-inserted with their original IDs preserved.
    </div>

  </div><!-- /tab-backup -->

  <!-- ══ ANALYTICS TAB ═══════════════════════════════════ -->
  <div class="tab-panel" id="tab-analytics">

    <!-- KPI cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">
      <div class="stat-card">
        <div class="stat-n" style="color:var(--success)">R<?= number_format($revenueTotal,0) ?></div>
        <div class="stat-l">All-time revenue</div>
      </div>
      <div class="stat-card">
        <div class="stat-n">R<?= number_format($revenueThisMonth,0) ?></div>
        <div class="stat-l">Revenue this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-n"><?= $ordersThisMonth ?></div>
        <div class="stat-l">Orders this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-n">R<?= number_format($avgOrderValue,0) ?></div>
        <div class="stat-l">Avg. order value</div>
      </div>
    </div>

    <!-- Revenue over time -->
    <div class="an-card" style="margin-bottom:1.25rem">
      <div class="an-title">Revenue &amp; Orders — last 12 months</div>
      <div style="position:relative;height:260px">
        <canvas id="chartMonthly"></canvas>
      </div>
    </div>

    <!-- Two-col row: service revenue + status -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem">
      <div class="an-card">
        <div class="an-title">Revenue by service</div>
        <div style="position:relative;height:240px">
          <canvas id="chartService"></canvas>
        </div>
      </div>
      <div class="an-card">
        <div class="an-title">Order status breakdown</div>
        <div style="position:relative;height:240px;display:flex;align-items:center;justify-content:center">
          <canvas id="chartStatus"></canvas>
        </div>
      </div>
    </div>

    <!-- Two-col row: collection + sizes -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem">
      <div class="an-card">
        <div class="an-title">Collection method</div>
        <div style="position:relative;height:220px;display:flex;align-items:center;justify-content:center">
          <canvas id="chartCollection"></canvas>
        </div>
      </div>
      <div class="an-card">
        <div class="an-title">Most popular custom shoe sizes</div>
        <?php if (empty($sizeCounts)): ?>
          <p style="font-size:13px;color:var(--muted);margin-top:0.5rem">No custom shoe size data yet.</p>
        <?php else: ?>
          <div style="position:relative;height:220px">
            <canvas id="chartSizes"></canvas>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Most popular colours -->
    <div class="an-card">
      <div class="an-title">Most popular custom colours</div>
      <?php if (empty($topColours)): ?>
        <p style="font-size:13px;color:var(--muted);margin-top:0.5rem">No custom colour data yet — appears once custom shoe orders are placed.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-top:0.25rem">
          <?php foreach (array_slice($topColours,0,24,true) as $hex => $cnt):
            $safe = htmlspecialchars($hex,ENT_QUOTES,'UTF-8');
            // Determine if light or dark for text
            $r = hexdec(substr(ltrim($hex,'#'),0,2));
            $g = hexdec(substr(ltrim($hex,'#'),2,2));
            $b2= hexdec(substr(ltrim($hex,'#'),4,2));
            $lum = (0.299*$r + 0.587*$g + 0.114*$b2);
            $textCol = $lum > 160 ? '#1A0F08' : '#ffffff';
          ?>
          <div title="<?= $safe ?> · used <?= $cnt ?> time(s)"
               style="display:flex;flex-direction:column;align-items:center;gap:4px">
            <div style="width:44px;height:44px;border-radius:50%;background:<?= $safe ?>;
                        border:2px solid rgba(0,0,0,0.08);
                        display:flex;align-items:center;justify-content:center;
                        font-size:11px;font-weight:600;color:<?= $textCol ?>;color-scheme:only light">
              <?= $cnt ?>
            </div>
            <div style="font-size:10px;color:var(--muted);font-family:monospace"><?= $safe ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /tab-analytics -->

  <!-- ══ INVENTORY TAB ════════════════════════════════════ -->
  <div class="tab-panel" id="tab-inventory">

    <?php if (!$hasInventory): ?>
    <div style="background:#fff8f6;border:1.5px solid rgba(212,86,26,0.3);border-radius:10px;padding:1rem 1.25rem;font-size:13px;color:#8B2200;margin-bottom:1.25rem">
      ⚠️ The <code>dbo.inventory</code> table does not exist yet. Run <strong>solemate_inventory_migration.sql</strong> in SSMS first.
    </div>
    <?php else: ?>

    <!-- Summary row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.25rem">
      <div class="stat-card">
        <div class="stat-n"><?= count($inventory) ?></div>
        <div class="stat-l">Total items</div>
      </div>
      <div class="stat-card">
        <div class="stat-n" style="color:var(--accent)"><?= count($invLow) ?></div>
        <div class="stat-l">Low stock</div>
      </div>
      <div class="stat-card">
        <div class="stat-n" style="color:#c0392b"><?= count($invOut) ?></div>
        <div class="stat-l">Out of stock</div>
      </div>
      <div class="stat-card">
        <div class="stat-n" style="color:var(--success)">
          R<?= number_format(array_sum(array_map(fn($i)=>(float)$i['quantity']*(float)$i['cost_per_unit'],$inventory)),0) ?>
        </div>
        <div class="stat-l">Stock value</div>
      </div>
    </div>

    <!-- Add item toggle -->
    <button class="inv-add-toggle" onclick="document.getElementById('invAddForm').classList.toggle('inv-open')">
      ＋ Add inventory item
    </button>

    <!-- Add item form -->
    <div class="inv-add-form" id="invAddForm">
      <strong style="font-size:13px;display:block;margin-bottom:0.875rem">New item</strong>
      <form method="POST">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0.625rem;margin-bottom:0.625rem">
          <div><label class="inv-label">Item name *</label>
            <input type="text" name="inv_name" required placeholder="e.g. Sneaker Cleaner Solution" class="inv-input"></div>
          <div><label class="inv-label">Category</label>
            <select name="inv_category" class="inv-input">
              <option>Cleaning Supplies</option><option>Adhesives</option>
              <option>Sole Materials</option><option>Leather Care</option>
              <option>Thread &amp; Stitching</option><option>Packaging</option><option>General</option>
            </select></div>
          <div><label class="inv-label">Unit</label>
            <input type="text" name="inv_unit" placeholder="bottle / sheet / unit" class="inv-input" value="unit"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 2fr;gap:0.625rem;margin-bottom:0.875rem">
          <div><label class="inv-label">Qty</label>
            <input type="number" name="inv_qty" step="0.5" min="0" class="inv-input" value="0"></div>
          <div><label class="inv-label">Reorder at</label>
            <input type="number" name="inv_reorder" step="0.5" min="0" class="inv-input" value="5"></div>
          <div><label class="inv-label">Cost / unit (R)</label>
            <input type="number" name="inv_cost" step="0.01" min="0" class="inv-input" value="0"></div>
          <div><label class="inv-label">Supplier</label>
            <input type="text" name="inv_supplier" placeholder="Supplier name" class="inv-input"></div>
        </div>
        <div style="display:flex;gap:0.625rem;align-items:center">
          <button type="submit" name="add_inventory" class="save-btn" style="padding:9px 20px;font-size:13px">Add item</button>
          <button type="button" class="inv-cancel" onclick="document.getElementById('invAddForm').classList.remove('inv-open')">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Inventory table -->
    <div style="overflow-x:auto">
      <table class="inv-table">
        <thead>
          <tr>
            <th>Item</th><th>Category</th><th style="text-align:right">Qty</th>
            <th>Unit</th><th style="text-align:right">Reorder</th>
            <th style="text-align:right">Cost/unit</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $prevCat = null;
          foreach ($inventory as $inv):
            $qty     = (float)$inv['quantity'];
            $reorder = (float)$inv['reorder_level'];
            $isOut   = $qty <= 0;
            $isLow   = !$isOut && $qty <= $reorder;
            if ($inv['category'] !== $prevCat):
              $prevCat = $inv['category'];
          ?>
          <tr>
            <td colspan="8" style="background:rgba(107,66,38,0.05);padding:6px 12px;
              font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.6px">
              <?= eh($inv['category']) ?>
            </td>
          </tr>
          <?php endif; ?>
          <tr class="<?= $isOut?'inv-row-out':($isLow?'inv-row-low':'') ?>">
            <td style="font-weight:500"><?= eh($inv['item_name']) ?>
              <?php if ($inv['supplier']): ?>
                <div style="font-size:11px;color:var(--muted)"><?= eh($inv['supplier']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--muted)"><?= eh($inv['category']) ?></td>
            <td style="text-align:right;font-family:'Playfair Display',serif;font-size:15px;
                color:<?= $isOut?'#c0392b':($isLow?'var(--accent)':'var(--dark)') ?>">
              <?= rtrim(rtrim(number_format($qty,2),'0'),'.') ?>
            </td>
            <td style="font-size:12px;color:var(--muted)"><?= eh($inv['unit']) ?></td>
            <td style="text-align:right;font-size:12px;color:var(--muted)"><?= rtrim(rtrim(number_format($reorder,2),'0'),'.') ?></td>
            <td style="text-align:right;font-size:13px">R<?= number_format($inv['cost_per_unit'],2) ?></td>
            <td>
              <?php if ($isOut): ?>
                <span class="inv-badge inv-badge-out">Out of stock</span>
              <?php elseif ($isLow): ?>
                <span class="inv-badge inv-badge-low">Low stock</span>
              <?php else: ?>
                <span class="inv-badge inv-badge-ok">In stock</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:0.4rem">
                <!-- Inline adjust form -->
                <form method="POST" style="display:flex;align-items:center;gap:4px">
                  <input type="hidden" name="inv_id" value="<?= (int)$inv['id'] ?>">
                  <input type="number" name="new_qty" value="<?= $qty ?>" step="0.5" min="0"
                         style="width:64px;border:1px solid rgba(107,66,38,0.2);border-radius:6px;
                                padding:4px 7px;font-size:12px;font-family:inherit;
                                background:var(--cream);color:var(--dark);outline:none">
                  <button type="submit" name="adjust_stock"
                          style="background:var(--dark);color:var(--tan);border:none;
                                 padding:5px 10px;border-radius:6px;font-size:11px;
                                 cursor:pointer;white-space:nowrap">Set</button>
                </form>
                <form method="POST" onsubmit="return confirm('Remove <?= eh(addslashes($inv['item_name'])) ?>?')">
                  <input type="hidden" name="inv_id" value="<?= (int)$inv['id'] ?>">
                  <button type="submit" name="delete_inventory"
                          style="background:none;border:1px solid rgba(212,86,26,0.25);
                                 color:var(--accent);padding:5px 8px;border-radius:6px;
                                 font-size:11px;cursor:pointer">✕</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php endif; ?>
  </div><!-- /tab-inventory -->

  <!-- ══ CARE PRODUCTS TAB ════════════════════════════════ -->
  <div class="tab-panel" id="tab-care">
    <?php if (!$hasCare): ?>
    <div style="background:#fff8f6;border:1px solid rgba(212,86,26,0.3);border-radius:10px;padding:0.9rem 1.1rem;font-size:13px;color:#8B2200;margin-bottom:1rem">
      ⚠️ The <code>dbo.care_products</code> table does not exist yet. Run <strong>solemate_care_products_migration.sql</strong> in SSMS first. Until then the website shows its built-in product list.
    </div>
    <?php else: ?>
    <button class="inv-add-toggle" onclick="document.getElementById('careAddForm').classList.toggle('inv-open')">＋ Add care product</button>
    <div class="inv-add-form" id="careAddForm">
      <strong style="font-size:13px;display:block;margin-bottom:0.875rem">New product</strong>
      <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0.625rem;margin-bottom:0.625rem">
          <div><label class="inv-label">Name *</label><input type="text" name="care_name" required class="inv-input" placeholder="e.g. Foam Cleaner (100ml)"></div>
          <div><label class="inv-label">Price (R) *</label><input type="number" name="care_price" step="0.01" min="0" required class="inv-input"></div>
          <div><label class="inv-label">Icon (emoji)</label><input type="text" name="care_icon" maxlength="8" class="inv-input" placeholder="🧴"></div>
        </div>
        <div style="margin-bottom:0.625rem"><label class="inv-label">Description</label>
          <textarea name="care_desc" rows="2" maxlength="500" class="inv-input" placeholder="Short description (optional)"></textarea></div>
        <div style="margin-bottom:0.875rem">
          <label class="inv-label">Image (JPG/PNG/WEBP, max 3MB)</label><input type="file" name="care_image" accept="image/*" class="inv-input">
        </div>
        <div style="display:flex;gap:0.625rem;align-items:center">
          <button type="submit" name="add_care" class="save-btn" style="padding:9px 20px;font-size:13px">Add product</button>
          <button type="button" class="inv-cancel" onclick="document.getElementById('careAddForm').classList.remove('inv-open')">Cancel</button>
        </div>
      </form>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead><tr><th style="width:64px"></th><th>Product</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($careAdmin as $cp): $cid = (int)$cp['id']; ?>
          <tr>
            <td>
              <?php if (!empty($cp['image_path'])): ?>
                <img src="<?= eh($cp['image_path']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px">
              <?php else: ?>
                <span style="font-size:1.6rem"><?= eh($cp['icon'] ?: '🧴') ?></span>
              <?php endif; ?>
            </td>
            <td><strong><?= eh($cp['name']) ?></strong>
              <?php if (!empty($cp['description'])): ?><div style="color:var(--muted);font-size:12px;max-width:320px"><?= eh($cp['description']) ?></div><?php endif; ?></td>
            <td class="price-cell">R<?= number_format((float)$cp['price'], 2) ?></td>
            <td><span style="font-size:12px;color:<?= $cp['is_active'] ? 'var(--success,#2D6A4F)' : 'var(--muted)' ?>"><?= $cp['is_active'] ? 'Visible' : 'Hidden' ?></span></td>
            <td style="white-space:nowrap">
              <button type="button" class="inv-cancel" onclick="var r=document.getElementById('care-edit-<?= $cid ?>');r.style.display=r.style.display==='none'?'table-row':'none'">Edit</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product?')">
                <input type="hidden" name="care_id" value="<?= $cid ?>">
                <button type="submit" name="delete_care" style="background:none;border:1px solid rgba(212,86,26,0.25);color:var(--accent);padding:5px 8px;border-radius:6px;font-size:11px;cursor:pointer">✕</button>
              </form>
            </td>
          </tr>
          <tr id="care-edit-<?= $cid ?>" style="display:none">
            <td colspan="5" style="background:var(--light)">
              <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="care_id" value="<?= $cid ?>">
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0.625rem;margin-bottom:0.625rem">
                  <div><label class="inv-label">Name *</label><input type="text" name="care_name" required class="inv-input" value="<?= eh($cp['name']) ?>"></div>
                  <div><label class="inv-label">Price (R) *</label><input type="number" name="care_price" step="0.01" min="0" required class="inv-input" value="<?= eh((string)$cp['price']) ?>"></div>
                  <div><label class="inv-label">Icon (emoji)</label><input type="text" name="care_icon" maxlength="8" class="inv-input" value="<?= eh($cp['icon']) ?>"></div>
                </div>
                <div style="margin-bottom:0.625rem"><label class="inv-label">Description</label>
                  <textarea name="care_desc" rows="2" maxlength="500" class="inv-input"><?= eh($cp['description']) ?></textarea></div>
                <div style="margin-bottom:0.625rem">
                  <label class="inv-label">Replace image</label><input type="file" name="care_image" accept="image/*" class="inv-input">
                  <?php if (!empty($cp['image_path'])): ?><label style="font-size:12px;display:block;margin-top:4px"><input type="checkbox" name="care_remove_img" value="1"> Remove current image</label><?php endif; ?>
                </div>
                <label style="font-size:12px;display:block;margin-bottom:0.75rem"><input type="checkbox" name="care_active" value="1" <?= $cp['is_active'] ? 'checked' : '' ?>> Visible on website</label>
                <button type="submit" name="update_care" class="save-btn" style="padding:8px 18px;font-size:13px">Save changes</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$careAdmin): ?><tr><td colspan="5" style="color:var(--muted);text-align:center;padding:1.5rem">No care products yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="sec-title" style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin:2rem 0 0.75rem">Care product orders</div>
    <?php if (!careTableExists($pdo, 'care_orders')): ?>
      <div style="color:var(--muted);font-size:13px">Run <strong>solemate_care_orders_migration.sql</strong> to enable shop orders.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Delivery</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($careOrdersAdmin as $co): ?>
          <tr>
            <td><strong><?= eh($co['order_ref']) ?></strong><div style="color:var(--muted);font-size:11px"><?= eh(date('d M Y H:i', strtotime($co['created_at']))) ?></div></td>
            <td><?= eh($co['customer_name']) ?><div style="color:var(--muted);font-size:11px"><?= eh($co['email']) ?><?= $co['phone'] ? ' · ' . eh($co['phone']) : '' ?></div></td>
            <td style="font-size:12px"><?= eh(implode(', ', $careOrderItems[(int)$co['id']] ?? [])) ?></td>
            <td style="font-size:12px"><?= $co['delivery_method'] === 'delivery' ? '🚚 ' . eh($co['delivery_address'] ?? '') : '🏬 Collect in store' ?></td>
            <td class="price-cell">R<?= number_format((float)$co['total'], 2) ?></td>
            <td><span style="font-size:12px;color:<?= $co['payment_status'] === 'paid' ? 'var(--success,#2D6A4F)' : 'var(--muted)' ?>"><?= $co['payment_status'] === 'paid' ? 'Paid' : 'Awaiting payment' ?></span></td>
            <td>
              <form method="POST" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="order_id" value="<?= (int)$co['id'] ?>">
                <select name="order_status" class="inv-input" style="padding:5px 8px;font-size:12px">
                  <?php foreach (CARE_ORDER_STATUSES as $st): ?>
                  <option<?= $co['status'] === $st ? ' selected' : '' ?>><?= eh($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update_care_order" class="save-btn" style="padding:5px 10px;font-size:12px">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$careOrdersAdmin): ?><tr><td colspan="7" style="color:var(--muted);text-align:center;padding:1.5rem">No care orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div><!-- /tab-care -->

  <!-- ══ E-COMMERCE TAB ════════════════════════════════════ -->
  <div class="tab-panel" id="tab-ecommerce">

    <?php if (!$ecomReady): ?>
    <div style="background:#fff8f6;border:1.5px solid rgba(212,86,26,.3);border-radius:10px;padding:1rem 1.25rem;font-size:13px;color:#8B2200;margin-bottom:1.25rem">
      ⚠️ Run <strong>solemate_ecommerce_migration.sql</strong> in SSMS to enable e-commerce features.
    </div>
    <?php else: ?>

    <!-- Payment KPI row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
      <div class="stat-card"><div class="stat-n" style="color:var(--success)">R<?= number_format($payStats['revenue'],0) ?></div><div class="stat-l">Online revenue</div></div>
      <div class="stat-card"><div class="stat-n"><?= $payStats['total'] ?></div><div class="stat-l">Paid orders</div></div>
      <div class="stat-card"><div class="stat-n" style="color:var(--accent)"><?= $payStats['pending'] ?></div><div class="stat-l">Pending payments</div></div>
      <?php if ($settings['coupons']): ?><div class="stat-card"><div class="stat-n"><?= count($coupons) ?></div><div class="stat-l">Coupons</div></div><?php endif; ?>
      <?php if ($settings['gift_cards']): ?><div class="stat-card"><div class="stat-n"><?= count($giftCards) ?></div><div class="stat-l">Gift cards</div></div><?php endif; ?>
      <?php if ($settings['loyalty']): ?><div class="stat-card"><div class="stat-n"><?= count($loyalCustomers) ?></div><div class="stat-l">Loyalty members</div></div><?php endif; ?>
    </div>

    <!-- ── COUPONS ── -->
    <?php if ($settings['coupons']): ?>
    <div class="an-card" style="margin-bottom:1.25rem">
      <div class="an-title">Coupons</div>

      <!-- Add coupon form -->
      <button type="button" class="inv-add-toggle" onclick="document.getElementById('addCouponForm').classList.toggle('inv-open')" style="margin-bottom:.75rem">＋ Create coupon</button>
      <div class="inv-add-form" id="addCouponForm">
        <form method="POST">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:.625rem;margin-bottom:.625rem">
            <div><label class="inv-label">Code *</label><input type="text" name="cp_code" class="inv-input" placeholder="WELCOME10" style="text-transform:uppercase" required maxlength="50"></div>
            <div><label class="inv-label">Type</label>
              <select name="cp_type" class="inv-input">
                <option value="percent">% Percent</option>
                <option value="fixed">R Fixed</option>
              </select></div>
            <div><label class="inv-label">Value *</label><input type="number" name="cp_value" class="inv-input" placeholder="10" step="0.01" min="0.01" required></div>
            <div><label class="inv-label">Min order (R)</label><input type="number" name="cp_min" class="inv-input" placeholder="0" step="1" min="0" value="0"></div>
            <div><label class="inv-label">Max uses (0=∞)</label><input type="number" name="cp_max" class="inv-input" placeholder="0" min="0" value="0"></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr auto auto auto;gap:.625rem;align-items:flex-end">
            <div><label class="inv-label">Starts (optional)</label><input type="date" name="cp_from" class="inv-input"></div>
            <div><label class="inv-label">Expires</label><input type="date" name="cp_until" class="inv-input" min="<?= date('Y-m-d') ?>"></div>
            <button type="submit" name="add_coupon" class="save-btn" style="padding:9px 20px;font-size:13px">Create</button>
            <button type="button" class="inv-cancel" onclick="document.getElementById('addCouponForm').classList.remove('inv-open')">Cancel</button>
          </div>
        </form>
      </div>

      <?php if (empty($coupons)): ?>
      <p style="font-size:13px;color:var(--muted);margin-top:.5rem">No coupons yet.</p>
      <?php else: ?>
      <div style="overflow-x:auto;margin-top:.25rem">
        <table class="inv-table">
          <thead><tr>
            <th>Code</th><th>Type</th><th style="text-align:right">Value</th>
            <th style="text-align:right">Min order</th><th style="text-align:right">Uses</th>
            <th>Starts</th><th>Expires</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($coupons as $cp): ?>
          <tr>
            <td><code style="font-weight:600;letter-spacing:.5px"><?= eh($cp['code']) ?></code></td>
            <td style="font-size:12px;color:var(--muted)"><?= $cp['type']==='percent'?'Percent':'Fixed' ?></td>
            <td style="text-align:right"><?= $cp['type']==='percent' ? eh($cp['value']).'%' : 'R'.number_format($cp['value'],2) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= $cp['min_order']>0?'R'.number_format($cp['min_order'],0):'—' ?></td>
            <td style="text-align:right"><?= (int)$cp['uses_count'] ?><?= $cp['max_uses']>0?' / '.$cp['max_uses']:'' ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= !empty($cp['valid_from']) ? date('d M Y',strtotime($cp['valid_from'])) : '—' ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= $cp['valid_until'] ? date('d M Y',strtotime($cp['valid_until'])) : '—' ?></td>
            <td><span class="inv-badge <?= $cp['is_active']?'inv-badge-ok':'inv-badge-out' ?>"><?= $cp['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <div style="display:flex;gap:.4rem">
                <form method="POST" style="display:inline"><input type="hidden" name="coupon_id" value="<?= (int)$cp['id'] ?>"><button name="toggle_coupon" type="submit" class="inv-cancel" style="padding:5px 10px;font-size:11px"><?= $cp['is_active']?'Disable':'Enable' ?></button></form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete coupon <?= eh(addslashes($cp['code'])) ?>?')"><input type="hidden" name="coupon_id" value="<?= (int)$cp['id'] ?>"><button name="delete_coupon" type="submit" style="background:none;border:1px solid rgba(212,86,26,.25);color:var(--accent);padding:5px 8px;border-radius:6px;font-size:11px;cursor:pointer">✕</button></form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── GIFT CARDS ── -->
    <?php if ($hasGC && $settings['gift_cards']): ?>
    <div class="an-card" style="margin-bottom:1.25rem">
      <div class="an-title">Gift Cards</div>

      <button type="button" class="inv-add-toggle" onclick="document.getElementById('addGcForm').classList.toggle('inv-open')" style="margin-bottom:.75rem">＋ Issue gift card</button>
      <div class="inv-add-form" id="addGcForm">
        <form method="POST">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.625rem;margin-bottom:.625rem">
            <div><label class="inv-label">Amount (R) *</label><input type="number" name="gc_amount" class="inv-input" min="50" step="50" placeholder="500" required></div>
            <div><label class="inv-label">Recipient name *</label><input type="text" name="gc_recipient_name" class="inv-input" placeholder="Siya Khoza" required></div>
            <div><label class="inv-label">Recipient email</label><input type="email" name="gc_recipient_email" class="inv-input" placeholder="siya@example.com"></div>
          </div>
          <div style="margin-bottom:.625rem"><label class="inv-label">Message (optional)</label><input type="text" name="gc_message" class="inv-input" placeholder="Happy birthday!"></div>
          <div style="display:flex;gap:.625rem">
            <button type="submit" name="add_gift_card" class="save-btn" style="padding:9px 20px;font-size:13px">Issue card</button>
            <button type="button" class="inv-cancel" onclick="document.getElementById('addGcForm').classList.remove('inv-open')">Cancel</button>
          </div>
        </form>
      </div>

      <?php if (empty($giftCards)): ?>
      <p style="font-size:13px;color:var(--muted);margin-top:.5rem">No gift cards yet.</p>
      <?php else: ?>
      <div style="overflow-x:auto;margin-top:.25rem">
        <table class="inv-table">
          <thead><tr>
            <th>Code</th><th>Recipient</th>
            <th style="text-align:right">Initial</th><th style="text-align:right">Balance</th>
            <th>Expires</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($giftCards as $gc): ?>
          <tr class="<?= (float)$gc['balance']<=0?'inv-row-out':'' ?>">
            <td><code style="font-weight:600;letter-spacing:.5px;font-size:12px"><?= eh($gc['code']) ?></code></td>
            <td>
              <div style="font-weight:500;font-size:13px"><?= eh($gc['recipient_name']) ?></div>
              <?php if ($gc['recipient_email']): ?><div style="font-size:11px;color:var(--muted)"><?= eh($gc['recipient_email']) ?></div><?php endif; ?>
            </td>
            <td style="text-align:right">R<?= number_format($gc['initial_balance'],0) ?></td>
            <td style="text-align:right;font-weight:600;color:<?= (float)$gc['balance']<=0?'#c0392b':'var(--success)' ?>">R<?= number_format($gc['balance'],2) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= $gc['expires_at']?date('d M Y',strtotime($gc['expires_at'])):'—' ?></td>
            <td><span class="inv-badge <?= $gc['is_active']?'inv-badge-ok':'inv-badge-out' ?>"><?= $gc['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <form method="POST" style="display:inline"><input type="hidden" name="gc_id" value="<?= (int)$gc['id'] ?>"><button name="toggle_gift_card" type="submit" class="inv-cancel" style="padding:5px 10px;font-size:11px"><?= $gc['is_active']?'Disable':'Enable' ?></button></form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── LOYALTY POINTS ── -->
    <?php if ($settings['loyalty']): ?>
    <?php if (!empty($loyalCustomers)): ?>
    <div class="an-card">
      <div class="an-title">Loyalty points — customer balances</div>
      <div style="overflow-x:auto">
        <table class="inv-table">
          <thead><tr>
            <th>Customer</th><th>Email</th>
            <th>Tier</th>
            <th style="text-align:right">Points</th><th style="text-align:right">Value (R)</th>
            <th>Adjust</th>
          </tr></thead>
          <tbody>
          <?php foreach ($loyalCustomers as $lc): ?>
          <tr>
            <td style="font-weight:500"><?= eh($lc['first_name'].' '.$lc['last_name']) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= eh($lc['email']) ?></td>
            <?php
                $tier = getLoyaltyTier((int)$lc['loyalty_points']);
            ?>
            <td>
              <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $tier['bg'] ?>;color:<?= $tier['color'] ?>;border:1px solid <?= $tier['color'] ?>33;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap">
                <?= $tier['icon'] ?> <?= $tier['name'] ?>
              </span>
            </td>
            <td style="text-align:right;font-family:'Playfair Display',serif;font-size:15px"><?= number_format($lc['loyalty_points']) ?></td>
            <td style="text-align:right;font-size:13px;color:var(--success)">R<?= number_format($lc['loyalty_points']*0.10,2) ?></td>
            <td>
              <form method="POST" style="display:flex;align-items:center;gap:.4rem">
                <input type="hidden" name="loyalty_customer_id" value="<?= (int)$lc['id'] ?>">
                <input type="number" name="loyalty_delta" placeholder="±pts" style="width:70px;border:1px solid rgba(107,66,38,.2);border-radius:6px;padding:4px 7px;font-size:12px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none" required>
                <input type="text" name="loyalty_reason" placeholder="Reason" style="width:120px;border:1px solid rgba(107,66,38,.2);border-radius:6px;padding:4px 7px;font-size:12px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none">
                <button type="submit" name="adjust_loyalty" style="background:var(--dark);color:var(--tan);border:none;padding:5px 10px;border-radius:6px;font-size:11px;cursor:pointer">Set</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="an-card">
      <div class="an-title">Loyalty points</div>
      <p style="font-size:13px;color:var(--muted)">No loyalty members yet. Points are earned automatically when customers pay online.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── REFERRALS ─────────────────────────────────────── -->
    <?php if (!empty($hasReferrals) && $settings['referrals']): ?>
    <div class="an-card" style="margin-top:1.25rem">
      <div class="an-title">Referral programme</div>
      <?php if (!empty($referralRows)): ?>
      <div style="overflow-x:auto">
        <table class="inv-table">
          <thead><tr>
            <th>Referrer</th>
            <th>Friend (Referee)</th>
            <th>Status</th>
            <th>Rewarded</th>
            <th>Date</th>
          </tr></thead>
          <tbody>
          <?php foreach ($referralRows as $rr): ?>
          <tr>
            <td>
              <div style="font-weight:500;font-size:13px"><?= eh($rr['referrer_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= eh($rr['referral_code']) ?></div>
            </td>
            <td>
              <div style="font-size:13px"><?= eh($rr['referee_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= eh($rr['referee_email']) ?></div>
            </td>
            <td>
              <?php if ($rr['status'] === 'completed'): ?>
              <span style="background:#f0faf4;color:#1a5c2a;border:1px solid rgba(34,139,34,.2);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600">✅ Completed</span>
              <?php else: ?>
              <span style="background:#fff8f0;color:#8B4500;border:1px solid rgba(212,132,26,.2);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600">⏳ Pending</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <?= $rr['rewarded'] ? '<span style="color:var(--success);font-size:16px">💰</span>' : '<span style="color:var(--muted);font-size:13px">—</span>' ?>
            </td>
            <td style="font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($rr['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p style="font-size:11px;color:var(--muted);margin-top:.75rem">
        💎 Referrers earn <strong>1,000 pts (R100)</strong> for every 3 friends who complete their first booking.
        Pending = friend signed up; Completed = friend paid.
      </p>
      <?php else: ?>
      <p style="font-size:13px;color:var(--muted)">No referrals yet. Referral codes are generated when customers register.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div><!-- /tab-ecommerce -->


</div><!-- /wrap -->

<style>
/* ── Analytics ── */
.stat-card{background:#fff;border-radius:10px;padding:1.125rem 1.25rem;
  box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid rgba(107,66,38,.08)}
.an-card{background:#fff;border-radius:12px;padding:1.25rem 1.5rem;
  box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid rgba(107,66,38,.08)}
.an-title{font-family:'Playfair Display',serif;font-size:14px;font-weight:700;margin-bottom:1rem;color:var(--dark)}
@media(max-width:680px){
  #tab-analytics > div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}
}

/* ── Inventory ── */
.inv-add-toggle{display:inline-flex;align-items:center;gap:6px;background:none;border:none;
  color:var(--accent);font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;
  padding:0;margin-bottom:0.875rem}
.inv-add-toggle:hover{text-decoration:underline}
.inv-add-form{background:rgba(250,247,242,.7);border:1.5px solid rgba(107,66,38,.12);
  border-radius:12px;padding:1.125rem 1.25rem;margin-bottom:1.25rem;display:none}
.inv-add-form.inv-open{display:block}
.inv-label{display:block;font-size:10px;font-weight:500;color:var(--muted);
  text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.inv-input{width:100%;border:1px solid rgba(107,66,38,.2);border-radius:7px;
  padding:7px 10px;font-size:13px;font-family:inherit;background:var(--cream);
  color:var(--dark);outline:none;appearance:none}
.inv-input:focus{border-color:var(--accent);box-shadow:0 0 0 2px rgba(212,86,26,.1)}
.inv-cancel{background:none;border:1px solid rgba(107,66,38,.2);color:var(--muted);
  padding:8px 16px;border-radius:7px;font-size:13px;cursor:pointer;font-family:inherit}
.inv-cancel:hover{border-color:var(--accent);color:var(--accent)}
.inv-table{width:100%;border-collapse:collapse;font-size:13px;min-width:700px}
.inv-table th{font-size:10px;font-weight:500;color:var(--muted);text-transform:uppercase;
  letter-spacing:.5px;padding:6px 12px 8px;text-align:left;
  border-bottom:1px solid rgba(107,66,38,.1)}
.inv-table td{padding:10px 12px;border-bottom:1px solid rgba(107,66,38,.06);vertical-align:middle}
.inv-table tbody tr:hover td{background:rgba(250,247,242,.6)}
.inv-row-low td{background:rgba(212,86,26,.03)}
.inv-row-out td{background:rgba(192,57,43,.04)}
.inv-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:500}
.inv-badge-ok {background:#d4edda;color:#155724}
.inv-badge-low{background:#fff3cd;color:#856404}
.inv-badge-out{background:#f8d7da;color:#721c24}
</style>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

function filterTable() {
  const q      = document.getElementById('searchInput').value.toLowerCase();
  const status = document.getElementById('statusFilter').value;
  document.querySelectorAll('#bookingTable tbody tr').forEach(row => {
    const text    = row.textContent.toLowerCase();
    const rowStat = row.dataset.status || '';
    const matchQ  = !q || text.includes(q);
    const matchS  = !status || rowStat === status;
    row.style.display = (matchQ && matchS) ? '' : 'none';
  });
}

function filterCustomers() {
  const q = document.getElementById('custSearch').value.toLowerCase();
  document.querySelectorAll('#customerTable tbody tr').forEach(row => {
    row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
}

function openCustomerModal(id, firstName, lastName, email, phone) {
  document.getElementById('cmCustomerId').value = id;
  document.getElementById('cmFirstName').value  = firstName;
  document.getElementById('cmLastName').value   = lastName;
  document.getElementById('cmEmail').value      = email;
  document.getElementById('cmPhone').value      = phone;
  document.getElementById('customerModal').classList.add('open');
}
function closeCustomerModal() { document.getElementById('customerModal').classList.remove('open'); }
document.getElementById('customerModal').addEventListener('click', function(e) { if (e.target === this) closeCustomerModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCustomerModal(); });

// Restore active tab from hash
const hash = window.location.hash.replace('#','');
if (['booking','custom','customers','payments','services','settings','backup','analytics','inventory','ecommerce','care'].includes(hash)) {
  const btn = document.querySelector(`.tab-btn[onclick*="${hash}"]`);
  if (btn) switchTab(hash, btn);
}

// ── Chart.js initialisation ─────────────────────────────
(function() {
  const DARK   = '#1A0F08';
  const TAN    = '#C8A97E';
  const ACCENT = '#D4561A';
  const MUTED  = '#8B7355';
  const GREEN  = '#2D6A4F';

  Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";
  Chart.defaults.color       = MUTED;

  // ── Monthly revenue + orders ──────────────────────────
  const monthlyRaw = <?= json_encode(array_values($monthlyData)) ?>;
  const labels   = monthlyRaw.map(r => r.label);
  const revenues = monthlyRaw.map(r => parseFloat(r.revenue));
  const orders   = monthlyRaw.map(r => parseInt(r.orders));

  const ctxM = document.getElementById('chartMonthly');
  if (ctxM) new Chart(ctxM, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Revenue (R)', data: revenues, backgroundColor: 'rgba(212,86,26,0.8)',
          borderRadius: 6, yAxisID: 'y', order: 2 },
        { label: 'Orders', data: orders, type: 'line', borderColor: TAN,
          backgroundColor: 'rgba(200,169,126,0.1)', pointBackgroundColor: TAN,
          tension: 0.35, yAxisID: 'y1', order: 1, pointRadius: 4 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
      scales: {
        y:  { position: 'left',  beginAtZero: true, grid: { color: 'rgba(107,66,38,0.07)' },
              ticks: { callback: v => 'R'+v.toLocaleString() } },
        y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false },
              ticks: { stepSize: 1 } }
      }
    }
  });

  // ── Revenue by service ────────────────────────────────
  const svcRaw = <?= json_encode(array_values($serviceRevenue)) ?>;
  const ctxS = document.getElementById('chartService');
  if (ctxS && svcRaw.length) new Chart(ctxS, {
    type: 'bar',
    data: {
      labels: svcRaw.map(r => r.service_name),
      datasets: [{ label: 'Revenue (R)', data: svcRaw.map(r => parseFloat(r.revenue)),
        backgroundColor: ['rgba(212,86,26,0.8)','rgba(200,169,126,0.8)','rgba(107,66,38,0.7)',
          'rgba(45,106,79,0.7)','rgba(212,86,26,0.5)','rgba(200,169,126,0.5)',
          'rgba(107,66,38,0.4)','rgba(45,106,79,0.4)'],
        borderRadius: 5 }]
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { beginAtZero: true, grid: { color: 'rgba(107,66,38,0.07)' },
             ticks: { callback: v => 'R'+v.toLocaleString() } },
        y: { grid: { display: false } }
      }
    }
  });

  // ── Order status doughnut ─────────────────────────────
  const stRaw = <?= json_encode(array_values($statusBreakdown)) ?>;
  const ctxSt = document.getElementById('chartStatus');
  const stPalette = ['#8B7355','#C8A97E','#D4561A','#6B4226','#2D6A4F','#A0A0A0','#5B8DD9','#7B68C8','#B05CC8','#C87843','#C8A020'];
  if (ctxSt && stRaw.length) new Chart(ctxSt, {
    type: 'doughnut',
    data: {
      labels: stRaw.map(r => r.status),
      datasets: [{ data: stRaw.map(r => parseInt(r.cnt)),
        backgroundColor: stPalette, borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } }
    }
  });

  // ── Collection method doughnut ────────────────────────
  const colRaw = <?= json_encode(array_values($collBreakdown)) ?>;
  const ctxC = document.getElementById('chartCollection');
  if (ctxC && colRaw.length) new Chart(ctxC, {
    type: 'doughnut',
    data: {
      labels: colRaw.map(r => ({ 'drop-off':'Drop-off','courier':'Courier','home':'Home collection' }[r.collection] || r.collection)),
      datasets: [{ data: colRaw.map(r => parseInt(r.cnt)),
        backgroundColor: [ACCENT, TAN, GREEN], borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } }
    }
  });

  // ── Custom shoe sizes ─────────────────────────────────
  const sizeRaw = <?= json_encode($sizeCounts) ?>;
  const sizeKeys = Object.keys(sizeRaw).sort((a,b) => {
    const n = s => parseInt(s.replace(/\D/g,''));
    return n(a) - n(b);
  });
  const ctxSz = document.getElementById('chartSizes');
  if (ctxSz && sizeKeys.length) new Chart(ctxSz, {
    type: 'bar',
    data: {
      labels: sizeKeys,
      datasets: [{ label: 'Orders', data: sizeKeys.map(k => sizeRaw[k]),
        backgroundColor: 'rgba(107,66,38,0.75)', borderRadius: 5 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(107,66,38,0.07)' } }
      }
    }
  });
})();
</script>

</body>
</html>