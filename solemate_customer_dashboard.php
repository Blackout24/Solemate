<?php
// ═══════════════════════════════════════════════════════
//  solemate_customer_dashboard.php  —  Customer Account
//  Tabs: Overview · My Bookings · Saved Designs ·
//        Wishlist · Profile · Addresses
// ═══════════════════════════════════════════════════════
require 'includes/solemate_customer_auth.php';
requireCustomerLogin();
require 'includes/solemate_database_connection.php';
require_once 'includes/solemate_ecommerce.php';

$custId = getCustomerId();
$flash  = null;
$errors = [];
$settings = getSiteSettings($pdo);

// ── Fetch customer record ────────────────────────────────
$custStmt = $pdo->prepare("SELECT * FROM dbo.customers WHERE id=?");
$custStmt->execute([$custId]);
$cust = $custStmt->fetch();
if (!$cust) { logoutCustomer(); header('Location: solemate_customer_login.php'); exit; }

$tab = $_GET['tab'] ?? 'overview';
if (!in_array($tab, ['overview','bookings','care','designs','wishlist','profile','addresses'])) $tab = 'overview';

// ── Table guards ─────────────────────────────────────────
$hasDesigns   = false;
$hasWishlist  = false;
$hasLoyCol    = false;
$hasReferrals = false;
$hasRefunds   = false; // dbo.refund_requests + dbo.booking.refund_status
try {
    $hasDesigns   = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.customer_saved_designs') AND type='U'")->fetchColumn();
    $hasWishlist  = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.customer_wishlist') AND type='U'")->fetchColumn();
    $hasLoyCol    = (bool)$pdo->query("SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.customers') AND name='loyalty_points'")->fetchColumn();
    $hasReferrals = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.referrals') AND type='U'")->fetchColumn();
    $hasRefunds   = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.refund_requests') AND type='U'")->fetchColumn()
                 && (bool)$pdo->query("SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name='refund_status'")->fetchColumn();
} catch (PDOException $e) {}

// Statuses a customer is still allowed to cancel from
$cancellableStatuses = ['Pending','Received','Assessment','In service','Quality check',
    'Design confirmed','Pattern cut','Stitching','Sole bonding','Finishing'];

// ══════════════════════════════════════════════════════
//  POST handlers
// ══════════════════════════════════════════════════════

// ── Update profile ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_profile') {
    $tab = 'profile';
    $fn = trim($_POST['first_name']??''); $ln = trim($_POST['last_name']??'');
    $em = strtolower(trim($_POST['email']??'')); $ph = trim($_POST['phone']??'');
    $np = $_POST['new_password']??''; $cp = $_POST['current_password']??'';
    if (!$fn) $errors[]='First name is required.';
    if (!$ln) $errors[]='Last name is required.';
    if (!$em||!filter_var($em,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email required.';
    if (empty($errors)&&$em!==$cust['email']) {
        $c=$pdo->prepare("SELECT id FROM dbo.customers WHERE email=? AND id!=?");
        $c->execute([$em,$custId]); if($c->fetch()) $errors[]='Email already in use.';
    }
    if ($np) {
        if (!$cp) $errors[]='Enter current password to change it.';
        elseif (!password_verify($cp,$cust['password_hash'])) $errors[]='Current password incorrect.';
        elseif (strlen($np)<8) $errors[]='New password must be 8+ characters.';
        elseif ($np!==($_POST['confirm_new_password']??'')) $errors[]='New passwords do not match.';
    }
    if (empty($errors)) {
        if ($np) {
            $pdo->prepare("UPDATE dbo.customers SET first_name=?,last_name=?,email=?,phone=?,password_hash=? WHERE id=?")
                ->execute([$fn,$ln,$em,$ph,password_hash($np,PASSWORD_BCRYPT),$custId]);
        } else {
            $pdo->prepare("UPDATE dbo.customers SET first_name=?,last_name=?,email=?,phone=? WHERE id=?")
                ->execute([$fn,$ln,$em,$ph,$custId]);
        }
        $_SESSION['customer_name']=$fn.' '.$ln; $_SESSION['customer_email']=$em;
        $flash='profile_saved'; $custStmt->execute([$custId]); $cust=$custStmt->fetch();
    }
}

// ── Add address ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_address') {
    $tab='addresses';
    $street=trim($_POST['addr_street']??'');
    if (!$street) { $errors[]='Street address is required.'; }
    else {
        $isDef=isset($_POST['addr_default'])?1:0;
        if ($isDef) $pdo->prepare("UPDATE dbo.customer_addresses SET is_default=0 WHERE customer_id=?")->execute([$custId]);
        $pdo->prepare("INSERT INTO dbo.customer_addresses (customer_id,label,street,suburb,city,province,postal_code,is_default) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$custId,trim($_POST['addr_label']??'Home'),$street,trim($_POST['addr_suburb']??''),trim($_POST['addr_city']??'Durban'),trim($_POST['addr_province']??'KwaZulu-Natal'),trim($_POST['addr_postal']??''),$isDef]);
        $flash='address_added';
    }
}

// ── Set default address ───────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='set_default') {
    $aid=(int)($_POST['address_id']??0);
    if ($aid>0) {
        $pdo->prepare("UPDATE dbo.customer_addresses SET is_default=0 WHERE customer_id=?")->execute([$custId]);
        $pdo->prepare("UPDATE dbo.customer_addresses SET is_default=1 WHERE id=? AND customer_id=?")->execute([$aid,$custId]);
        $flash='default_set';
    }
    $tab='addresses';
}

// ── Delete address ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete_address') {
    $aid=(int)($_POST['address_id']??0);
    if ($aid>0) $pdo->prepare("DELETE FROM dbo.customer_addresses WHERE id=? AND customer_id=?")->execute([$aid,$custId]);
    $flash='address_deleted'; $tab='addresses';
}

// ── Delete saved design ───────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete_design') {
    $did=(int)($_POST['design_id']??0);
    if ($did>0 && $hasDesigns) $pdo->prepare("DELETE FROM dbo.customer_saved_designs WHERE id=? AND customer_id=?")->execute([$did,$custId]);
    $flash='design_deleted'; $tab='designs';
}

// ── Rename saved design ───────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='rename_design') {
    $did=(int)($_POST['design_id']??0);
    $dn=trim($_POST['design_name']??'');
    if ($did>0 && $dn && $hasDesigns)
        $pdo->prepare("UPDATE dbo.customer_saved_designs SET design_name=?,updated_at=GETDATE() WHERE id=? AND customer_id=?")->execute([$dn,$did,$custId]);
    $flash='design_renamed'; $tab='designs';
}

// ── Remove from wishlist ──────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='remove_wishlist') {
    $sid=(int)($_POST['service_id']??0);
    if ($sid>0 && $hasWishlist) $pdo->prepare("DELETE FROM dbo.customer_wishlist WHERE customer_id=? AND service_id=?")->execute([$custId,$sid]);
    $flash='wishlist_removed'; $tab='wishlist';
}

