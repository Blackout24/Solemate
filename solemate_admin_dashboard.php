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

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$flash     = null;

// ── Handle logout ───────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: solemate_admin_login.php?bye=1');
    exit;
}

// ── Handle status update ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id      = (int)$_POST['order_id'];
    $status  = $_POST['status'] ?? '';
    $allowed = ['Pending','Received','Assessment','In service','Quality check','Ready'];
    if ($id > 0 && in_array($status, $allowed)) {
        $pdo->prepare("UPDATE dbo.orders SET status = ? WHERE id = ?")->execute([$status, $id]);

        // Fetch order details to send notification email
        $orderStmt = $pdo->prepare("
            SELECT o.order_ref, o.customer_name, o.email, o.shoe_type, s.service_name
            FROM dbo.orders o
            LEFT JOIN dbo.services s ON o.service_id = s.id
            WHERE o.id = ?
        ");
        $orderStmt->execute([$id]);
        $orderRow = $orderStmt->fetch();

        if ($orderRow && !empty($orderRow['email'])) {
            $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $trackerUrl = $protocol . '://' . $host . '/SoleMate/solemate_homepage.php?page=tracker&oid=' . urlencode($orderRow['order_ref']);
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
        header('Location: solemate_admin_dashboard.php?flash=status_updated&order=' . $id . '&status=' . urlencode($status) . '#orders');
        exit;
    }
    header('Location: solemate_admin_dashboard.php?flash=status_error#orders');
    exit;
}

// ── Handle delete ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $id = (int)$_POST['order_id'];
    if ($id > 0) {
        $pdo->prepare("DELETE FROM dbo.orders WHERE id = ?")->execute([$id]);
        header('Location: solemate_admin_dashboard.php?flash=deleted&order=' . $id . '#orders');
        exit;
    }
    header('Location: solemate_admin_dashboard.php?flash=delete_error#orders');
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
];
$flashKey = $_GET['flash'] ?? '';
if (isset($flashMap[$flashKey])) {
    [$flashType, $flashMsg] = $flashMap[$flashKey];
    $flash = ['type' => $flashType, 'msg' => $flashMsg];
}

// ── Fetch data ──────────────────────────────────────────
$orders = $pdo->query("
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
    FROM dbo.orders o
    LEFT JOIN dbo.services s ON o.service_id = s.id
    ORDER BY o.order_date DESC
")->fetchAll();

$services = $pdo->query("SELECT * FROM dbo.services ORDER BY sort_order")->fetchAll();

// Stats
$total    = count($orders);
$ready   = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'Ready'));
$pending = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'Received'));
$revenue  = array_sum(array_column($orders, 'price'));

$statusColors = [
    'Pending'       => '#A0A0A0',
    'Received'      => '#8B7355',
    'Assessment'    => '#C8A97E',
    'In service'    => '#D4561A',
    'Quality check' => '#6B4226',
    'Ready'         => '#2D6A4F',
];
$allStatuses = ['Pending','Received','Assessment','In service','Quality check','Ready'];

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
  <div class="page-title">Order Management</div>
  <div class="page-sub">Manage all bookings, update statuses, and configure services.</div>

  <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
      <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?> <?= $flash['msg'] ?>
    </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card"><div class="stat-n"><?= $total ?></div><div class="stat-l">Total orders</div></div>
    <div class="stat-card"><div class="stat-n" style="color:var(--success)"><?= $ready ?></div><div class="stat-l">Ready for collection</div></div>
    <div class="stat-card"><div class="stat-n" style="color:var(--brown)"><?= $pending ?></div><div class="stat-l">New / pending</div></div>
    <div class="stat-card"><div class="stat-n" style="color:var(--tan)">R<?= number_format($revenue, 0) ?></div><div class="stat-l">Est. revenue</div></div>
    <div class="stat-card"><div class="stat-n" style="font-size:1.4rem">4.9 ★</div><div class="stat-l">Avg. review score</div></div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('orders',this)">All Orders (<?= $total ?>)</button>
    <button class="tab-btn" onclick="switchTab('services',this)">Services</button>
    <button class="tab-btn" onclick="switchTab('settings',this)">Settings</button>
    <button class="tab-btn" onclick="switchTab('backup',this)">Backup</button>
  </div>

  <!-- ══ ORDERS TAB ══════════════════════════════════════ -->
  <div class="tab-panel active" id="tab-orders">
    <!-- Filter bar -->
    <div style="display:flex;gap:0.5rem;margin-bottom:0.875rem;flex-wrap:wrap;align-items:center">
      <input type="text" id="searchInput" placeholder="Search orders…"
             oninput="filterTable()"
             style="border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:7px 12px;font-size:13px;font-family:inherit;background:#fff;color:var(--dark);outline:none;min-width:200px;flex:1">
      <select id="statusFilter" onchange="filterTable()"
              style="border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:7px 11px;font-size:12px;font-family:inherit;background:#fff;color:var(--dark);outline:none">
        <option value="">All statuses</option>
        <?php foreach ($allStatuses as $s) echo "<option value=\"{$s}\">{$s}</option>"; ?>
      </select>
    </div>

    <div class="tbl-wrap">
      <table id="ordersTable">
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
        <?php foreach ($orders as $o):
          $color = $statusColors[$o['status'] ?? ''] ?? '#999';
          $collLabel = ['drop-off'=>'Drop-off','courier'=>'Courier','home'=>'Home'][$o['collection']] ?? $o['collection'];
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
                  <?php foreach ($allStatuses as $s): ?>
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
        <?php if (empty($orders)): ?>
          <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2.5rem">No orders yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /tab-orders -->

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
        <code style="background:var(--light);padding:1px 6px;border-radius:4px;font-size:11px;">orders</code>,
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

</div><!-- /wrap -->

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
  document.querySelectorAll('#ordersTable tbody tr').forEach(row => {
    const text    = row.textContent.toLowerCase();
    const rowStat = row.dataset.status || '';
    const matchQ  = !q || text.includes(q);
    const matchS  = !status || rowStat === status;
    row.style.display = (matchQ && matchS) ? '' : 'none';
  });
}

// Restore active tab from hash
const hash = window.location.hash.replace('#','');
if (['orders','services','settings','backup'].includes(hash)) {
  const btn = document.querySelector(`.tab-btn[onclick*="${hash}"]`);
  if (btn) switchTab(hash, btn);
}
</script>

</body>
</html>