// ── Cancel booking + request refund ───────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel_booking' && $hasRefunds) {
    $tab = 'bookings';
    $bid = (int)($_POST['booking_id']??0);
    $reason = trim($_POST['cancel_reason']??'');

    $bkStmt = $pdo->prepare("SELECT * FROM dbo.booking WHERE id=? AND (email=? OR customer_id=?)");
    $bkStmt->execute([$bid, $cust['email'], $custId]);
    $booking = $bkStmt->fetch();

    if (!$booking) {
        $errors[] = 'Booking not found.';
    } elseif (!in_array($booking['status'] ?? '', $cancellableStatuses)) {
        $errors[] = 'This booking can no longer be cancelled.';
    } else {
        $wasPaid = ($booking['payment_status'] ?? '') === 'paid';
        $pdo->prepare("UPDATE dbo.booking SET status=N'Cancelled', refund_status=?, cancelled_at=GETDATE(), cancel_reason=? WHERE id=?")
            ->execute([$wasPaid ? 'requested' : null, $reason ?: null, $bid]);

        if ($wasPaid) {
            $amount = (float)($booking['amount_paid'] ?? 0);
            $pdo->prepare("INSERT INTO dbo.refund_requests (booking_id,customer_id,order_ref,cart_ref,customer_email,amount,reason) VALUES (?,?,?,?,?,?,?)")
                ->execute([$bid, $custId, $booking['order_ref'], $booking['cart_ref'] ?? null, $cust['email'], $amount, $reason ?: null]);
            $flash = 'cancel_refund_requested';
        } else {
            $flash = 'cancel_done';
        }
    }
}

if (isset($_GET['welcome'])) $flash='welcome';

// ── Load tab data ─────────────────────────────────────
$bookings=$addresses=$designs=$wishlist=[];
$stats=['total'=>0,'active'=>0,'ready'=>0,'designs'=>0,'wishlist'=>0,'addresses'=>0];
$activeStatuses=['Received','Assessment','In service','Quality check','Design confirmed','Pattern cut','Stitching','Sole bonding','Finishing'];

if (in_array($tab,['overview','bookings'])) {
    $bs=$pdo->prepare("SELECT b.*,s.service_name,s.service_key FROM dbo.booking b LEFT JOIN dbo.services s ON b.service_id=s.id WHERE b.email=? OR b.customer_id=? ORDER BY b.order_date DESC");
    $bs->execute([$cust['email'],$custId]); $bookings=$bs->fetchAll();
    $stats['total']=count($bookings);
    foreach ($bookings as $bk) {
        if (in_array($bk['status']??'',$activeStatuses)) $stats['active']++;
        if (($bk['status']??'')==='Ready') $stats['ready']++;
    }
}
$careOrders = []; $careOrderItems = [];
if ($tab === 'care') {
    try {
        $cos = $pdo->prepare("SELECT * FROM dbo.care_orders WHERE (customer_id=? OR email=?) AND payment_status=N'paid' ORDER BY created_at DESC");
        $cos->execute([$custId, $cust['email']]);
        $careOrders = $cos->fetchAll();
        if ($careOrders) {
            $ids = implode(',', array_map(fn($r) => (int)$r['id'], $careOrders));
            foreach ($pdo->query("SELECT order_id, name, qty, line_total FROM dbo.care_order_items WHERE order_id IN ($ids) ORDER BY id")->fetchAll() as $ci) {
                $careOrderItems[(int)$ci['order_id']][] = $ci;
            }
        }
    } catch (\Throwable $e) { $careOrders = []; }
}
if (in_array($tab,['overview','addresses'])) {
    $as=$pdo->prepare("SELECT * FROM dbo.customer_addresses WHERE customer_id=? ORDER BY is_default DESC, created_at ASC");
    $as->execute([$custId]); $addresses=$as->fetchAll(); $stats['addresses']=count($addresses);
}
if (in_array($tab,['overview','designs']) && $hasDesigns) {
    $ds=$pdo->prepare("SELECT * FROM dbo.customer_saved_designs WHERE customer_id=? ORDER BY updated_at DESC");
    $ds->execute([$custId]); $designs=$ds->fetchAll(); $stats['designs']=count($designs);
}
if (in_array($tab,['overview','wishlist']) && $hasWishlist) {
    $ws=$pdo->prepare("SELECT w.*,s.service_name,s.service_key,s.description,s.price,s.icon,s.price_from,s.id AS sid FROM dbo.customer_wishlist w JOIN dbo.services s ON w.service_id=s.id WHERE w.customer_id=? ORDER BY w.added_at DESC");
    $ws->execute([$custId]); $wishlist=$ws->fetchAll(); $stats['wishlist']=count($wishlist);
}

// ── Loyalty tier + Referrals (Overview only) ───────────
$loyaltyTier   = null;
$referralStats = null;
$referralRows  = [];
if ($tab==='overview') {
    if ($hasLoyCol) $loyaltyTier = getLoyaltyTier((int)$cust['loyalty_points']);
    if ($hasReferrals) {
        $referralStats = getReferralStats($pdo, $custId);
        $rr = $pdo->prepare("
            SELECT r.*, c2.first_name+' '+c2.last_name AS referee_name, c2.email AS referee_email
            FROM dbo.referrals r
            JOIN dbo.customers c2 ON r.referee_id = c2.id
            WHERE r.referrer_id = ?
            ORDER BY r.created_at DESC
        ");
        $rr->execute([$custId]);
        $referralRows = $rr->fetchAll();
    }
}

// All services for booking modal dropdowns
$allServices=$pdo->query("SELECT id,service_name,price,price_from FROM dbo.services ORDER BY sort_order")->fetchAll();

$memberSince=date('F Y',strtotime($cust['created_at']));

function e(string $v): string { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
function statusBadge(string $s): string {
    $m=['Pending'=>['#fff3cd','#856404'],'Received'=>['#d1ecf1','#0c5460'],'Assessment'=>['#e2d9f3','#432874'],
        'In service'=>['#cfe2ff','#084298'],'Quality check'=>['#d1ecf1','#0c5460'],
        'Ready'=>['#d4edda','#155724'],'Dispatched'=>['#d6d8d9','#383d41'],
        'Design confirmed'=>['#e2d9f3','#432874'],'Pattern cut'=>['#cfe2ff','#084298'],
        'Stitching'=>['#cfe2ff','#084298'],'Sole bonding'=>['#cfe2ff','#084298'],'Finishing'=>['#d4edda','#155724'],
        'Cancelled'=>['#f8d7da','#842029']];
    [$bg,$fg]=$m[$s]??['#f8f9fa','#6c757d'];
    return "<span style=\"display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;background:{$bg};color:{$fg}\">{$s}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Account — SoleMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<link rel="icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;--accent:#D4561A;--light:#E8D5BE;--muted:#8B7355;--success:#2D6A4F}
html,body{min-height:100vh;background:var(--cream);color:var(--dark);font-family:'DM Sans',system-ui,sans-serif}
nav{background:var(--dark);padding:0 1.75rem;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(0,0,0,.35)}
.logo{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--tan);text-decoration:none}.logo span{color:var(--accent)}
.nav-right{display:flex;align-items:center;gap:1rem}
.nav-home{color:rgba(255,255,255,.45);font-size:13px;text-decoration:none;transition:color .15s}.nav-home:hover{color:var(--tan)}
.nav-logout{color:rgba(255,255,255,.45);font-size:12px;text-decoration:none;border:1px solid rgba(255,255,255,.15);padding:5px 12px;border-radius:6px;transition:all .15s}.nav-logout:hover{border-color:var(--tan);color:var(--tan)}
.page{max-width:920px;margin:0 auto;padding:2rem 1.5rem 4rem}
.flash{background:#f0faf5;border:1.5px solid rgba(45,106,79,.3);border-radius:10px;padding:12px 16px;margin-bottom:1.5rem;font-size:13px;color:#1a4731;display:flex;align-items:center;gap:10px}
.err-box{background:#fff8f6;border:1.5px solid rgba(212,86,26,.3);border-radius:9px;padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#8B2200;display:flex;align-items:flex-start;gap:8px}
.err-box ul{margin:.25rem 0 0 1rem;line-height:1.9}
.profile-banner{background:var(--dark);border-radius:14px;padding:1.75rem 2rem;margin-bottom:1.75rem;display:flex;align-items:center;gap:1.25rem;box-shadow:0 4px 20px rgba(0,0,0,.15)}
.avatar{width:56px;height:56px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#fff;flex-shrink:0}
.banner-name{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;color:#fff}
.banner-sub{font-size:12px;color:rgba(200,169,126,.6);margin-top:3px}
.tabs{display:flex;gap:4px;margin-bottom:1.75rem;background:rgba(107,66,38,.06);border-radius:10px;padding:4px;flex-wrap:wrap}
.tab{padding:8px 16px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;color:var(--muted);transition:all .15s;border:none;background:none;font-family:inherit;white-space:nowrap}
.tab:hover{color:var(--brown)}.tab.active{background:#fff;color:var(--dark);box-shadow:0 1px 4px rgba(0,0,0,.1)}
.tab-badge{background:var(--accent);color:#fff;font-size:10px;padding:1px 6px;border-radius:20px;margin-left:4px;vertical-align:middle}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.75rem}
.stat-card{background:#fff;border-radius:12px;padding:1.25rem 1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid rgba(107,66,38,.08)}
.stat-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:var(--dark)}
.stat-lbl{font-size:12px;color:var(--muted);margin-top:2px}
.sec-card{background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid rgba(107,66,38,.08);margin-bottom:1.25rem}
.sec-title{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;margin-bottom:1rem}
.orders-table{width:100%;border-collapse:collapse;font-size:13px}
.orders-table th{font-size:10px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:0 12px 10px;text-align:left;border-bottom:1px solid rgba(107,66,38,.1)}
.orders-table td{padding:11px 12px;border-bottom:1px solid rgba(107,66,38,.06);vertical-align:middle}
.orders-table tr:last-child td{border-bottom:none}
.orders-table tbody tr:hover td{background:rgba(250,247,242,.7)}
.order-ref{font-family:monospace;font-size:12px;color:var(--brown);font-weight:600;text-decoration:none}.order-ref:hover{color:var(--accent)}
.empty-state{text-align:center;padding:2.5rem 1rem;color:var(--muted);font-size:14px}
.empty-state a{color:var(--accent);text-decoration:none;font-weight:500}.empty-state a:hover{text-decoration:underline}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.fg{margin-bottom:.875rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.fg input,.fg select,.fg textarea{width:100%;border:1.5px solid rgba(107,66,38,.2);border-radius:9px;padding:10px 14px;font-size:14px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;transition:border-color .18s,box-shadow .18s;appearance:none;resize:vertical}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.fg input::placeholder,.fg textarea::placeholder{color:rgba(107,66,38,.35)}
.pw-wrap{position:relative}.pw-wrap input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:15px;padding:4px}.pw-toggle:hover{color:var(--accent)}
.btn-primary{background:var(--dark);color:var(--tan);border:none;padding:11px 24px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;transition:all .18s}.btn-primary:hover{background:#2a1a10;color:#e5c98e}
.btn-accent{background:var(--accent);color:#fff;border:none;padding:11px 24px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;transition:opacity .15s;text-decoration:none;display:inline-block}.btn-accent:hover{opacity:.88}
.btn-ghost{background:none;border:1.5px solid rgba(107,66,38,.2);color:var(--muted);padding:9px 18px;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit;transition:all .15s}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:6px 14px;font-size:12px;border-radius:7px}
.btn-danger{background:#fff;border:1.5px solid rgba(212,86,26,.3);color:var(--accent);padding:7px 14px;border-radius:7px;font-size:12px;cursor:pointer;font-family:inherit;transition:all .15s}.btn-danger:hover{background:#fff8f6;border-color:var(--accent)}
.form-section{margin-bottom:1.75rem}
.form-section h3{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid rgba(107,66,38,.1)}
.add-toggle{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:14px;font-weight:500;color:var(--accent);background:none;border:none;font-family:inherit;padding:0;margin-bottom:1rem}.add-toggle:hover{text-decoration:underline}
.add-form{background:rgba(250,247,242,.6);border:1.5px solid rgba(107,66,38,.12);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;display:none}.add-form.open{display:block}
.checkbox-row{display:flex;align-items:center;gap:.5rem;margin:-.25rem 0 .875rem;font-size:13px;color:var(--muted);cursor:pointer}.checkbox-row input{width:16px;height:16px;accent-color:var(--accent)}
.addr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-bottom:1.5rem}
.addr-card{background:var(--cream);border:1.5px solid rgba(107,66,38,.12);border-radius:12px;padding:1.25rem;position:relative}.addr-card.is-default{border-color:var(--tan);background:#fffbf5}
.addr-default-badge{display:inline-block;background:var(--tan);color:var(--dark);font-size:10px;font-weight:600;padding:2px 10px;border-radius:20px;margin-bottom:.5rem}
.addr-label{font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.35rem}
.addr-street{font-size:14px;font-weight:500;color:var(--dark);line-height:1.5}
.addr-detail{font-size:13px;color:var(--muted);line-height:1.5}
.addr-actions{display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap}

/* ── Saved Designs ── */
.designs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin-bottom:1.5rem}
.design-card{background:#fff;border:1.5px solid rgba(107,66,38,.1);border-radius:14px;padding:1.25rem;position:relative;transition:box-shadow .15s}.design-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.1)}
.design-swatches{display:flex;gap:5px;margin-bottom:.875rem;flex-wrap:wrap}
.design-swatch{width:22px;height:22px;border-radius:50%;border:2px solid rgba(255,255,255,.8);box-shadow:0 1px 3px rgba(0,0,0,.12);flex-shrink:0;color-scheme:only light}
.design-name{font-family:'Playfair Display',serif;font-size:14px;font-weight:700;margin-bottom:3px}
.design-meta{font-size:11px;color:var(--muted)}
.design-actions{display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap}
.design-rename-form{display:none;margin-top:.625rem}
.design-rename-form.open{display:flex;gap:.5rem}
.design-rename-input{flex:1;border:1.5px solid rgba(107,66,38,.2);border-radius:7px;padding:6px 10px;font-size:13px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none}.design-rename-input:focus{border-color:var(--accent)}

/* ── Wishlist ── */
.wishlist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}
.wish-card{background:#fff;border:1.5px solid rgba(107,66,38,.1);border-radius:14px;padding:1.25rem;display:flex;flex-direction:column;gap:.75rem}
.wish-icon{font-size:2rem;line-height:1}
.wish-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700}
.wish-price{font-size:13px;color:var(--accent);font-weight:500}
.wish-desc{font-size:12px;color:var(--muted);line-height:1.6;flex:1}
.wish-actions{display:flex;gap:.5rem;flex-wrap:wrap}

/* ── Booking modal ── */
.modal-overlay{position:fixed;inset:0;background:rgba(26,15,8,.55);backdrop-filter:blur(3px);z-index:300;display:none;align-items:center;justify-content:center;padding:1.5rem}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:18px;padding:2rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;animation:slideUp .3s cubic-bezier(.16,1,.3,1)}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);padding:4px}.modal-close:hover{color:var(--accent)}
.modal-title{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;margin-bottom:1.25rem}
.design-preview-banner{background:var(--dark);border-radius:10px;padding:.875rem 1rem;margin-bottom:1.25rem;display:flex;gap:.625rem;align-items:center}
.dp-swatches{display:flex;gap:4px;flex-wrap:wrap}
.dp-swatch{width:18px;height:18px;border-radius:50%;border:1.5px solid rgba(255,255,255,.2);flex-shrink:0;color-scheme:only light}

/* ── Expandable order rows ── */
.order-expand-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-size:12px;padding:2px 8px;border-radius:5px;transition:all .12s}.order-expand-btn:hover{background:rgba(107,66,38,.1);color:var(--dark)}
.order-details{display:none;padding:12px 12px 14px;background:rgba(250,247,242,.6);border-bottom:1px solid rgba(107,66,38,.06)}
.order-details.open{display:block}
.od-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.5rem;font-size:12px}
.od-item label{display:block;color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.od-item span{font-weight:500}
.od-swatches{display:flex;gap:4px;margin-top:.5rem;flex-wrap:wrap}
.od-swatch{width:16px;height:16px;border-radius:50%;border:1.5px solid rgba(107,66,38,.15);flex-shrink:0;color-scheme:only light}

@media(max-width:600px){.form-row{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}.orders-table th:nth-child(3),.orders-table td:nth-child(3),.orders-table th:nth-child(5),.orders-table td:nth-child(5){display:none}.profile-banner{padding:1.25rem}.tabs{gap:2px}.tab{font-size:12px;padding:7px 12px}}
</style>
</head>
<body>

<nav>
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <div class="nav-right">
    <a href="solemate_homepage.php" class="nav-home">← Website</a>
    <a href="solemate_customer_logout.php" class="nav-logout">Sign out</a>
  </div>
</nav>

<div class="page">

  <!-- Flash messages -->
  <?php
  $flashes=['welcome'=>'🎉 Welcome, '.e($cust['first_name']).'! Your account is ready.',
    'profile_saved'=>'✅ Profile updated.','address_added'=>'✅ Address saved.',
    'address_deleted'=>'✅ Address removed.','default_set'=>'✅ Default address updated.',
    'design_deleted'=>'✅ Design removed.','design_renamed'=>'✅ Design renamed.',
    'wishlist_removed'=>'✅ Removed from wishlist.',
    'cancel_done'=>'✅ Booking cancelled.',
    'cancel_refund_requested'=>'✅ Booking cancelled — your refund request has been sent to our team.'];
  if ($flash && isset($flashes[$flash])): ?>
  <div class="flash"><span>&#8203;</span><?= $flashes[$flash] ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="err-box"><span style="flex-shrink:0">⚠️</span>
    <div><?= count($errors)===1 ? e($errors[0]) : '<ul>'.implode('',array_map(fn($e)=>'<li>'.e($e).'</li>',$errors)).'</ul>' ?></div>
  </div>
  <?php endif; ?>

  <!-- Profile banner -->
  <div class="profile-banner">
    <div class="avatar"><?= mb_strtoupper(mb_substr($cust['first_name'],0,1)).mb_strtoupper(mb_substr($cust['last_name'],0,1)) ?></div>
    <div>
      <div class="banner-name"><?= e($cust['first_name'].' '.$cust['last_name']) ?></div>
      <div class="banner-sub"><?= e($cust['email']) ?> · Member since <?= $memberSince ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <?php
    $tabLabels=['overview'=>'Overview','bookings'=>'My Bookings','care'=>'Care Orders','designs'=>'Saved Designs','wishlist'=>'Wishlist','profile'=>'Profile','addresses'=>'Addresses'];
    foreach ($tabLabels as $k=>$l): ?>
    <a href="?tab=<?= $k ?>" class="tab<?= $tab===$k?' active':'' ?>"><?= $l ?>
      <?php if ($k==='bookings'&&$stats['ready']>0): ?><span class="tab-badge"><?= $stats['ready'] ?></span><?php endif; ?>
      <?php if ($k==='wishlist'&&$stats['wishlist']>0): ?><span class="tab-badge" style="background:var(--brown)"><?= $stats['wishlist'] ?></span><?php endif; ?>
      <?php if ($k==='designs'&&$stats['designs']>0): ?><span class="tab-badge" style="background:var(--muted)"><?= $stats['designs'] ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ════════════════════ OVERVIEW ════════════════════ -->
  <?php if ($tab==='overview'): ?>
  <div class="stats">
    <div class="stat-card"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-lbl">Total orders</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['active'] ?></div><div class="stat-lbl">In progress</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $stats['ready']>0?'var(--success)':'var(--dark)' ?>"><?= $stats['ready'] ?></div><div class="stat-lbl">Ready</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['designs'] ?></div><div class="stat-lbl">Saved designs</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['wishlist'] ?></div><div class="stat-lbl">Wishlist</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['addresses'] ?></div><div class="stat-lbl">Addresses</div></div>
  </div>

  <?php if ($stats['ready']>0): ?>
  <div class="flash" style="background:#f0faf5">🎉 <?= $stats['ready'] ?> order<?= $stats['ready']>1?'s are':' is'?> ready for collection! <a href="?tab=bookings" style="color:var(--success);font-weight:600;text-decoration:none;margin-left:6px">View →</a></div>
  <?php endif; ?>

  <!-- ── Loyalty & Rewards ── -->
  <?php if ($loyaltyTier && $settings['loyalty']): ?>
  <div class="sec-card">
    <div class="sec-title">Loyalty &amp; rewards</div>
    <table class="orders-table">
      <thead><tr><th>Tier</th><th style="text-align:right">Points</th><th style="text-align:right">Value (R)</th><th>Progress</th></tr></thead>
      <tbody>
        <tr>
          <td>
            <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $loyaltyTier['bg'] ?>;color:<?= $loyaltyTier['color'] ?>;border:1px solid <?= $loyaltyTier['color'] ?>33;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap">
              <?= $loyaltyTier['icon'] ?> <?= e($loyaltyTier['name']) ?>
            </span>
          </td>
          <td style="text-align:right;font-family:'Playfair Display',serif;font-size:15px"><?= number_format((int)$cust['loyalty_points']) ?></td>
          <td style="text-align:right;font-size:13px;color:var(--success)">R<?= number_format($cust['loyalty_points']*0.10,2) ?></td>
          <td style="min-width:160px">
            <?php if ($loyaltyTier['next']): ?>
            <div style="background:rgba(107,66,38,.1);border-radius:20px;height:7px;overflow:hidden"><div style="background:<?= $loyaltyTier['color'] ?>;height:100%;width:<?= $loyaltyTier['progress_pct'] ?>%"></div></div>
            <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= $loyaltyTier['points_to_next'] ?> pts to <?= e($loyaltyTier['next']) ?></div>
            <?php else: ?>
            <div style="font-size:11px;color:var(--muted)">Highest tier reached 🎉</div>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Referrals ── -->
  <?php if ($hasReferrals && $settings['referrals']): ?>
  <div class="sec-card">
    <div class="sec-title">Referrals</div>
    <?php if ($referralStats && $referralStats['code']): ?>
    <div style="font-size:13px;color:var(--muted);margin-bottom:1rem">
      Your referral code: <strong style="color:var(--dark);font-family:monospace"><?= e($referralStats['code']) ?></strong>
      · <?= (int)$referralStats['completed'] ?> completed · <?= (int)$referralStats['pending'] ?> pending
      <?php if ($referralStats['next_reward_at']>0): ?> · <?= (int)$referralStats['next_reward_at'] ?> more friend<?= $referralStats['next_reward_at']>1?'s':'' ?> to your next R100<?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($referralRows)): ?>
    <table class="orders-table">
      <thead><tr><th>Referrer</th><th>Friend (Referee)</th><th>Date</th><th>Status</th><th>Rewarded</th></tr></thead>
      <tbody>
        <?php foreach ($referralRows as $rr): ?>
        <tr>
          <td>
            <div style="font-weight:500;font-size:13px"><?= e($cust['first_name'].' '.$cust['last_name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= e($referralStats['code']??'') ?></div>
          </td>
          <td>
            <div style="font-size:13px"><?= e($rr['referee_name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= e($rr['referee_email']) ?></div>
          </td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= e(date('d M Y',strtotime($rr['created_at']))) ?></td>
          <td>
            <?php if ($rr['status']==='completed'): ?>
            <span style="background:#f0faf4;color:#1a5c2a;border:1px solid rgba(34,139,34,.2);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600">✅ Completed</span>
            <?php else: ?>
            <span style="background:#fff8f0;color:#8B4500;border:1px solid rgba(212,132,26,.2);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600">⏳ Pending</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center"><?= $rr['rewarded'] ? '<span style="color:var(--success);font-size:16px">💰</span>' : '<span style="color:var(--muted);font-size:13px">—</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:11px;color:var(--muted);margin-top:.75rem">💎 Earn <strong>R100</strong> for every 3 friends who complete their first booking.</p>
    <?php else: ?>
    <div class="empty-state"><div style="font-size:2.5rem;margin-bottom:.75rem">🤝</div><div style="font-weight:500;margin-bottom:.5rem">No referrals yet</div>Share your code above to start earning.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($bookings)): ?>
  <div class="sec-card">
    <div class="sec-title">Recent orders</div>
    <table class="orders-table"><thead><tr><th>Order ref</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($bookings,0,4) as $bk): ?>
      <tr>
        <td><a href="solemate_homepage.php?page=tracker&oid=<?= e($bk['order_ref']) ?>" class="order-ref" target="_blank"><?= e($bk['order_ref']) ?></a></td>
        <td><?= e($bk['service_name']??'Shoe service') ?></td>
        <td><?= e(date('d M Y',strtotime($bk['order_date']))) ?></td>
        <td><?= statusBadge($bk['status']??'') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php if (count($bookings)>4): ?><div style="margin-top:.875rem;text-align:right"><a href="?tab=bookings" style="font-size:13px;color:var(--accent);text-decoration:none">All <?= count($bookings) ?> orders →</a></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($designs)): ?>
  <div class="sec-card">
    <div class="sec-title">Saved designs</div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <?php foreach (array_slice($designs,0,4) as $d):
        $dc=json_decode($d['customization']??'{}',true)??[];
        $hexes=array_values(is_array($dc['colors']??null)?$dc['colors']:[]);
      ?>
      <div style="display:flex;align-items:center;gap:.625rem;background:var(--cream);border:1px solid rgba(107,66,38,.12);border-radius:10px;padding:.625rem .875rem">
        <div style="display:flex;gap:3px"><?php foreach (array_slice($hexes,0,5) as $h): ?><div style="width:16px;height:16px;border-radius:50%;background:<?= e($h) ?>;border:1px solid rgba(0,0,0,.1);color-scheme:only light"></div><?php endforeach; ?></div>
        <span style="font-size:13px;font-weight:500"><?= e($d['design_name']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:.875rem"><a href="?tab=designs" style="font-size:13px;color:var(--accent);text-decoration:none">Manage designs →</a></div>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:1rem;flex-wrap:wrap">
    <a href="solemate_homepage.php?page=booking" class="btn-accent">Book a service →</a>
    <a href="solemate_homepage.php?page=customizer" class="btn-ghost" style="text-decoration:none;display:inline-block">3D Customizer</a>
  </div>


  <!-- ════════════════ MY BOOKINGS ════════════════════ -->
  <?php elseif ($tab==='bookings'): ?>
  <?php if (!$hasRefunds): ?>
  <div class="err-box"><span>⚠️</span><span>Run <strong>sql/solemate_refund_migration.sql</strong> to enable order cancellation and refund requests.</span></div>
  <?php endif; ?>
  <div class="sec-card" style="padding:1.25rem 0">
    <div style="padding:0 1.5rem 1rem"><div class="sec-title" style="margin-bottom:0">All orders</div></div>
    <?php if (empty($bookings)): ?>
    <div class="empty-state"><div style="font-size:2.5rem;margin-bottom:.75rem">👟</div><div style="font-weight:500;margin-bottom:.5rem">No orders yet</div><a href="solemate_homepage.php?page=booking">Book a service →</a></div>
    <?php else: ?>
    <table class="orders-table">
      <thead><tr>
        <th style="padding-left:1.5rem">Order ref</th><th>Service</th><th>Date</th>
        <th>Collection</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($bookings as $bk):
          $hasCust=!empty($bk['customization_data']);
          $bDec=$hasCust?json_decode($bk['customization_data'],true)??[]:[];
          $bHexes=array_values(is_array($bDec['colors']??null)?$bDec['colors']:[]);
          $bSize=$bDec['size']??'';
          $bId=(int)$bk['id'];
        ?>
        <tr data-id="<?= $bId ?>">
          <td style="padding-left:1.5rem"><a href="solemate_homepage.php?page=tracker&oid=<?= e($bk['order_ref']) ?>" class="order-ref" target="_blank"><?= e($bk['order_ref']) ?></a></td>
          <td><?= e($bk['service_name']??'Shoe service') ?>
            <?php if (!empty($bHexes)): ?><div style="display:flex;gap:3px;margin-top:3px"><?php foreach (array_slice($bHexes,0,4) as $h): ?><div style="width:12px;height:12px;border-radius:50%;background:<?= e($h) ?>;border:1px solid rgba(0,0,0,.1);color-scheme:only light"></div><?php endforeach; ?></div><?php endif; ?>
          </td>
          <td style="white-space:nowrap"><?= e(date('d M Y',strtotime($bk['order_date']))) ?></td>
          <td style="text-transform:capitalize"><?= e($bk['collection']??'') ?></td>
          <td><?= statusBadge($bk['status']??'') ?>
            <?php if ($hasRefunds && ($bk['refund_status']??'')): ?>
            <div style="font-size:10px;color:var(--muted);margin-top:3px;text-transform:capitalize">Refund: <?= e($bk['refund_status']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.4rem;align-items:center">
              <button class="order-expand-btn" onclick="toggleOrder(<?= $bId ?>)">Details</button>
              <button class="btn-ghost btn-sm" onclick="openRebookModal(<?= (int)($bk['service_id']??0) ?>,<?= htmlspecialchars(json_encode($bk['service_name']??''), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($bk['shoe_type']??''), ENT_QUOTES) ?>)">Book again</button>
              <?php if ($hasRefunds && in_array($bk['status']??'', $cancellableStatuses)): ?>
              <button class="btn-ghost btn-sm" onclick="openCancelModal(<?= $bId ?>,<?= htmlspecialchars(json_encode($bk['order_ref'] ?? ''), ENT_QUOTES) ?>,<?= (($bk['payment_status']??'')==='paid')?'true':'false' ?>)">Cancel</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <tr id="od-<?= $bId ?>">
          <td colspan="6" style="padding:0;border:none">
            <div class="order-details" id="odd-<?= $bId ?>">
              <div class="od-grid">
                <?php if ($bk['shoe_type']): ?><div class="od-item"><label>Shoe</label><span><?= e($bk['shoe_type']) ?></span></div><?php endif; ?>
                <?php if ($bk['drop_date']): ?><div class="od-item"><label>Drop-off</label><span><?= e(date('d M Y',strtotime($bk['drop_date']))) ?></span></div><?php endif; ?>
                <?php if ($bk['phone']): ?><div class="od-item"><label>Phone</label><span><?= e($bk['phone']) ?></span></div><?php endif; ?>
                <?php if ($bSize): ?><div class="od-item"><label>Shoe size</label><span><?= e($bSize) ?></span></div><?php endif; ?>
              </div>
              <?php if (!empty($bHexes)): ?>
              <div style="margin-top:.625rem;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Design colours</div>
              <div class="od-swatches"><?php foreach ($bHexes as $h): ?><div class="od-swatch" style="background:<?= e($h) ?>" title="<?= e($h) ?>"></div><?php endforeach; ?></div>
              <?php endif; ?>
              <?php if ($bk['notes']): ?><div style="font-size:12px;color:var(--muted);margin-top:.625rem;line-height:1.6"><strong style="color:var(--dark)">Notes:</strong> <?= e($bk['notes']) ?></div><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>


  <!-- ════════════════ SAVED DESIGNS ═════════════════ -->
  <?php elseif ($tab==='care'): ?>
  <div class="sec-card" style="padding:1.25rem 1.5rem">
    <div class="sec-title">Care product orders</div>
    <?php if (empty($careOrders)): ?>
    <div class="empty-state"><div style="font-size:2.5rem;margin-bottom:.75rem">🧴</div><div style="font-weight:500;margin-bottom:.5rem">No care product orders yet</div><a href="solemate_homepage.php?page=care">Browse care products →</a></div>
    <?php else: foreach ($careOrders as $co): ?>
    <div style="padding:1rem 0;border-top:1px solid rgba(var(--border-rgb,92,56,32),.1)">
      <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div><strong><?= e($co['order_ref']) ?></strong>
          <span style="color:var(--muted);font-size:12px"> · <?= e(date('d M Y', strtotime($co['created_at']))) ?></span></div>
        <div style="font-size:12px;font-weight:500;color:var(--accent)"><?= e($co['status']) ?> · <a href="solemate_homepage.php?page=tracker&amp;oid=<?= urlencode($co['order_ref']) ?>" style="color:var(--accent)">Track →</a></div>
      </div>
      <?php foreach ($careOrderItems[(int)$co['id']] ?? [] as $ci): ?>
      <div style="font-size:13px;display:flex;justify-content:space-between;padding:3px 0"><span><?= e($ci['name']) ?> <span style="color:var(--muted)">× <?= (int)$ci['qty'] ?></span></span><span>R<?= number_format((float)$ci['line_total'], 2) ?></span></div>
      <?php endforeach; ?>
      <div style="font-size:12px;color:var(--muted);margin-top:.4rem">
        <?= $co['delivery_method'] === 'delivery' ? '🚚 Delivery to ' . e($co['delivery_address'] ?? '') : '🏬 Collect in store' ?>
        · Total <strong style="color:var(--accent)">R<?= number_format((float)$co['total'], 2) ?></strong>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <?php elseif ($tab==='designs'): ?>
  <?php if (!$hasDesigns): ?>
  <div class="err-box"><span>⚠️</span><span>Run <strong>solemate_customer_features_migration.sql</strong> to enable saved designs.</span></div>
  <?php else: ?>
  <?php if (empty($designs)): ?>
  <div class="sec-card">
    <div class="empty-state">
      <div style="font-size:2.5rem;margin-bottom:.75rem">🎨</div>
      <div style="font-weight:500;margin-bottom:.5rem">No saved designs yet</div>
      <div style="font-size:13px;margin-bottom:1rem">Create a custom shoe design in the 3D Customizer and save it here.</div>
      <a href="solemate_homepage.php?page=customizer">Open 3D Customizer →</a>
    </div>
  </div>
  <?php else: ?>
  <div class="designs-grid">
    <?php foreach ($designs as $d):
      $dc=json_decode($d['customization']??'{}',true)??[];
      $hexes=array_values(is_array($dc['colors']??null)?$dc['colors']:[]);
      $size=$dc['size']??'';
      $did=(int)$d['id'];
    ?>
    <div class="design-card">
      <!-- Swatches -->
      <div class="design-swatches">
        <?php foreach (array_slice($hexes,0,8) as $h): ?>
        <div class="design-swatch" style="background:<?= e($h) ?>" title="<?= e($h) ?>"></div>
        <?php endforeach; ?>
        <?php if (empty($hexes)): ?><span style="font-size:12px;color:var(--muted)">No colour data</span><?php endif; ?>
      </div>
      <!-- Name & meta -->
      <div class="design-name"><?= e($d['design_name']) ?></div>
      <div class="design-meta">
        <?= $size?e($size).' · ':'' ?>Saved <?= e(date('d M Y',strtotime($d['updated_at']))) ?>
      </div>
      <!-- Rename inline form -->
      <div class="design-rename-form" id="rename-<?= $did ?>">
        <form method="POST" style="display:flex;gap:.5rem;flex:1">
          <input type="hidden" name="action" value="rename_design">
          <input type="hidden" name="design_id" value="<?= $did ?>">
          <input type="text" name="design_name" class="design-rename-input" value="<?= e($d['design_name']) ?>" required maxlength="100">
          <button type="submit" class="btn-primary btn-sm">Save</button>
        </form>
        <button type="button" class="btn-ghost btn-sm" onclick="document.getElementById('rename-<?= $did ?>').classList.remove('open')">✕</button>
      </div>
      <!-- Actions -->
      <div class="design-actions">
        <button class="btn-accent btn-sm" onclick="openDesignModal(<?= $did ?>,<?= htmlspecialchars(json_encode($d['design_name']), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($hexes), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($size), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($d['customization']??'{}'), ENT_QUOTES) ?>)">Book this design</button>
        <a class="btn-ghost btn-sm" style="text-decoration:none;display:inline-block" href="solemate_homepage.php?page=customizer&amp;edit_design=<?= $did ?>">✏️ Edit design</a>
        <button class="btn-ghost btn-sm" onclick="document.getElementById('rename-<?= $did ?>').classList.toggle('open')">Rename</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this design?')">
          <input type="hidden" name="action" value="delete_design">
          <input type="hidden" name="design_id" value="<?= $did ?>">
          <button type="submit" class="btn-danger btn-sm">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.5rem">
    <a href="solemate_homepage.php?page=customizer" class="btn-accent">Open 3D Customizer →</a>
  </div>
  <?php endif; ?>


  <!-- ════════════════════ WISHLIST ════════════════════ -->
  <?php elseif ($tab==='wishlist'): ?>
  <?php if (!$hasWishlist): ?>
  <div class="err-box"><span>⚠️</span><span>Run <strong>solemate_customer_features_migration.sql</strong> to enable wishlist.</span></div>
  <?php elseif (empty($wishlist)): ?>
  <div class="sec-card">
    <div class="empty-state">
      <div style="font-size:2.5rem;margin-bottom:.75rem">♡</div>
      <div style="font-weight:500;margin-bottom:.5rem">Your wishlist is empty</div>
      <div style="font-size:13px;margin-bottom:1rem">Tap the ♥ on any service on the homepage to save it here.</div>
      <a href="solemate_homepage.php">Browse services →</a>
    </div>
  </div>
  <?php else: ?>
  <div class="wishlist-grid">
    <?php foreach ($wishlist as $w): ?>
    <div class="wish-card">
      <div class="wish-icon"><?= $w['icon'] ?></div>
      <div>
        <div class="wish-name"><?= e($w['service_name']) ?></div>
        <div class="wish-price">R<?= number_format($w['price'],0) ?><?= $w['price_from']?'+':'' ?> per pair</div>
      </div>
      <div class="wish-desc"><?= e($w['description']) ?></div>
      <div class="wish-actions">
        <button class="btn-accent btn-sm" onclick="openRebookModal(<?= (int)$w['sid'] ?>,<?= htmlspecialchars(json_encode($w['service_name']), ENT_QUOTES) ?>,'')">Book now</button>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="remove_wishlist">
          <input type="hidden" name="service_id" value="<?= (int)$w['sid'] ?>">
          <button type="submit" class="btn-ghost btn-sm" style="color:var(--accent)">♥ Remove</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>


  <!-- ════════════════════ PROFILE ════════════════════ -->
  <?php elseif ($tab==='profile'): ?>
  <div class="sec-card">
    <form method="POST">
      <input type="hidden" name="action" value="update_profile">
      <div class="form-section">
        <h3>Personal details</h3>
        <div class="form-row">
          <div class="fg"><label>First name *</label><input type="text" name="first_name" required maxlength="60" value="<?= e($cust['first_name']) ?>"></div>
          <div class="fg"><label>Last name *</label><input type="text" name="last_name" required maxlength="60" value="<?= e($cust['last_name']) ?>"></div>
        </div>
        <div class="fg"><label>Email address *</label><input type="email" name="email" required maxlength="100" value="<?= e($cust['email']) ?>"></div>
        <div class="fg"><label>Phone <span style="color:rgba(107,66,38,.4);font-weight:400">(optional)</span></label><input type="tel" name="phone" maxlength="30" placeholder="082 123 4567" value="<?= e($cust['phone']??'') ?>"></div>
      </div>
      <div class="form-section">
        <h3>Change password <span style="font-size:12px;font-weight:400;color:var(--muted)">— leave blank to keep current</span></h3>
        <div class="fg"><label>Current password</label><div class="pw-wrap"><input type="password" name="current_password" id="cpw" placeholder="Your current password" maxlength="72"><button type="button" class="pw-toggle" onclick="togglePw('cpw',this)">👁</button></div></div>
        <div class="form-row">
          <div class="fg"><label>New password</label><div class="pw-wrap"><input type="password" name="new_password" id="npw" placeholder="Min. 8 characters" maxlength="72"><button type="button" class="pw-toggle" onclick="togglePw('npw',this)">👁</button></div></div>
          <div class="fg"><label>Confirm new password</label><div class="pw-wrap"><input type="password" name="confirm_new_password" id="cnpw" placeholder="Repeat new password" maxlength="72"><button type="button" class="pw-toggle" onclick="togglePw('cnpw',this)">👁</button></div></div>
        </div>
      </div>
      <button type="submit" class="btn-primary">Save changes →</button>
    </form>
  </div>
  <div class="sec-card" style="font-size:13px;color:var(--muted);line-height:1.8">
    <strong style="color:var(--dark);display:block;margin-bottom:.5rem">Account information</strong>
    Member since: <?= e($memberSince) ?><br>
    Last sign-in: <?= $cust['last_login']?e(date('d M Y, H:i',strtotime($cust['last_login']))):'First session' ?>
  </div>


  <!-- ════════════════════ ADDRESSES ═══════════════════ -->
  <?php elseif ($tab==='addresses'): ?>
  <button type="button" class="add-toggle" onclick="document.getElementById('addAddrForm').classList.toggle('open')">
    <span style="font-size:18px;line-height:1">＋</span> Add new address
  </button>
  <div class="add-form" id="addAddrForm">
    <form method="POST">
      <input type="hidden" name="action" value="add_address">
      <div class="form-row">
        <div class="fg"><label>Label</label><select name="addr_label"><option>Home</option><option>Work</option><option>Other</option></select></div>
        <div class="fg"><label>Postal code</label><input type="text" name="addr_postal" placeholder="4001" maxlength="10"></div>
      </div>
      <div class="fg"><label>Street address *</label><input type="text" name="addr_street" required placeholder="123 Florida Road" maxlength="150"></div>
      <div class="form-row">
        <div class="fg"><label>Suburb</label><input type="text" name="addr_suburb" placeholder="Morningside" maxlength="100"></div>
        <div class="fg"><label>City</label><input type="text" name="addr_city" placeholder="Durban" maxlength="100" value="Durban"></div>
      </div>
      <div class="fg"><label>Province</label>
        <select name="addr_province">
          <option value="KwaZulu-Natal" selected>KwaZulu-Natal</option><option>Gauteng</option>
          <option>Western Cape</option><option>Eastern Cape</option><option>Limpopo</option>
          <option>Mpumalanga</option><option>North West</option><option>Northern Cape</option><option>Free State</option>
        </select>
      </div>
      <label class="checkbox-row"><input type="checkbox" name="addr_default" value="1"> Set as default delivery address</label>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <button type="submit" class="btn-primary">Save address →</button>
        <button type="button" class="btn-ghost" onclick="document.getElementById('addAddrForm').classList.remove('open')">Cancel</button>
      </div>
    </form>
  </div>
  <?php if (empty($addresses)): ?>
  <div class="sec-card"><div class="empty-state"><div style="font-size:2.5rem;margin-bottom:.75rem">📍</div><div style="font-weight:500;margin-bottom:.5rem">No saved addresses</div><div style="font-size:13px">Add an address above to use during booking.</div></div></div>
  <?php else: ?>
  <div class="addr-grid">
    <?php foreach ($addresses as $a): ?>
    <div class="addr-card<?= $a['is_default']?' is-default':'' ?>">
      <?php if ($a['is_default']): ?><div class="addr-default-badge">Default</div><?php endif; ?>
      <div class="addr-label"><?= e($a['label']) ?></div>
      <div class="addr-street"><?= e($a['street']) ?></div>
      <div class="addr-detail"><?= $a['suburb']?e($a['suburb']).', ':'' ?><?= e($a['city']) ?><br><?= e($a['province']) ?><?= $a['postal_code']?' · '.e($a['postal_code']):'' ?></div>
      <div class="addr-actions">
        <?php if (!$a['is_default']): ?>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="set_default"><input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>"><button type="submit" class="btn-ghost btn-sm">Set default</button></form>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this address?')"><input type="hidden" name="action" value="delete_address"><input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>"><button type="submit" class="btn-danger">Remove</button></form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div><!-- /page -->

<!-- ═══════════════ BOOKING MODAL ═══════════════════ -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div class="modal-title" id="modalTitle">Book a service</div>

    <!-- Design preview banner (shown for design rebooks) -->
    <div class="design-preview-banner" id="designBanner" style="display:none">
      <div>
        <div style="font-size:11px;color:rgba(200,169,126,.6);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Design</div>
        <div style="color:#fff;font-size:13px;font-weight:500" id="bannerDesignName"></div>
        <?php /* shoe size */ ?>
        <div style="color:rgba(200,169,126,.6);font-size:11px;margin-top:2px" id="bannerDesignSize"></div>
      </div>
      <div class="dp-swatches" id="bannerSwatches" style="margin-left:auto"></div>
    </div>

    <form action="solemate_process_order.php" method="POST">
      <input type="hidden" name="customization_data" id="modalCustomData">

      <div class="form-row">
        <div class="fg"><label>First name *</label><input type="text" name="customer_name" required value="<?= e($cust['first_name']) ?>"></div>
        <div class="fg"><label>Surname</label><input type="text" name="surname" value="<?= e($cust['last_name']) ?>"></div>
      </div>
      <div class="fg"><label>Email *</label><input type="email" name="email" required value="<?= e($cust['email']) ?>"></div>
      <div class="fg"><label>Phone</label><input type="tel" name="phone" value="<?= e($cust['phone']??'') ?>"></div>
      <div class="fg">
        <label>Service *</label>
        <select name="service_id" id="modalServiceSelect" required>
          <option value="">— Select a service —</option>
          <?php foreach ($allServices as $sv): ?>
          <option value="<?= (int)$sv['id'] ?>"><?= e($sv['service_name']) ?> — R<?= number_format($sv['price'],0) ?><?= $sv['price_from']?'+':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label>Shoe brand / type</label><input type="text" name="shoe_type" id="modalShoeType" placeholder="e.g. Nike Air Max, Timberlands"></div>
      <div class="form-row">
        <div class="fg"><label>Drop-off date *</label><input type="date" name="drop_date" min="<?= date('Y-m-d') ?>" required></div>
        <div class="fg"><label>Collection method</label>
          <select name="collection">
            <option value="drop-off">Drop-off (free)</option>
            <option value="courier">Courier (+R80)</option>
            <option value="home">Home collection (+R120)</option>
          </select>
        </div>
      </div>
      <div class="fg"><label>Notes <span style="color:rgba(107,66,38,.4);font-weight:400">(optional)</span></label>
        <textarea name="notes" id="modalNotes" rows="2" placeholder="Any special instructions…"></textarea>
      </div>
      <input type="hidden" name="pairs" value="1">
      <input type="hidden" name="rush" value="0">
      <button type="submit" class="btn-primary" style="width:100%;padding:13px">Confirm booking →</button>
    </form>
  </div>
</div>

<!-- ═══════════════ CANCEL / REFUND MODAL ═══════════════ -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal">
    <button class="modal-close" onclick="closeCancelModal()">✕</button>
    <div class="modal-title" id="cancelModalTitle">Cancel booking</div>
    <p id="cancelModalNote" style="font-size:13px;color:var(--muted);margin:-.5rem 0 1rem;line-height:1.6"></p>
    <form method="POST" onsubmit="return confirm('Cancel this booking? This cannot be undone.')">
      <input type="hidden" name="action" value="cancel_booking">
      <input type="hidden" name="booking_id" id="cancelBookingId">
      <div class="fg"><label>Reason <span style="color:rgba(107,66,38,.4);font-weight:400">(optional)</span></label>
        <textarea name="cancel_reason" rows="3" placeholder="Let us know why you're cancelling…"></textarea>
      </div>
      <button type="submit" class="btn-primary" style="width:100%;padding:13px;background:#b3261e">Confirm cancellation</button>
    </form>
  </div>
</div>

<script>
function openCancelModal(bookingId, orderRef, wasPaid) {
  document.getElementById('cancelBookingId').value = bookingId;
  document.getElementById('cancelModalTitle').textContent = 'Cancel ' + orderRef;
  document.getElementById('cancelModalNote').textContent = wasPaid
    ? 'This order was paid — cancelling will submit a refund request to our team for review.'
    : 'This order has not been paid yet, so no refund is needed.';
  document.getElementById('cancelModal').classList.add('open');
}
function closeCancelModal() { document.getElementById('cancelModal').classList.remove('open'); }
document.getElementById('cancelModal').addEventListener('click', function(e) {
  if (e.target === this) closeCancelModal();
});
</script>

<script>
// ── Expand/collapse order row ───────────────────────────
function toggleOrder(id) {
  const el = document.getElementById('odd-' + id);
  if (!el) return;
  el.classList.toggle('open');
}

// ── Password show/hide ──────────────────────────────────
function togglePw(id, btn) {
  const f = document.getElementById(id);
  if (!f) return;
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.textContent = f.type === 'password' ? '👁' : '🙈';
}

// ── Booking modal helpers ───────────────────────────────
function closeModal() { document.getElementById('bookingModal').classList.remove('open'); }
document.getElementById('bookingModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Rebook a previous service (from Orders tab or Wishlist)
function openRebookModal(serviceId, serviceName, shoeType) {
  document.getElementById('modalTitle').textContent = 'Book again — ' + serviceName;
  document.getElementById('designBanner').style.display = 'none';
  document.getElementById('modalCustomData').value = '';
  document.getElementById('modalShoeType').value = shoeType || '';
  document.getElementById('modalNotes').value = '';

  const sel = document.getElementById('modalServiceSelect');
  for (let i = 0; i < sel.options.length; i++) {
    if (parseInt(sel.options[i].value) === serviceId) { sel.selectedIndex = i; break; }
  }
  document.getElementById('bookingModal').classList.add('open');
}

// Book a saved custom design
function openDesignModal(designId, designName, hexes, size, customJson) {
  document.getElementById('modalTitle').textContent = 'Book design — ' + designName;
  document.getElementById('modalCustomData').value = customJson;

  // Banner
  const banner = document.getElementById('designBanner');
  banner.style.display = 'flex';
  document.getElementById('bannerDesignName').textContent = designName;
  document.getElementById('bannerDesignSize').textContent = size ? 'Size: ' + size : '';

  const swatchesEl = document.getElementById('bannerSwatches');
  swatchesEl.innerHTML = '';
  (hexes || []).slice(0, 8).forEach(h => {
    const d = document.createElement('div');
    d.className = 'dp-swatch';
    d.style.background = h;
    d.title = h;
    swatchesEl.appendChild(d);
  });

  // Pre-fill shoe type with design name + size
  document.getElementById('modalShoeType').value = 'Custom Design: ' + designName + (size ? ' ('+size+')' : '');
  document.getElementById('modalNotes').value = '[DESIGN] ' + designName;

  // Auto-select "Custom Shoe Design" service if it exists, else first service
  const sel = document.getElementById('modalServiceSelect');
  let found = false;
  for (let i = 0; i < sel.options.length; i++) {
    if (/custom/i.test(sel.options[i].text)) { sel.selectedIndex = i; found = true; break; }
  }
  if (!found) sel.selectedIndex = 1;

  document.getElementById('bookingModal').classList.add('open');
}

// Keyboard close
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeCancelModal(); } });
</script>
</body>
</html>