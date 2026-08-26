<?php
// ═══════════════════════════════════════════════════════
//  index.php  —  SoleMate Main Website
//  All data comes from shoe_repair_db via PDO (db.php)
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';
require 'includes/solemate_customer_auth.php';
require_once 'includes/solemate_ecommerce.php';
$settings = getSiteSettings($pdo);

// Build wishlist set for logged-in customers (fast O(1) lookup in card loop)
$customerWishlistIds = [];
if (isCustomerLoggedIn()) {
    try {
        $wlExists = $pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.customer_wishlist') AND type='U'")->fetchColumn();
        if ($wlExists) {
            $wlRows = $pdo->prepare("SELECT service_id FROM dbo.customer_wishlist WHERE customer_id=?");
            $wlRows->execute([getCustomerId()]);
            $customerWishlistIds = array_column($wlRows->fetchAll(), 'service_id', 'service_id');
        }
    } catch (PDOException $e) {}
}

$page       = $_GET['page'] ?? 'services';
$validPages = ['services', 'booking', 'tracker', 'calculator', 'reviews', 'gallery', 'about', 'giftcard', 'customizer'];
if (!in_array($page, $validPages)) $page = 'services';
if ($page === 'giftcard' && !$settings['gift_cards']) $page = 'services';

// ── Gift card balance check (inline POST) ────────────────
$gcCheckResult = null;
if ($page === 'giftcard' && $settings['gift_cards'] && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['gc_action'] ?? '') === 'check') {
    $gcCheckResult = validateGiftCard($pdo, strtoupper(trim($_POST['gc_code'] ?? '')));
}

$services = $pdo->query("SELECT * FROM dbo.services ORDER BY sort_order")->fetchAll();

$trackResult = null;
$trackError  = null;
$newBooking  = isset($_GET['new']) && $_GET['new'] === '1';

if ($page === 'tracker' && isset($_GET['oid'])) {
    $rawOid = strtoupper(trim($_GET['oid']));
    if ($rawOid) {
        $stmt = $pdo->prepare("
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
                s.service_name
            FROM dbo.booking o
            LEFT JOIN dbo.services s ON o.service_id = s.id
            WHERE o.order_ref = ?
        ");
        $stmt->execute([$rawOid]);
        $trackResult = $stmt->fetch();
        if (!$trackResult) {
            $trackError = "Order <strong>" . htmlspecialchars($rawOid) . "</strong> not found. Please check the number and try again.";
        }
    } else {
        $trackError = "Please enter an order number.";
    }
}

$bookingErrors = [];
if ($page === 'booking' && !empty($_GET['errors'])) {
    $bookingErrors = explode('|', urldecode($_GET['errors']));
}

$reviews = [];
if ($page === 'reviews') {
    $reviews = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();
}

$galleryItems = [];
if ($page === 'gallery') {
    // Try DB first; fall back to built-in array if the table hasn't been migrated yet
    try {
        $galleryItems = $pdo->query(
            "SELECT * FROM gallery_items ORDER BY sort_order ASC"
        )->fetchAll();
    } catch (Exception $e) {
        $galleryItems = [
            ['id'=>1,'service_key'=>'deep-clean',          'shoe_name'=>'Air Jordan 1 Retro High OG',  'before_desc'=>'Dirt-caked uppers, yellowed midsoles, grimy lace eyelets',      'after_desc'=>'Crisp box-fresh finish, bright white soles, clean laces',        'turnaround'=>'48h',    'rating'=>5],
            ['id'=>2,'service_key'=>'leather-restoration', 'shoe_name'=>"Church's Oxford Brogue",      'before_desc'=>'Deep toe scuffs, cracked leather, faded colour',                'after_desc'=>'Full colour match, deep conditioned, mirror-polished toe',       'turnaround'=>'72h',    'rating'=>5],
            ['id'=>3,'service_key'=>'sneaker-whitening',   'shoe_name'=>'Nike Air Force 1 Low',         'before_desc'=>'Greyed midsoles, oxidised rubber, stained canvas',              'after_desc'=>'Factory-white midsoles, restored canvas, blinding clean',        'turnaround'=>'24h',    'rating'=>5],
            ['id'=>4,'service_key'=>'sole-repair',         'shoe_name'=>'Red Wing Iron Ranger Boot',    'before_desc'=>'Worn-through heel stack, detaching outsole, split welt',        'after_desc'=>'Vibram heel replaced, outsole resoled, welt restitched',         'turnaround'=>'5 days', 'rating'=>5],
            ['id'=>5,'service_key'=>'leather-restoration', 'shoe_name'=>'Louis Vuitton Derby Shoe',     'before_desc'=>'Colour transfer on toe cap, cracked varnish, creased vamp',     'after_desc'=>'Factory colour matched, re-varnished, crease smoothed',          'turnaround'=>'4 days', 'rating'=>5],
            ['id'=>6,'service_key'=>'deep-clean',          'shoe_name'=>'New Balance 990v5',            'before_desc'=>'Heavy mud on upper mesh, caked lug sole, dark lace tips',       'after_desc'=>'All colourways restored, mesh brightened, soles degreased',      'turnaround'=>'48h',    'rating'=>5],
            ['id'=>7,'service_key'=>'sneaker-whitening',   'shoe_name'=>'Adidas Stan Smith',            'before_desc'=>'Yellowed panels, grey toe box, stiff insole',                   'after_desc'=>'Bright white panels, fresh insole, crisp green heel tab',        'turnaround'=>'24h',    'rating'=>5],
            ['id'=>8,'service_key'=>'sole-repair',         'shoe_name'=>'Timberland 6-inch Premium',    'before_desc'=>'Delaminating midsole, worn lug pattern, exposed shank',         'after_desc'=>'Full resoled with Commando lug, rebonded midsole, waterproofed', 'turnaround'=>'4 days', 'rating'=>5],
            ['id'=>9,'service_key'=>'leather-restoration', 'shoe_name'=>'Grenson Fred Triple Welt',     'before_desc'=>'Rain staining, salt tide marks, lifting welt stitching',        'after_desc'=>'Salt marks removed, welt restitched, leather nourished',         'turnaround'=>'72h',    'rating'=>5],
        ];
    }
}

$preselectedId    = (int)($_GET['service_id'] ?? 0);
$preselectedPairs = (int)($_GET['pairs']      ?? 1);
$preselectedRush  = (int)($_GET['rush']       ?? 0);
$fromQuote        = isset($_GET['pairs']) || isset($_GET['rush']);

function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SoleMate — Shoe Cleaning &amp; Repair · Durban</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<link rel="icon" href="includes/Solemate_logo.ico" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#FAF7F2;--tan:#C8A97E;--brown:#6B4226;--dark:#1A0F08;
  --accent:#D4561A;--light:#E8D5BE;--muted:#8B7355;--success:#2D6A4F;
}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--dark);min-height:100vh}
html, body{overflow-x:hidden;}

nav{background:var(--dark);padding:0 1.75rem;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(0,0,0,0.35)}
.logo{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--tan);text-decoration:none}
.logo span{color:var(--accent)}
.nav-tabs{display:flex;gap:3px;flex-wrap:wrap}
.nav-tab{color:rgba(255,255,255,0.5);font-size:13px;padding:6px 13px;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;transition:all 0.15s}
.nav-tab:hover{color:var(--tan)}
.nav-tab.active{background:rgba(200,169,126,0.15);color:var(--tan)}
.admin-link{color:rgba(255,255,255,0.28);font-size:12px;text-decoration:none;margin-left:0.5rem;border-left:1px solid rgba(255,255,255,0.1);padding-left:0.75rem}
.admin-link:hover{color:var(--tan)}
/* Wishlist heart button on service cards */
.wl-btn{position:absolute;top:10px;right:10px;z-index:5;background:rgba(0,0,0,0.35);
  backdrop-filter:blur(4px);border:none;color:#fff;font-size:17px;width:34px;height:34px;
  border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .18s;line-height:1}
.wl-btn:hover{background:rgba(212,86,26,0.7);transform:scale(1.12)}
.wl-btn.wl-active{background:rgba(212,86,26,0.85);color:#fff}

.hero{background:var(--dark);padding:4rem 2rem 3.5rem;text-align:center;position:relative;overflow:hidden}
.hero img,.hero-logo{max-width:220px;width:100%;height:auto;display:block;margin:0 auto;position:relative;z-index:2;}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 55% at 50% -10%,rgba(200,169,126,0.1) 0%,transparent 65%);pointer-events:none}
.hero-badge{display:inline-block;background:rgba(200,169,126,0.12);border:1px solid rgba(200,169,126,0.28);color:var(--tan);font-size:11px;padding:4px 14px;border-radius:20px;margin-bottom:1.25rem;text-transform:uppercase;letter-spacing:1px}
.hero h1{font-family:'Playfair Display',serif;font-size:clamp(2rem,5vw,3.2rem);font-weight:700;color:#fff;line-height:1.18;margin-bottom:0.875rem}
.hero h1 em{font-style:italic;color:var(--tan)}
.hero p{font-size:15px;color:rgba(255,255,255,0.52);line-height:1.7;margin-bottom:2.25rem;max-width:420px;margin-left:auto;margin-right:auto}
.hero-btns{display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap}

.btn-p{background:var(--accent);color:#fff;border:none;padding:12px 26px;border-radius:7px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;transition:opacity 0.15s;text-decoration:none;display:inline-block}
.btn-p:hover{opacity:0.87}
.btn-s{background:transparent;color:rgba(255,255,255,0.75);border:1px solid rgba(255,255,255,0.22);padding:12px 26px;border-radius:7px;font-size:14px;cursor:pointer;font-family:inherit;transition:all 0.15s;text-decoration:none;display:inline-block}
.btn-s:hover{border-color:var(--tan);color:var(--tan)}

.stats-bar{background:var(--tan);display:flex;justify-content:center;gap:3rem;padding:1rem 1.5rem;flex-wrap:wrap}
.stat-num{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--dark);text-align:center}
.stat-lbl{font-size:11px;color:var(--brown);text-align:center;margin-top:2px}

.page-wrap{padding:2.25rem 1.75rem 4rem;max-width:920px;margin:0 auto}
.sec-label{font-size:11px;font-weight:500;color:var(--accent);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.sec-title{font-family:'Playfair Display',serif;font-size:1.85rem;font-weight:700;margin-bottom:8px;line-height:1.2}
.sec-sub{font-size:14px;color:var(--muted);line-height:1.7;margin-bottom:1.75rem;max-width:520px}

.list{position:relative;width:100%}
.item-wrapper{position:absolute;will-change:transform,width,height,opacity;padding:6px;cursor:pointer;top:0;left:0}
.item-wrapper>.item-img{position:relative;background-size:cover;background-position:center center;width:100%;height:100%;text-transform:uppercase;font-size:10px;line-height:10px;border-radius:10px;box-shadow:0px 10px 50px -10px rgba(0,0,0,0.2);overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end;padding:1.125rem}
.svc-book-pill{position:absolute;top:1rem;left:1rem;background:var(--accent);color:#fff;font-size:10px;font-weight:500;padding:4px 12px;border-radius:20px;opacity:0;transform:translateY(-8px);transition:opacity 0.25s ease,transform 0.25s ease;font-family:'DM Sans',system-ui,sans-serif;letter-spacing:0.3px;text-transform:none;text-decoration:none}
.item-wrapper:hover .svc-book-pill{opacity:0;transform:translateY(-8px)}
.svc-icon-bg{position:absolute;top:1rem;right:1rem;font-size:2rem;opacity:0.75;line-height:1}
.svc-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:0.35rem;line-height:1.3;text-transform:none;letter-spacing:0}
.svc-desc{font-size:11px;line-height:1.6;margin-bottom:0.8rem;text-transform:none;letter-spacing:0;font-family:'DM Sans',system-ui,sans-serif;font-weight:400}
.svc-price-row{display:flex;align-items:baseline;justify-content:space-between;font-family:'DM Sans',system-ui,sans-serif}
.svc-price{font-size:20px;font-weight:500;letter-spacing:0;text-transform:none}
.svc-price span{font-size:10px;font-weight:400;text-transform:none;letter-spacing:0}

.form-wrap{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:14px;padding:1.875rem;max-width:530px}
.form-ttl{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;margin-bottom:1.375rem}
.fg{margin-bottom:0.9rem}
.fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px}
.fg input,.fg select,.fg textarea{width:100%;border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;transition:border-color 0.18s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--accent)}
.fg-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem}
.sub-btn{width:100%;background:var(--accent);color:#fff;border:none;padding:13px;border-radius:7px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;margin-top:0.5rem;transition:opacity 0.15s}
.sub-btn:hover{opacity:0.87}

.alert{border-radius:9px;padding:0.9rem 1.125rem;font-size:13px;margin-bottom:1.25rem}
.alert-error{background:#fff8f6;border:1px solid rgba(212,86,26,0.28);color:#8B2200}
.alert-error ul{margin:4px 0 0 1rem}
.alert-error li{margin-bottom:2px}
.alert-success{background:#f0faf5;border:1px solid rgba(45,106,79,0.28);color:var(--success)}
.alert-success h3{font-family:'Playfair Display',serif;font-size:16px;margin-bottom:0.4rem}
.alert-success p{line-height:1.65}
.order-id-chip{font-family:monospace;font-size:15px;font-weight:700;color:var(--accent);background:var(--cream);padding:2px 9px;border-radius:5px;letter-spacing:0.5px}

.tracker-wrap{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:14px;padding:1.625rem 1.875rem;max-width:600px}
.track-row{display:flex;gap:0.625rem;margin-bottom:0.5rem}
.track-inp{flex:1;border:1px solid rgba(107,66,38,0.2);border-radius:7px;padding:10px 14px;font-size:14px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;transition:border-color 0.18s}
.track-inp:focus{border-color:var(--accent)}
.track-btn{background:var(--brown);color:#fff;border:none;padding:10px 20px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;white-space:nowrap;transition:opacity 0.15s}
.track-btn:hover{opacity:0.83}
.hint{font-size:12px;color:var(--muted);margin-bottom:0.25rem}
.hint a{color:var(--dark);text-decoration:underline dotted}
.hint a:hover{color:var(--accent);text-decoration:none}
.divider{border:none;border-top:1px solid rgba(107,66,38,0.1);margin:1.375rem 0}
.not-found{background:#fff8f6;border:1px solid rgba(212,86,26,0.22);border-radius:8px;padding:0.875rem 1.125rem;font-size:13px;color:#8B2200;margin-top:0.875rem}

.steps-wrap{position:relative;display:flex;justify-content:space-between;margin:1.5rem 0 1.125rem;border-radius:8px;}
.steps-track{position:absolute;top:13px;left:0;right:0;height:3px;background:var(--light)}
.steps-fill{position:absolute;top:13px;left:0;height:3px;border-radius:3px;background:linear-gradient(90deg,transparent,var(--accent),#ff9a5c,var(--accent),transparent);max-width:100%;overflow:hidden;background-size:200% 100%;box-shadow:0 0 6px var(--accent),0 0 12px var(--accent),0 0 20px rgba(212,86,26,0.7);animation:electricFlow 2s linear infinite}
.steps-fill::after{content:'';position:absolute;inset:0;background:linear-gradient(120deg,transparent,rgba(255,255,255,0.9),transparent);animation:electricPulse 1.5s infinite}
.step{display:flex;flex-direction:column;align-items:center;gap:5px;z-index:2;flex:1}
.step-dot{width:27px;height:27px;border-radius:50%;border:2px solid var(--light);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--muted);font-weight:500}
.step.done .step-dot{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 0 8px var(--accent),0 0 16px var(--accent),0 0 22px rgba(212,86,26,0.8)}
.step.active .step-dot{border-color:var(--accent);color:var(--accent);background:#fff;box-shadow:0 0 10px var(--accent),0 0 20px rgba(212,86,26,0.7);animation:pulseDot 1.2s infinite}
.step-lbl{font-size:10px;color:var(--muted);text-align:center;max-width:58px;line-height:1.3}
.step.done .step-lbl,.step.active .step-lbl{color:var(--dark);font-weight:500}
.order-box{background:var(--cream);border-radius:9px;padding:0.9rem 1.125rem;font-size:13px;color:var(--muted);line-height:2;border-left:3px solid var(--accent)}
.order-box strong{color:var(--dark)}

/* ── Calculator ── */
.calc-card{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:14px;padding:1.875rem;max-width:530px}
.calc-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(107,66,38,0.07);gap:1rem}
.calc-lbl{font-size:13px;color:var(--muted)}
.calc-ctrl{border:1px solid rgba(107,66,38,0.18);border-radius:6px;padding:6px 9px;font-size:12px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;min-width:170px}
.calc-total{display:flex;justify-content:space-between;align-items:center;padding:1.125rem 0 0;margin-top:0.5rem}
.total-lbl{font-size:14px;font-weight:500}
.total-price{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--accent)}
.calc-book-btn{margin-top:1.25rem;background:var(--accent);color:var(--tan);border:none;padding:12px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:block;text-align:center;transition:opacity 0.15s;width:100%}
.calc-book-btn:hover{opacity:0.83}

/* ── Reviews ── */
.rating-summary{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:12px;padding:1.375rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;max-width:340px}
.big-score{font-family:'Playfair Display',serif;font-size:3rem;font-weight:700;color:var(--dark);line-height:1}
.big-stars{font-size:18px;color:#F4A623;letter-spacing:2px}
.rating-note{font-size:12px;color:var(--muted);margin-top:3px}
.rev-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1rem}
.rev-card{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:12px;padding:1.25rem 1.5rem}
.stars{color:#F4A623;font-size:14px;margin-bottom:0.6rem;letter-spacing:1px}
.rev-text{font-size:13px;color:var(--dark);line-height:1.65;margin-bottom:1rem;font-style:italic}
.reviewer{display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;border-radius:50%;background:var(--light);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:500;color:var(--brown);flex-shrink:0}
.rev-name{font-size:13px;font-weight:500}
.rev-loc{font-size:11px;color:var(--muted)}

/* ── About Us ── */
.about-intro{max-width:620px;margin:0 auto 3rem;text-align:center}
.about-intro p{font-size:15px;color:var(--muted);line-height:1.8}
.values-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:3rem}
.value-card{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:12px;padding:1.5rem;text-align:center;transition:transform 0.18s,box-shadow 0.18s}
.value-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(107,66,38,0.11)}
.value-icon{font-size:2rem;margin-bottom:0.75rem;display:block}
.value-title{font-family:'Playfair Display',serif;font-size:14px;font-weight:700;margin-bottom:0.4rem}
.value-desc{font-size:12px;color:var(--muted);line-height:1.6}
.shops-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;margin-bottom:3rem}
.shop-card{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:14px;overflow:hidden;transition:transform 0.18s,box-shadow 0.18s}
.shop-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(107,66,38,0.13)}
.shop-card-header{background:var(--dark);padding:1.125rem 1.375rem;display:flex;align-items:center;justify-content:space-between}
.shop-badge{background:rgba(200,169,126,0.15);border:1px solid rgba(200,169,126,0.3);color:var(--tan);font-size:10px;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:1px}
.shop-name{font-family:'Playfair Display',serif;font-size:16px;color:#fff;font-weight:700}
.shop-body{padding:1.375rem}
.shop-detail{display:flex;align-items:flex-start;gap:10px;margin-bottom:0.875rem}
.shop-detail-icon{font-size:14px;margin-top:1px;flex-shrink:0;width:20px;text-align:center}
.shop-detail-text{font-size:13px;color:var(--muted);line-height:1.55}
.shop-detail-text strong{color:var(--dark)}
.shop-hours-grid{display:grid;grid-template-columns:auto 1fr;gap:2px 10px;font-size:12px;margin-top:4px}
.shop-hours-day{color:var(--muted)}
.shop-hours-time{color:var(--dark);font-weight:500}
.shop-cta{display:flex;gap:0.5rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid rgba(107,66,38,0.08)}
.shop-dir-btn{flex:1;background:var(--accent);color:#fff;border:none;padding:9px 14px;border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;transition:opacity 0.15s}
.shop-dir-btn:hover{opacity:0.87}
.shop-call-btn{background:transparent;border:1px solid rgba(107,66,38,0.2);color:var(--dark);padding:9px 14px;border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;transition:all 0.15s;flex-shrink:0}
.shop-call-btn:hover{border-color:var(--brown);color:var(--brown)}
.flagship-tag{display:inline-flex;align-items:center;gap:5px;background:rgba(212,86,26,0.1);border:1px solid rgba(212,86,26,0.25);color:var(--accent);font-size:10px;padding:3px 10px;border-radius:20px;margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:0.8px}
.team-blurb{background:linear-gradient(135deg,var(--dark) 0%,#2d1a0e 100%);border-radius:14px;padding:2.5rem;text-align:center;margin-bottom:3rem;position:relative;overflow:hidden}
.team-blurb::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 110%,rgba(200,169,126,0.08) 0%,transparent 65%);pointer-events:none}
.team-blurb-title{font-family:'Playfair Display',serif;font-size:1.5rem;color:#fff;margin-bottom:0.75rem}
.team-blurb p{font-size:13px;color:rgba(255,255,255,0.5);line-height:1.8;max-width:480px;margin:0 auto 1.5rem}

footer{background:var(--dark);color:rgba(255,255,255,0.42);text-align:center;padding:1.875rem;font-size:12px;line-height:2.2;margin-top:2rem}
.footer-logo{font-family:'Playfair Display',serif;color:var(--tan);font-size:16px;display:block;margin-bottom:4px}

/* ── StickerPeel ──────────────────────────────────────────────────────── */
.sticker-wrap{position:fixed;right:clamp(1rem,5vw,4rem);top:140px;width:150px;height:150px;cursor:grab;user-select:none;touch-action:none;z-index:200;}
.sticker-wrap:active{cursor:grabbing}
.sticker-scene{
  width:100%;height:100%;
  position:relative;
  transform:rotate(12deg);
  overflow:hidden;
  transition:transform 0.3s cubic-bezier(0.34,1.36,0.64,1);
  filter:drop-shadow(0 8px 24px rgba(0,0,0,0.55));
}
.sticker-wrap:hover .sticker-scene{transform:rotate(8deg) scale(1.05)}
.sticker-face{
  position:absolute;inset:0;
  border-radius:50%;
  overflow:hidden;
}
.sticker-face img{
  width:100%;height:100%;
  object-fit:cover;
  display:block;
  pointer-events:none;
  -webkit-user-drag:none;
}
.sticker-face::after{
  content:'';
  position:absolute;inset:0;
  background:radial-gradient(ellipse at 30% 25%, rgba(255,255,255,0.22) 0%, transparent 60%);
  pointer-events:none;
  border-radius:50%;
  opacity:0;
  transition:opacity 0.3s ease;
}
.sticker-wrap:hover .sticker-face::after{opacity:1}
.sticker-flap{
  position:absolute;
  bottom:0;
  right:0;
  width:42%;
  height:42%;
  overflow:hidden;
  pointer-events:none;

  clip-path:polygon(100% 0,100% 100%,0 100%);
  transform-origin:100% 100%;
  transform:rotate(0deg);
  transition:transform .35s cubic-bezier(.34,1.56,.64,1);

  filter:drop-shadow(-4px -4px 10px rgba(0,0,0,.35));
}
.sticker-wrap:hover .sticker-flap,
.sticker-wrap.touch-active .sticker-flap{
  transform:rotate(-28deg) scale(1.05);
}
.sticker-flap img{
  position:absolute;
  width:270%;
  height:270%;
  right:0;
  bottom:0;

  object-fit:cover;
  transform-origin:100% 100%;
  transform:rotate(42deg) scale(.92);

  pointer-events:none;
  -webkit-user-drag:none;

  filter:brightness(.7);
}
.sticker-flap::after{
  content:'';
  position:absolute;inset:0;
  background:linear-gradient(135deg,transparent 35%,rgba(0,0,0,0.4) 100%);
  border-radius:0 0 50% 0;
}
@media(max-width:640px){
  .sticker-wrap{right:0.5rem;top:110px;width:100px;height:100px}
}

@media(max-width:600px){
  .nav-tabs{gap:2px}.nav-tab{font-size:12px;padding:5px 9px}
  .fg-row{grid-template-columns:1fr}
  .stats-bar{gap:1.5rem}
  .track-row{flex-direction:column}
}

@keyframes electricFlow{
  0%{background-position:200% 0;}
  100%{background-position:-200% 0;}
}

@keyframes electricPulse{
  0%{transform:translateX(-100%);}
  100%{transform:translateX(100%);}
}

@keyframes pulseDot{
  0%,100%{transform:scale(1);}
  50%{transform:scale(1.2);}
}

/* ── 3D Customizer ─────────────────────────────────────────────── */
.nav-tab.customizer-tab{
  background:linear-gradient(135deg,rgba(212,86,26,0.18),rgba(200,169,126,0.12));
  color:var(--tan);
  border:1px solid rgba(200,169,126,0.22);
  font-weight:500;
  letter-spacing:0.3px;
}
.nav-tab.customizer-tab:hover,.nav-tab.customizer-tab.active{
  background:linear-gradient(135deg,rgba(212,86,26,0.35),rgba(200,169,126,0.2));
  color:#fff;
  border-color:var(--tan);
}
.customizer-layout{
  display:grid;
  grid-template-columns:1fr 320px;
  gap:1.5rem;
  height:calc(100vh - 200px);
  min-height:540px;
}
@media(max-width:860px){
  .customizer-layout{grid-template-columns:1fr;height:auto}
  .customizer-canvas-wrap{height:55vw;min-height:300px}
  .customizer-panel{max-height:none}
}
.customizer-canvas-wrap{
  position:relative;
  border-radius:16px;
  overflow:hidden;
  background:radial-gradient(ellipse at 40% 35%,#2c1a0e 0%,#0d0704 70%);
  box-shadow:0 8px 40px rgba(0,0,0,0.45);
  border:1px solid rgba(200,169,126,0.1);
}
#shoe-canvas{width:100%;height:100%;display:block;cursor:grab}
#shoe-canvas:active{cursor:grabbing}
.canvas-hint{
  position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);
  background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);
  color:rgba(255,255,255,0.5);font-size:11px;padding:5px 14px;
  border-radius:20px;pointer-events:none;white-space:nowrap;
  border:1px solid rgba(255,255,255,0.08);transition:opacity 0.5s;
}
.canvas-loading{
  position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:1rem;
  background:radial-gradient(ellipse at 40% 35%,#2c1a0e,#0d0704);
}
.loading-ring{
  width:44px;height:44px;border-radius:50%;
  border:3px solid rgba(200,169,126,0.15);
  border-top-color:var(--tan);
  animation:spin 0.9s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-text{color:rgba(200,169,126,0.6);font-size:12px;letter-spacing:1px;text-transform:uppercase}

.customizer-panel{
  display:flex;flex-direction:column;gap:1rem;
  overflow-y:auto;padding-right:2px;
}
.customizer-panel::-webkit-scrollbar{width:3px}
.customizer-panel::-webkit-scrollbar-thumb{background:var(--light);border-radius:3px}
.cp-card{
  background:#fff;border:1px solid rgba(107,66,38,0.12);
  border-radius:14px;padding:1.25rem;
}
.cp-card-title{
  font-family:'Playfair Display',serif;font-size:14px;font-weight:700;
  color:var(--dark);margin-bottom:1rem;display:flex;align-items:center;gap:8px;
}
.cp-card-title span{font-size:18px}
.parts-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.625rem}
.part-btn{
  display:flex;align-items:center;gap:8px;padding:7px 10px;
  border-radius:8px;border:1.5px solid rgba(107,66,38,0.15);
  background:var(--cream);cursor:pointer;transition:all 0.18s;
  font-size:12px;font-weight:500;color:var(--dark);font-family:inherit;
  white-space:nowrap;
}
.part-btn:hover{border-color:var(--accent);background:#fff}
.part-btn.active{border-color:var(--accent);background:rgba(212,86,26,0.06);color:var(--accent)}
.part-color-dot{
  width:18px;height:18px;border-radius:50%;flex-shrink:0;
  border:2px solid rgba(0,0,0,0.12);box-shadow:inset 0 0 0 1px rgba(255,255,255,0.3);
  transition:background 0.2s;
}
.color-picker-area{margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid rgba(107,66,38,0.08)}
.color-picker-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.5rem}
.color-swatches{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:0.625rem}
.swatch{
  width:26px;height:26px;border-radius:6px;cursor:pointer;
  border:2px solid transparent;transition:all 0.15s;flex-shrink:0;
  box-shadow:0 1px 4px rgba(0,0,0,0.2);
}
.swatch:hover{transform:scale(1.12);border-color:rgba(0,0,0,0.25)}
.swatch.selected{border-color:var(--accent);box-shadow:0 0 0 2px rgba(212,86,26,0.35)}
.color-hex-row{display:flex;align-items:center;gap:8px}
.color-hex-input{
  flex:1;border:1px solid rgba(107,66,38,0.2);border-radius:6px;
  padding:6px 10px;font-size:12px;font-family:monospace;background:var(--cream);
  color:var(--dark);outline:none;
}
.color-hex-input:focus{border-color:var(--accent)}
.native-picker{width:32px;height:32px;border:none;cursor:pointer;background:none;padding:0}

.preset-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.625rem}
.preset-btn{
  border-radius:10px;border:1.5px solid rgba(107,66,38,0.12);
  overflow:hidden;cursor:pointer;transition:all 0.2s;background:none;padding:0;
  font-family:inherit;
}
.preset-btn:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.12)}
.preset-btn.active-preset{border-color:var(--accent);box-shadow:0 0 0 2px rgba(212,86,26,0.25)}
.preset-swatch-row{display:flex;height:20px}
.preset-swatch{flex:1;height:100%}
.preset-name{font-size:10px;padding:4px;text-align:center;background:#fff;color:var(--dark);font-weight:500}

.env-btns{display:flex;gap:0.5rem;flex-wrap:wrap}
.env-btn{
  flex:1;min-width:0;padding:7px 6px;border-radius:8px;border:1.5px solid rgba(107,66,38,0.15);
  background:var(--cream);cursor:pointer;font-size:11px;font-weight:500;color:var(--muted);
  transition:all 0.18s;font-family:inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.env-btn:hover{border-color:var(--tan);color:var(--dark)}
.env-btn.active-env{background:var(--dark);color:var(--tan);border-color:var(--dark)}

.cta-book-btn{
  width:100%;background:linear-gradient(135deg,var(--accent),#b83d0e);
  color:#fff;border:none;padding:13px;border-radius:10px;
  font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;
  transition:opacity 0.15s;text-decoration:none;display:block;text-align:center;
  box-shadow:0 4px 16px rgba(212,86,26,0.35);margin-top:auto;
}
.cta-book-btn:hover{opacity:0.88}
.reset-btn{
  width:100%;background:transparent;color:var(--muted);border:1px solid rgba(107,66,38,0.18);
  padding:9px;border-radius:8px;font-size:12px;cursor:pointer;font-family:inherit;transition:all 0.15s;
}
.reset-btn:hover{color:var(--accent);border-color:var(--accent)}
.customizer-page-wrap{padding:1.25rem 1.75rem 3rem;max-width:1200px;margin:0 auto}

/* ── Gallery ──────────────────────────────────────────────── */
.gallery-filter{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}
.filter-btn{padding:6px 16px;border-radius:20px;border:1px solid rgba(107,66,38,0.18);background:var(--cream);font-size:12px;font-weight:500;cursor:pointer;font-family:inherit;transition:all .18s;color:var(--muted)}
.filter-btn:hover,.filter-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(265px,1fr));gap:1.25rem}
.gal-card{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:14px;overflow:hidden;transition:transform .25s cubic-bezier(.34,1.56,.64,1),box-shadow .25s}
.gal-card:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(107,66,38,.14)}
.gal-pair{display:grid;grid-template-columns:1fr 1fr;position:relative}
.gal-half{aspect-ratio:1;position:relative;overflow:hidden;background:#2c1a0e}
.gal-half img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s}
.gal-card:hover .gal-half img{transform:scale(1.04)}
.gal-half.before img{filter:grayscale(30%) sepia(40%) brightness(0.6) contrast(1.2) saturate(0.6)}
.gal-half.before.raw img{filter:none}
.gal-label{position:absolute;top:7px;left:7px;background:rgba(0,0,0,.55);color:#fff;font-size:9px;letter-spacing:.8px;text-transform:uppercase;padding:3px 7px;border-radius:4px;z-index:2}
.gal-label.after-lbl{background:rgba(212,86,26,.82)}
.gal-divider{position:absolute;left:50%;top:0;bottom:0;width:2px;background:rgba(255,255,255,.82);z-index:3;display:flex;align-items:center;justify-content:center}
.gal-divider-handle{width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.28);display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--dark);font-weight:700;flex-shrink:0}
.gal-info{padding:.9rem 1.125rem 1rem}
.gal-tag{display:inline-block;background:rgba(212,86,26,.1);color:var(--accent);font-size:10px;font-weight:500;padding:3px 10px;border-radius:12px;margin-bottom:.45rem}
.gal-name{font-family:'Playfair Display',serif;font-size:14px;font-weight:700;margin-bottom:.2rem;line-height:1.3}
.gal-stars{color:#F4A623;font-size:12px;letter-spacing:1px;margin-bottom:.3rem}
.gal-desc{font-size:11px;color:var(--muted);line-height:1.55;margin-bottom:.625rem}
.gal-meta{display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--muted)}
.gal-ta{font-weight:500;color:var(--dark)}
.gal-book{font-size:11px;color:var(--accent);text-decoration:none;font-weight:500}
.gal-book:hover{text-decoration:underline}
.gal-empty{text-align:center;padding:3rem 1.5rem;color:var(--muted);font-size:13px;grid-column:1/-1}
/* ── Gift Cards ───────────────────────────────────────── */
.gc-layout{display:grid;grid-template-columns:1fr 340px;gap:1.75rem;align-items:start}
@media(max-width:700px){.gc-layout{grid-template-columns:1fr}}
.gc-preview{background:linear-gradient(135deg,var(--dark) 0%,#3d1f0e 100%);border-radius:18px;padding:1.75rem;aspect-ratio:1.586/1;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 12px 40px rgba(0,0,0,.18);margin-bottom:1.25rem}
.gc-preview-logo{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;color:var(--tan)}.gc-preview-logo span{color:var(--accent)}
.gc-preview-tag{font-size:10px;color:rgba(200,169,126,.45);letter-spacing:.6px;margin-top:3px}
.gc-preview-amt{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:700;color:#fff;text-align:right}
.gc-preview-code{font-family:monospace;font-size:12px;color:rgba(200,169,126,.55);letter-spacing:2px}
.gc-preview-valid{font-size:10px;color:rgba(200,169,126,.38);text-align:right}
.gc-perks{font-size:13px;color:var(--muted);line-height:2}
.gc-card{background:#fff;border-radius:14px;padding:1.5rem;border:1px solid rgba(107,66,38,.08);box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:1.25rem}
.gc-card h3{font-family:'Playfair Display',serif;font-size:1.05rem;margin-bottom:1rem}
.gc-pills{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.75rem}
.gc-pill{border:1.5px solid rgba(107,66,38,.18);border-radius:8px;padding:7px 16px;font-size:13px;font-weight:500;cursor:pointer;background:none;color:var(--dark);font-family:inherit;transition:all .15s}
.gc-pill:hover,.gc-pill.active{background:var(--dark);color:var(--tan);border-color:var(--dark)}
.gc-fg{margin-bottom:.8rem}
.gc-fg label{display:block;font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.gc-fg input,.gc-fg textarea{width:100%;border:1.5px solid rgba(107,66,38,.18);border-radius:9px;padding:9px 13px;font-size:13px;font-family:inherit;background:var(--cream);color:var(--dark);outline:none;transition:border-color .15s;resize:vertical}
.gc-fg input:focus,.gc-fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,86,26,.1)}
.gc-fg input::placeholder,.gc-fg textarea::placeholder{color:rgba(107,66,38,.32)}
.gc-divider{font-family:'Playfair Display',serif;font-size:.85rem;font-weight:700;margin:.9rem 0 .7rem;padding-bottom:.5rem;border-bottom:1px solid rgba(107,66,38,.1)}
.gc-submit{width:100%;background:var(--accent);color:#fff;border:none;padding:12px;border-radius:9px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;transition:opacity .15s;margin-top:.5rem}
.gc-submit:hover{opacity:.88}
.gc-balance-result{padding:12px 15px;border-radius:9px;font-size:13px;margin-top:.75rem;line-height:1.6}
.gc-balance-ok{background:#f0faf4;border:1.5px solid rgba(34,139,34,.25);color:#1a5c2a}
.gc-balance-err{background:#fff8f6;border:1.5px solid rgba(212,86,26,.25);color:#8B2200}
</style>
</head>
<body>
<nav>
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <div style="display:flex;align-items:center">
    <div class="nav-tabs">
      <?php
      $tabs = ['services'=>'Services','booking'=>'Booking','tracker'=>'Track Order','calculator'=>'Quote','reviews'=>'Reviews','gallery'=>'Gallery','giftcard'=>'🎁 Gift Cards','about'=>'About Us','customizer'=>'✦ 3D Customize'];
      if (!$settings['gift_cards']) unset($tabs['giftcard']);
      foreach ($tabs as $key => $label) {
          $active = ($page === $key) ? ' active' : '';
          $extra  = ($key === 'customizer') ? ' customizer-tab' : '';
          echo "<a href=\"solemate_homepage.php?page={$key}\" class=\"nav-tab{$active}{$extra}\">{$label}</a>";
      }
      ?>
    </div>
    <div style="display:flex;align-items:center;gap:4px;margin-left:0.5rem;border-left:1px solid rgba(255,255,255,0.1);padding-left:0.75rem">
      <?php if (isCustomerLoggedIn()): ?>
        <a href="solemate_customer_dashboard.php" class="nav-tab" title="My account"
           style="white-space:nowrap">
          <span style="margin-right:4px;font-size:14px">👤</span><?= getCustomerName() ?>
        </a>
      <?php else: ?>
        <a href="solemate_customer_login.php" class="admin-link" style="white-space:nowrap">Sign in</a>
        <a href="solemate_customer_register.php" class="admin-link" style="white-space:nowrap">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php if ($page === 'services'): ?>
<div class="hero">
  <div class="hero-badge">Est. 2008 · Durban's Finest</div>
  <h1>Your shoes deserve<br><em>a second life.</em></h1>
  <p>Professional shoe cleaning, restoration, and repair. We treat every pair like it's our own.</p>
  <div class="hero-btns">
    <a href="solemate_homepage.php?page=booking" class="btn-p">Book a service</a>
    <a href="solemate_homepage.php?page=tracker" class="btn-s">Track my order</a>
  </div>

  <!-- StickerPeel -->
  <div class="sticker-wrap" id="stickerWrap">
    <div class="sticker-scene" id="stickerScene">
      <div class="sticker-face">
        <img src="includes/Solemate_sticker.png" alt="SoleMate sticker">
      </div>
      <div class="sticker-flap">
        <img src="includes/Solemate_sticker.png" alt="">
      </div>
    </div>
  </div>
</div>
<div class="stats-bar">
  <?php $orderCount = $pdo->query("SELECT COUNT(*) FROM dbo.booking")->fetchColumn(); ?>
  <div><div class="stat-num"><?= number_format(max((int)$orderCount, 12847)) ?>+</div><div class="stat-lbl">Booking completed</div></div>
  <div><div class="stat-num">4.9 ★</div><div class="stat-lbl">Average rating</div></div>
  <div><div class="stat-num">48h</div><div class="stat-lbl">Avg turnaround</div></div>
  <div><div class="stat-num">15+</div><div class="stat-lbl">Years experience</div></div>
</div>
<?php endif; ?>

<?php if ($page !== 'customizer'): ?>
<div class="page-wrap">
<?php endif; ?>

<?php if ($page === 'services'): ?>
<div class="sec-label">What we offer</div>
<div class="sec-title">Expert care for every pair</div>
<div class="sec-sub">From sneakers to leather dress shoes — our certified cobblers handle it all. Click a service to book.</div>
<?php
// Card visual config keyed by service id from DB
$svc_vis = [
  'deep-clean'          => ['bg'=>'linear-gradient(145deg,#c8a97e 0%,#8b6542 60%,#5a3e26 100%)', 'h'=>210, 'dark'=>false],
  'sole-repair'         => ['bg'=>'linear-gradient(145deg,#2c1a0e 0%,#6b4226 60%,#8b5c38 100%)', 'h'=>240, 'dark'=>false],
  'leather-restoration' => ['bg'=>'linear-gradient(145deg,#d4561a 0%,#8b2200 60%,#4a1200 100%)', 'h'=>260, 'dark'=>false],
  'sneaker-whitening'   => ['bg'=>'linear-gradient(145deg,#e8d5be 0%,#c8a97e 60%,#a07850 100%)', 'h'=>210, 'dark'=>true],
  'heel-replacement'    => ['bg'=>'linear-gradient(145deg,#3d2010 0%,#1a0f08 60%,#0d0704 100%)', 'h'=>230, 'dark'=>false],
  'full-restoration'    => ['bg'=>'linear-gradient(145deg,#d4561a 0%,#6b2400 50%,#1a0f08 100%)', 'h'=>270, 'dark'=>false],
];
$fallback_vis = ['bg'=>'linear-gradient(145deg,#6b4226,#1a0f08)', 'h'=>220, 'dark'=>false];
?>
<div class="list" id="svc-list" style="min-height:400px">
  <?php foreach ($services as $idx => $s):
    $key = strtolower(str_replace(' ','-',$s['service_name']));
    $vis = $svc_vis[$key] ?? $fallback_vis;
    $textColor = $vis['dark'] ? '#1A0F08' : '#fff';
    $descColor = $vis['dark'] ? 'rgba(0,0,0,0.65)' : 'rgba(255,255,255,0.72)';
    $priceColor = $vis['dark'] ? '#D4561A' : '#FAC775';
    $unitColor  = $vis['dark'] ? 'rgba(0,0,0,0.5)' : 'rgba(255,255,255,0.55)';
  ?>
  <div class="item-wrapper"
     data-idx="<?= $idx ?>"
     data-h="<?= $vis['h'] ?>"
     style="opacity:0">
    <!-- Wishlist heart button -->
    <button class="wl-btn<?= isset($customerWishlistIds[$s['id']])?' wl-active':'' ?>"
            data-sid="<?= (int)$s['id'] ?>"
            title="<?= isset($customerWishlistIds[$s['id']])?'Remove from wishlist':'Save to wishlist' ?>"
            onclick="toggleWishlist(this,<?= (int)$s['id'] ?>)">
      <?= isset($customerWishlistIds[$s['id']])?'♥':'♡' ?>
    </button>
    <a href="<?= $s['service_key'] === 'custom-shoes'
        ? 'solemate_homepage.php?page=customizer'
        : 'solemate_homepage.php?page=booking&service_id=' . $s['id'] ?>"
      class="item-img" style="background:<?= $vis['bg'] ?>;display:flex;flex-direction:column;justify-content:flex-end;text-decoration:none;">
      <div class="svc-icon-bg"><?= $s['icon'] ?></div>
      <div>
        <div class="svc-name" style="color:<?= $textColor ?>"><?= e($s['service_name']) ?></div>
        <div class="svc-desc" style="color:<?= $descColor ?>"><?= e($s['description']) ?></div>
        <div class="svc-price-row">
          <span class="svc-price" style="color:<?= $priceColor ?>">R<?= number_format($s['price'],0) ?></span>
          <span class="svc-price" style="color:<?= $unitColor ?>"><span><?= $s['price_from'] ? 'from' : 'per pair' ?></span></span>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($page === 'booking'): ?>
<div class="sec-label">Reserve your slot</div>
<div class="sec-title">Book a service</div>
<div class="sec-sub">Drop-off, courier, or home collection available. You'll get a unique order number immediately.</div>

<?php if ($newBooking && isset($_GET['oid'])): ?>
  <div class="alert alert-success">
    <h3>✅ Booking confirmed!</h3>
    <p>Your order number is <span class="order-id-chip"><?= e($_GET['oid']) ?></span><br>
    Keep this safe — paste it on the Track Order page to follow progress.<br>
    We'll be in touch at your email address to confirm the details.</p>
    <a href="solemate_homepage.php?page=tracker&oid=<?= urlencode($_GET['oid']) ?>" class="btn-p" style="margin-top:0.875rem;display:inline-block;font-size:13px;padding:9px 22px">Track this order →</a>
  </div>
<?php endif; ?>

<?php if (!empty($bookingErrors)): ?>
  <div class="alert alert-error">
    <strong>Please fix the following:</strong>
    <ul><?php foreach ($bookingErrors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
  </div>
<?php endif; ?>

<div class="form-wrap">
  <div class="form-ttl">Your booking details</div>
  <form action="solemate_process_order.php" method="POST">
    <div class="fg-row">
      <div class="fg"><label>First name *</label><input type="text" name="customer_name" placeholder="First name" required></div>
      <div class="fg"><label>Surname</label><input type="text" name="surname" placeholder="Surname"></div>
    </div>
    <div class="fg"><label>Email *</label><input type="email" name="email" placeholder="example@email.com" required></div>
    <div class="fg"><label>Phone</label><input type="tel" name="phone" placeholder="+27 82 000 0000"></div>
    <div class="fg">
      <label>Service *</label>
      <select name="service_id" required>
        <option value="">— Select a service —</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= $s['id'] ?>"<?= ($preselectedId === (int)$s['id']) ? ' selected' : '' ?>>
            <?= e($s['service_name']) ?> — R<?= number_format($s['price'], 0) ?><?= $s['price_from'] ? '+' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg-row" id="quote-extras" style="<?= $fromQuote ? '' : 'display:none' ?>">
      <div class="fg">
        <label>Number of pairs</label>
        <select name="pairs">
          <option value="1"<?= $preselectedPairs === 1 ? ' selected' : '' ?>>1 pair</option>
          <option value="2"<?= $preselectedPairs === 2 ? ' selected' : '' ?>>2 pairs (−10%)</option>
          <option value="3"<?= $preselectedPairs === 3 ? ' selected' : '' ?>>3 pairs (−15%)</option>
          <option value="4"<?= $preselectedPairs >= 4 ? ' selected' : '' ?>>4+ pairs (−20%)</option>
        </select>
      </div>
      <div class="fg">
        <label>Rush service (24h)</label>
        <select name="rush">
          <option value="0"<?= $preselectedRush === 0 ? ' selected' : '' ?>>No</option>
          <option value="150"<?= $preselectedRush === 150 ? ' selected' : '' ?>>Yes (+R150)</option>
        </select>
      </div>
    </div>
    <div class="fg"><label>Shoe brand / type</label><input type="text" name="shoe_type" placeholder="e.g. Nike Air Max, Oxford Brogues, Timberlands"></div>
    <div class="fg-row">
      <div class="fg"><label>Drop-off date *</label><input type="date" name="drop_date" min="<?= date('Y-m-d') ?>" required></div>
      <div class="fg">
        <label>Collection method</label>
        <select name="collection">
          <option value="drop-off">Drop-off (free)</option>
          <option value="courier">Courier (+R80)</option>
          <option value="home">Home collection (+R120)</option>
        </select>
      </div>
    </div>
    <div class="fg"><label>Notes</label><textarea name="notes" rows="2" placeholder="Any damage, stains, or special instructions..."></textarea></div>
    <button type="submit" class="sub-btn">Confirm booking →</button>
  </form>
</div>

<?php elseif ($page === 'tracker'): ?>
<div class="sec-label">Order status</div>
<div class="sec-title">Track your order</div>
<div class="sec-sub">Enter your order number to see live progress.</div>

<div class="tracker-wrap">
  <form method="GET" action="solemate_homepage.php">
    <input type="hidden" name="page" value="tracker">
    <div class="track-row">
      <input class="track-inp" name="oid" placeholder="e.g. SM-20240421" value="<?= e($_GET['oid'] ?? '') ?>">
      <button type="submit" class="track-btn">Track order</button>
    </div>
  </form>
  <div class="hint">
    Try: <a href="solemate_homepage.php?page=tracker&oid=SM-20240421">SM-20240421</a>,
    <a href="solemate_homepage.php?page=tracker&oid=SM-20240388">SM-20240388</a>,
    <a href="solemate_homepage.php?page=tracker&oid=SM-20240372">SM-20240372</a>
  </div>

  <?php if ($trackError): ?>
    <div class="not-found"><?= $trackError ?></div>
  <?php endif; ?>
 
  <?php if ($trackResult):
    $o    = $trackResult;
    $isCustom  = (($o['service_key'] ?? '') === 'custom-shoes'
                  || strpos($o['shoe_type'] ?? '', 'Custom') === 0);
    $isDropOff = ($o['collection'] === 'drop-off');
    if ($isCustom) {
        // Custom shoe manufacturing pipeline
        $statusStep = $isDropOff
            ? ['Design confirmed'=>1,'Pattern cut'=>2,'Stitching'=>3,'Sole bonding'=>4,'Finishing'=>5,'Dispatched'=>6]
            : ['Pending'=>1,'Design confirmed'=>2,'Pattern cut'=>3,'Stitching'=>4,'Sole bonding'=>5,'Finishing'=>6,'Dispatched'=>7];
        $stepLabels = $isDropOff
            ? ['Design confirmed','Pattern cut','Stitching','Sole bonding','Finishing','Dispatched']
            : ['Pending','Design confirmed','Pattern cut','Stitching','Sole bonding','Finishing','Dispatched'];
    } else {
        $statusStep = $isDropOff
            ? ['Received'=>1,'Assessment'=>2,'In service'=>3,'Quality check'=>4,'Ready'=>5]
            : ['Pending'=>1,'Received'=>2,'Assessment'=>3,'In service'=>4,'Quality check'=>5,'Ready'=>6];
        $stepLabels = $isDropOff
            ? ['Received','Assessment','In service','Quality check','Ready']
            : ['Pending','Received','Assessment','In service','Quality check','Ready'];
    }
    $totalSteps = count($stepLabels);
    $step = $statusStep[$o['status']] ?? 1;
    $pct  = ($totalSteps > 1) ? round(($step - 1) / ($totalSteps - 1) * 100) : 0;
    $collectionLabel = ['drop-off'=>'Drop-off','courier'=>'Courier','home'=>'Home collection'][$o['collection']] ?? $o['collection'];
  ?>
    <hr class="divider">
    <div class="steps-wrap">
      <div class="steps-track"></div>
      <div class="steps-fill" style="width:<?= $pct ?>%"></div>
      <?php for ($i = 1; $i <= $totalSteps; $i++):
        $cls = $i < $step ? 'done' : ($i === $step ? 'active' : '');
        $dot = $i < $step ? '✓' : $i;
      ?>
      <div class="step <?= $cls ?>">
        <div class="step-dot"><?= $dot ?></div>
        <div class="step-lbl"><?= $stepLabels[$i - 1] ?></div>
      </div>
      <?php endfor; ?>
    </div>
    <div class="order-box">
      <strong>Order <?= e($o['order_ref']) ?></strong> &nbsp;·&nbsp; <?= e($o['service_name'] ?? '—') ?><br>
      Customer: <?= e($o['customer_name']) ?> &nbsp;|&nbsp;
      Shoe: <?= e($o['shoe_type'] ?: 'Not specified') ?><br>
      Received: <?= date('d M Y', strtotime($o['order_date'])) ?> &nbsp;|&nbsp;
      Drop-off: <strong><?= $o['drop_date'] ? date('d M Y', strtotime($o['drop_date'])) : 'TBC' ?></strong><br>
      Collection: <?= $collectionLabel ?><br>
      Status: <strong style="color:var(--accent)"><?= e($o['status'] ?? 'Unknown') ?></strong>
    </div>
  <?php endif; ?>
</div>
<p style="margin-top:1.25rem;font-size:12px;color:var(--muted)">
  Order number sent via email at booking · Questions? Call <strong style="color:var(--dark)">031 555 0123</strong>
</p>

<?php elseif ($page === 'calculator'): ?>
<div class="sec-label">Instant quote</div>
<div class="sec-title">Price calculator</div>
<div class="sec-sub">Prices loaded live from our database. Final price confirmed after assessment.</div>
<div class="calc-card">
  <div class="calc-row">
    <span class="calc-lbl">Service type</span>
    <select class="calc-ctrl" id="cs" onchange="calcP()">
      <?php foreach ($services as $s): ?>
      <option value="<?= $s['price'] ?>" data-id="<?= $s['id'] ?>"><?= e($s['service_name']) ?> — R<?= number_format($s['price'], 0) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="calc-row">
    <span class="calc-lbl">Number of pairs</span>
    <select class="calc-ctrl" id="cp" onchange="calcP()">
      <option value="1">1 pair</option>
      <option value="2">2 pairs (−10%)</option>
      <option value="3">3 pairs (−15%)</option>
      <option value="4">4+ pairs (−20%)</option>
    </select>
  </div>
  <div class="calc-row">
    <span class="calc-lbl">Collection method</span>
    <select class="calc-ctrl" id="cc" onchange="calcP()">
      <option value="0">Drop-off (free)</option>
      <option value="80">Courier (+R80)</option>
      <option value="120">Home collection (+R120)</option>
    </select>
  </div>
  <div class="calc-row">
    <span class="calc-lbl">Rush service (24h)</span>
    <select class="calc-ctrl" id="cr" onchange="calcP()">
      <option value="0">No</option>
      <option value="150">Yes (+R150)</option>
    </select>
  </div>
  <div class="calc-total">
    <span class="total-lbl">Estimated total</span>
    <span class="total-price" id="cres">R0</span>
  </div>
  <a href="solemate_homepage.php?page=booking" id="calc-book-link" class="calc-book-btn">Book this service →</a>
</div>
<p style="margin-top:0.875rem;font-size:12px;color:var(--muted)">* Estimates only. Complex repairs may vary after assessment.</p>
<script>
function calcP() {
  const cs    = document.getElementById('cs');
  const base  = parseFloat(cs.value) || 0;
  const pairs = parseInt(document.getElementById('cp').value) || 1;
  const col   = parseInt(document.getElementById('cc').value) || 0;
  const rush  = parseInt(document.getElementById('cr').value) || 0;
  const disc  = {1:1, 2:0.9, 3:0.85, 4:0.8}[pairs] || 0.8;
  const total = Math.round(base * pairs * disc) + col + rush;
  document.getElementById('cres').textContent = 'R' + total.toLocaleString('en-ZA');
  const id = cs.options[cs.selectedIndex].dataset.id;
  document.getElementById('calc-book-link').href =
    'solemate_homepage.php?page=booking&service_id=' + id +
    '&pairs=' + pairs +
    '&rush=' + rush;
}
calcP();
</script>

<?php elseif ($page === 'reviews'): ?>
<div class="sec-label">Customer stories</div>
<div class="sec-title">What our clients say</div>
<div class="sec-sub">Over 2,400 happy customers in Durban and beyond.</div>
<div class="rating-summary">
  <div class="big-score">4.9</div>
  <div>
    <div class="big-stars">★★★★★</div>
    <div class="rating-note">Based on <?= number_format(count($reviews) + 2394) ?>+ reviews</div>
  </div>
</div>
<div class="rev-grid">
  <?php foreach ($reviews as $r): ?>
  <div class="rev-card">
    <div class="stars"><?= str_repeat('★', (int)$r['stars']) ?></div>
    <div class="rev-text">"<?= e($r['review_text']) ?>"</div>
    <div class="reviewer">
      <div class="avatar"><?= e($r['initials']) ?></div>
      <div>
        <div class="rev-name"><?= e($r['customer']) ?></div>
        <div class="rev-loc"><?= e($r['location']) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div style="background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:12px;padding:1.5rem;margin-top:1.5rem;max-width:460px">
  <div style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:0.5rem">Happy with your service?</div>
  <p style="font-size:13px;color:var(--muted);margin-bottom:1rem;line-height:1.6">We'd love to hear from you. Help others find SoleMate.</p>
  <a href="mailto:hello@solemate.co.za?subject=My SoleMate Review" class="btn-p" style="font-size:13px;padding:10px 22px">Leave a review →</a>
</div>

<?php elseif ($page === 'gallery'): ?>
<div class="sec-label">Proof of craft</div>
<div class="sec-title">Before And After Gallery</div>
<div class="sec-sub">Real results from our Durban studio. Every pair treated with expert care and obsessive attention to detail.</div>

<?php
// ── Unsplash CDN helper ──────────────────────────────────────────────────────
// All images are free to use under the Unsplash License (unsplash.com/license).
// Photo credits embedded in each item below.
$CDN = 'https://images.unsplash.com/';
$PARAMS = '?w=500&q=80&auto=format&fit=crop';

// ── Gallery seed data (with real Unsplash photo IDs) ────────────────────────
// img_before / img_after are Unsplash photo IDs.
// same_img=true → "before" uses the same photo as "after" but with a dirt CSS filter applied.
$galSeed = [
  [
    'service_key'  => 'deep-clean',
    'shoe_name'    => 'Air Jordan 1 Retro High OG',
    'before_desc'  => 'Dirt-caked uppers, yellowed midsoles, grimy lace eyelets',
    'after_desc'   => 'Crisp box-fresh finish, bright white soles, clean laces',
    'turnaround'   => '48h', 'rating' => 5,
    'img_before'   => 'photo-1566363384123-360c5f6bc369', // Ronit Singh — worn Jordan
    'img_after'    => 'photo-1552346318-f82ee4ffa2d4',    // Taylor Smith — crisp AJ1 red/white
    'same_img'     => false,
    'credit_b'     => 'Ronit Singh', 'credit_a' => 'Taylor Smith',
  ],
  [
    'service_key'  => 'leather-restoration',
    'shoe_name'    => "Church's Oxford Brogue",
    'before_desc'  => 'Deep toe scuffs, cracked leather, faded colour',
    'after_desc'   => 'Full colour match, deep conditioned, mirror-polished toe',
    'turnaround'   => '72h', 'rating' => 5,
    'img_before'   => 'photo-1517137931843-463a3930961c', // Erik-Jan Leusink — brown leather shoes
    'img_after'    => 'photo-1517137931843-463a3930961c',
    'same_img'     => true,
    'credit_b'     => 'Erik-Jan Leusink', 'credit_a' => 'Erik-Jan Leusink',
  ],
  [
    'service_key'  => 'sneaker-whitening',
    'shoe_name'    => 'Nike Air Force 1 Low',
    'before_desc'  => 'Greyed midsoles, oxidised rubber, stained canvas',
    'after_desc'   => 'Factory-white midsoles, restored canvas, blinding clean',
    'turnaround'   => '24h', 'rating' => 5,
    'img_before'   => 'photo-1626379637476-f78d1d86b9f3', // Maria F. Pissioli — worn grey AF1
    'img_after'    => 'photo-1620989928357-71ff9bbf1f34', // Ervan Wirawan — crisp white AF1
    'same_img'     => false,
    'credit_b'     => 'Maria F. Pissioli', 'credit_a' => 'Ervan M Wirawan',
  ],
  [
    'service_key'  => 'sole-repair',
    'shoe_name'    => 'Red Wing Iron Ranger Boot',
    'before_desc'  => 'Worn-through heel stack, detaching outsole, split welt',
    'after_desc'   => 'Vibram heel replaced, outsole resoled, welt restitched',
    'turnaround'   => '5 days', 'rating' => 5,
    'img_before'   => 'photo-1688940596945-64fc5b6bad4a', // Dmytro Glazunov — actual dirty boots
    'img_after'    => 'photo-1606036525923-525fa3b35465', // Glenn Carstens-Peters — clean leather boots
    'same_img'     => false,
    'credit_b'     => 'Dmytro Glazunov', 'credit_a' => 'Glenn Carstens-Peters',
  ],
  [
    'service_key'  => 'leather-restoration',
    'shoe_name'    => 'Louis Vuitton Derby Shoe',
    'before_desc'  => 'Colour transfer on toe cap, cracked varnish, creased vamp',
    'after_desc'   => 'Factory colour matched, re-varnished, crease smoothed',
    'turnaround'   => '4 days', 'rating' => 5,
    'img_before'   => 'photo-1517137931843-463a3930961c',
    'img_after'    => 'photo-1517137931843-463a3930961c',
    'same_img'     => true,
    'credit_b'     => 'Erik-Jan Leusink', 'credit_a' => 'Erik-Jan Leusink',
  ],
  [
    'service_key'  => 'deep-clean',
    'shoe_name'    => 'New Balance 990v5',
    'before_desc'  => 'Heavy mud on upper mesh, caked lug sole, dark lace tips',
    'after_desc'   => 'All colourways restored, mesh brightened, soles degreased',
    'turnaround'   => '48h', 'rating' => 5,
    'img_before'   => 'photo-1518527399940-f3f768f47dd2', // gebhartyler — worn Jordan on court
    'img_after'    => 'photo-1558128242-6e97528bf8fb',    // David Lezcano — clean Jordan
    'same_img'     => false,
    'credit_b'     => 'gebhartyler', 'credit_a' => 'David Lezcano',
  ],
  [
    'service_key'  => 'sneaker-whitening',
    'shoe_name'    => 'Adidas Stan Smith',
    'before_desc'  => 'Yellowed panels, grey toe box, stiff insole',
    'after_desc'   => 'Bright white panels, fresh insole, crisp green heel tab',
    'turnaround'   => '24h', 'rating' => 5,
    'img_before'   => 'photo-1626379637476-f78d1d86b9f3', // Maria F. Pissioli — grey AF1
    'img_after'    => 'photo-1620989928357-71ff9bbf1f34', // Ervan Wirawan — white AF1
    'same_img'     => false,
    'credit_b'     => 'Maria F. Pissioli', 'credit_a' => 'Ervan M Wirawan',
  ],
  [
    'service_key'  => 'sole-repair',
    'shoe_name'    => 'Timberland 6-inch Premium',
    'before_desc'  => 'Delaminating midsole, worn lug pattern, exposed shank',
    'after_desc'   => 'Full resoled with Commando lug, rebonded midsole, waterproofed',
    'turnaround'   => '4 days', 'rating' => 5,
    'img_before'   => 'photo-1688940596945-64fc5b6bad4a', // Dmytro Glazunov — dirty boots
    'img_after'    => 'photo-1606036525923-525fa3b35465', // Glenn Carstens-Peters — clean boots
    'same_img'     => false,
    'credit_b'     => 'Dmytro Glazunov', 'credit_a' => 'Glenn Carstens-Peters',
  ],
  [
    'service_key'  => 'leather-restoration',
    'shoe_name'    => 'Grenson Fred Triple Welt',
    'before_desc'  => 'Rain staining, salt tide marks, lifting welt stitching',
    'after_desc'   => 'Salt marks removed, welt restitched, leather nourished',
    'turnaround'   => '72h', 'rating' => 5,
    'img_before'   => 'photo-1517137931843-463a3930961c',
    'img_after'    => 'photo-1517137931843-463a3930961c',
    'same_img'     => true,
    'credit_b'     => 'Erik-Jan Leusink', 'credit_a' => 'Erik-Jan Leusink',
  ],
];

// Merge DB rows with CDN data (DB rows use seed as source of truth for images)
// If coming from DB, inject img fields from seed by matching shoe_name
$seedByName = [];
foreach ($galSeed as $s) { $seedByName[$s['shoe_name']] = $s; }

if (!empty($galleryItems)) {
    foreach ($galleryItems as &$row) {
        $match = $seedByName[$row['shoe_name']] ?? null;
        if ($match) {
            $row['img_before'] = $row['img_before'] ?? $match['img_before'];
            $row['img_after']  = $row['img_after']  ?? $match['img_after'];
            $row['same_img']   = $row['same_img']   ?? $match['same_img'];
            $row['credit_b']   = $match['credit_b'];
            $row['credit_a']   = $match['credit_a'];
        }
    }
    unset($row);
} else {
    $galleryItems = $galSeed;
}

$galServiceLabels = [
    'deep-clean'          => 'Deep Clean',
    'leather-restoration' => 'Restoration',
    'sneaker-whitening'   => 'Whitening',
    'sole-repair'         => 'Sole Repair',
];
$presentKeys = array_unique(array_column($galleryItems, 'service_key'));
?>

<div class="gallery-filter">
  <button class="filter-btn active" data-filter="all">All services</button>
  <?php foreach ($galServiceLabels as $key => $label):
        if (!in_array($key, $presentKeys)) continue; ?>
  <button class="filter-btn" data-filter="<?= e($key) ?>"><?= e($label) ?></button>
  <?php endforeach; ?>
</div>

<div class="gallery-grid" id="galGrid">
<?php foreach ($galleryItems as $item):
    $sKey         = e($item['service_key']);
    $serviceLabel = $galServiceLabels[$item['service_key']] ?? ucwords(str_replace('-', ' ', $item['service_key']));
    $stars        = str_repeat('★', (int)$item['rating']);
    $imgB         = $CDN . ($item['img_before'] ?? '') . $PARAMS;
    $imgA         = $CDN . ($item['img_after']  ?? '') . $PARAMS;
    $sameImg      = !empty($item['same_img']);
    $bookSvcId    = 0;
    foreach ($services as $svc) {
        if ($svc['service_key'] === $item['service_key']) { $bookSvcId = $svc['id']; break; }
    }
?>
<div class="gal-card" data-service="<?= $sKey ?>">
  <div class="gal-pair">
    <div class="gal-half before<?= $sameImg ? '' : ' raw' ?>">
      <span class="gal-label">Before</span>
      <img src="<?= e($imgB) ?>"
           alt="Before: <?= e($item['shoe_name']) ?>"
           loading="lazy"
           title="Photo by <?= e($item['credit_b'] ?? 'Unsplash') ?> on Unsplash">
    </div>
    <div class="gal-half after">
      <span class="gal-label after-lbl">After</span>
      <img src="<?= e($imgA) ?>"
           alt="After: <?= e($item['shoe_name']) ?>"
           loading="lazy"
           title="Photo by <?= e($item['credit_a'] ?? 'Unsplash') ?> on Unsplash">
    </div>
    <div class="gal-divider"><div class="gal-divider-handle">↔</div></div>
  </div>
  <div class="gal-info">
    <span class="gal-tag"><?= e($serviceLabel) ?></span>
    <div class="gal-name"><?= e($item['shoe_name']) ?></div>
    <div class="gal-stars"><?= $stars ?></div>
    <div class="gal-desc"><strong style="color:var(--muted)">Before:</strong> <?= e($item['before_desc']) ?></div>
    <div class="gal-desc"><strong style="color:var(--accent)">After:</strong> <?= e($item['after_desc']) ?></div>
    <div class="gal-meta">
      <span>Turnaround: <span class="gal-ta"><?= e($item['turnaround']) ?></span></span>
      <?php if ($bookSvcId): ?>
      <a href="solemate_homepage.php?page=booking&service_id=<?= $bookSvcId ?>" class="gal-book">Book this →</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($galleryItems)): ?>
  <div class="gal-empty">No gallery items yet — check back soon!</div>
<?php endif; ?>
</div>

<p style="margin-top:1.5rem;font-size:11px;color:rgba(139,115,85,.55)">
  Illustrative shoe photos via <a href="https://unsplash.com" target="_blank" rel="noopener" style="color:var(--muted)">Unsplash</a>
  (free to use under the <a href="https://unsplash.com/license" target="_blank" rel="noopener" style="color:var(--muted)">Unsplash License</a>).
  Replace with your own before/after photos once uploaded.
</p>
<p style="margin-top:.5rem;font-size:12px;color:var(--muted)">
  Want your shoes to be our next success story?
  <a href="solemate_homepage.php?page=booking" style="color:var(--accent);font-weight:500">Book a service →</a>
</p>

<script>
(function(){
  var btns  = document.querySelectorAll('.filter-btn');
  var cards = document.querySelectorAll('.gal-card');
  btns.forEach(function(btn){
    btn.addEventListener('click', function(){
      btns.forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      var f = btn.dataset.filter;
      var visible = 0;
      cards.forEach(function(c){
        var show = (f === 'all' || c.dataset.service === f);
        c.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      var empty = document.querySelector('.gal-empty');
      if (empty) empty.style.display = visible ? 'none' : '';
    });
  });
})();
</script>

<?php elseif ($page === 'giftcard'): ?>
<?php
  $gcAmounts  = [200, 300, 500, 750, 1000, 1500];
  $custName   = isCustomerLoggedIn() ? getCustomerName()  : '';
  $custEmail  = isCustomerLoggedIn() ? getCustomerEmail() : '';
?>
<div class="sec-label">Give a gift</div>
<div class="sec-title">SoleMate Gift Cards</div>
<div class="sec-sub">Treat someone to fresh kicks — redeemable on any service. Code delivered by email instantly after payment.</div>

<div class="gc-layout" style="margin-top:1.75rem">
  <!-- Left: preview + perks -->
  <div>
    <div class="gc-preview">
      <div>
        <div class="gc-preview-logo">Sole<span>Mate</span></div>
        <div class="gc-preview-tag">SHOE CLEANING &amp; REPAIR · DURBAN</div>
      </div>
      <div>
        <div class="gc-preview-amt" id="gcPreviewAmt">R 0.00</div>
        <div class="gc-preview-code">GC-XXXX-XXXX</div>
        <div class="gc-preview-valid">Valid 1 year from purchase</div>
      </div>
    </div>
    <div class="gc-perks">
      ✅ Redeemable on any SoleMate service<br>
      ✅ Code emailed instantly after payment<br>
      ✅ Valid for 12 months<br>
      ✅ Balance never expires mid-job
    </div>
  </div>

  <!-- Right: buy form + balance check -->
  <div>
    <!-- Buy form -->
    <div class="gc-card">
      <h3>Buy a gift card</h3>
      <form method="POST" action="solemate_gift_card.php">
        <div class="gc-divider" style="margin-top:0;border:none;padding:0;margin-bottom:.7rem;font-size:.8rem">Choose an amount</div>
        <div class="gc-pills">
          <?php foreach ($gcAmounts as $a): ?>
          <button type="button" class="gc-pill" data-amt="<?= $a ?>" onclick="gcSelectAmt(this)">R<?= $a ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="amount" id="gcSelectedAmt" value="">
        <div class="gc-fg">
          <label>Custom amount (R100 – R5,000)</label>
          <input type="number" name="custom_amount" id="gcCustomAmt" min="100" max="5000" step="50"
                 placeholder="e.g. 450" oninput="gcSetCustom(this.value)">
        </div>

        <div class="gc-divider">Recipient</div>
        <div class="gc-fg">
          <label>Recipient name *</label>
          <input type="text" name="recipient_name" placeholder="Who's receiving this?" required maxlength="100">
        </div>
        <div class="gc-fg">
          <label>Recipient email *</label>
          <input type="email" name="recipient_email" placeholder="their@email.com" required maxlength="100">
        </div>
        <div class="gc-fg">
          <label>Personal message <span style="color:rgba(107,66,38,.4);font-weight:400">(optional)</span></label>
          <textarea name="message" rows="2" placeholder="Happy birthday! Enjoy fresh kicks 👟" maxlength="300"></textarea>
        </div>

        <div class="gc-divider">Your details</div>
        <div class="gc-fg">
          <label>Your name *</label>
          <input type="text" name="sender_name" required maxlength="100" value="<?= htmlspecialchars($custName,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="gc-fg">
          <label>Your email *</label>
          <input type="email" name="sender_email" required maxlength="100" value="<?= htmlspecialchars($custEmail,ENT_QUOTES,'UTF-8') ?>">
        </div>

        <button type="submit" class="gc-submit">Purchase gift card — R<span id="gcBtnAmt">0</span></button>
      </form>
    </div>

    <!-- Balance check -->
    <div class="gc-card">
      <h3>Check a gift card balance</h3>
      <form method="POST" action="solemate_homepage.php?page=giftcard">
        <input type="hidden" name="gc_action" value="check">
        <div class="gc-fg">
          <label>Gift card code</label>
          <input type="text" name="gc_code" placeholder="GC-XXXX-XXXX" maxlength="20"
                 style="font-family:monospace;letter-spacing:1px;text-transform:uppercase"
                 value="<?= htmlspecialchars($_POST['gc_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="gc-submit" style="background:var(--dark)">Check balance</button>
      </form>
      <?php if ($gcCheckResult !== null): ?>
      <div class="gc-balance-result <?= $gcCheckResult['ok'] ? 'gc-balance-ok' : 'gc-balance-err' ?>">
        <?php if ($gcCheckResult['ok']): ?>
          <strong>Balance: R<?= number_format((float)$gcCheckResult['card']['balance'], 2) ?></strong><br>
          <span style="font-size:12px">
            Original value: R<?= number_format((float)$gcCheckResult['card']['initial_balance'], 2) ?>
            <?php if (!empty($gcCheckResult['card']['expires_at'])): ?>
            · Expires <?= date('d M Y', strtotime($gcCheckResult['card']['expires_at'])) ?>
            <?php endif; ?>
          </span>
        <?php else: ?>
          <?= htmlspecialchars($gcCheckResult['message'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function gcSelectAmt(btn) {
  document.querySelectorAll('.gc-pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('gcSelectedAmt').value = btn.dataset.amt;
  document.getElementById('gcCustomAmt').value   = '';
  gcUpdatePreview(parseFloat(btn.dataset.amt));
}
function gcSetCustom(val) {
  document.querySelectorAll('.gc-pill').forEach(p => p.classList.remove('active'));
  document.getElementById('gcSelectedAmt').value = '';
  gcUpdatePreview(parseFloat(val) || 0);
}
function gcUpdatePreview(amt) {
  document.getElementById('gcPreviewAmt').textContent = 'R ' + (amt||0).toLocaleString('en-ZA',{minimumFractionDigits:2});
  document.getElementById('gcBtnAmt').textContent     = (amt||0).toLocaleString('en-ZA',{minimumFractionDigits:2});
}
</script>

<?php elseif ($page === 'about'): ?>
<div class="sec-label">Our story</div>
<div class="sec-title">Born in Durban, built on craft</div>

<div class="about-intro" style="margin-top:1rem">
  <p>SoleMate was founded in 2008 by a family of cobblers who believed that great shoes deserve great care. What started as a single bench in Morningside has grown into three workshops across Durban — all united by the same obsession: bringing your favourite pair back to life.</p>
</div>

<div class="values-grid">
  <div class="value-card">
    <span class="value-icon">🧵</span>
    <div class="value-title">Artisan craftsmanship</div>
    <div class="value-desc">Every repair is done by hand. We don't rush, we don't cut corners — we do it right.</div>
  </div>
  <div class="value-card">
    <span class="value-icon">♻️</span>
    <div class="value-title">Sustainable approach</div>
    <div class="value-desc">Restore, don't replace. We divert thousands of pairs from landfill each year.</div>
  </div>
  <div class="value-card">
    <span class="value-icon">🏆</span>
    <div class="value-title">15+ years expertise</div>
    <div class="value-desc">Our cobblers are certified and continuously trained on the latest materials and techniques.</div>
  </div>
  <div class="value-card">
    <span class="value-icon">📦</span>
    <div class="value-title">Guaranteed results</div>
    <div class="value-desc">Not happy? We'll redo the service free of charge. Your satisfaction is our promise.</div>
  </div>
</div>

<div class="sec-label" style="margin-bottom:6px">Find us</div>
<div class="sec-title" style="margin-bottom:8px">Our shops</div>
<p class="sec-sub">Three convenient locations across Durban — drop in, or book online and we'll arrange collection.</p>

<div class="shops-grid">

  <!-- Morningside (Flagship) -->
  <div class="shop-card">
    <div class="shop-card-header">
      <div class="shop-name">Morningside</div>
      <span class="shop-badge">Flagship</span>
    </div>
    <div class="shop-body">
      <div class="flagship-tag">⭐ Main Workshop</div>
      <div class="shop-detail">
        <span class="shop-detail-icon">📍</span>
        <div class="shop-detail-text"><strong>123 Florida Road</strong><br>Morningside, Durban, 4001</div>
      </div>
      <div class="shop-detail">
        <span class="shop-detail-icon">🕐</span>
        <div class="shop-detail-text">
          <div class="shop-hours-grid">
            <span class="shop-hours-day">Mon – Fri</span><span class="shop-hours-time">8:00am – 6:00pm</span>
            <span class="shop-hours-day">Saturday</span><span class="shop-hours-time">9:00am – 2:00pm</span>
            <span class="shop-hours-day">Sunday</span><span class="shop-hours-time">Closed</span>
          </div>
        </div>
      </div>
      <div class="shop-detail">
        <span class="shop-detail-icon">🚗</span>
        <div class="shop-detail-text">Free street parking &amp; on-site bay</div>
      </div>
      <div class="shop-cta">
        <a href="https://www.google.com/maps/search/123+Florida+Road+Morningside+Durban" target="_blank" class="shop-dir-btn">📍 Directions</a>
        <a href="tel:0315550123" class="shop-call-btn">📞 031 555 0123</a>
      </div>
    </div>
  </div>

  <!-- Umhlanga -->
  <div class="shop-card">
    <div class="shop-card-header">
      <div class="shop-name">Umhlanga</div>
      <span class="shop-badge">Branch</span>
    </div>
    <div class="shop-body">
      <div class="shop-detail">
        <span class="shop-detail-icon">📍</span>
        <div class="shop-detail-text"><strong>Shop 14, Umhlanga Village</strong><br>Chartwell Drive, Umhlanga, 4319</div>
      </div>
      <div class="shop-detail">
        <span class="shop-detail-icon">🕐</span>
        <div class="shop-detail-text">
          <div class="shop-hours-grid">
            <span class="shop-hours-day">Mon – Fri</span><span class="shop-hours-time">8:00am – 6:00pm</span>
            <span class="shop-hours-day">Saturday</span><span class="shop-hours-time">9:00am – 3:00pm</span>
            <span class="shop-hours-day">Sunday</span><span class="shop-hours-time">10:00am – 1:00pm</span>
          </div>
        </div>
      </div>
      <div class="shop-detail">
        <span class="shop-detail-icon">🚗</span>
        <div class="shop-detail-text">Undercover parking in village square</div>
      </div>
      <div class="shop-cta">
        <a href="https://www.google.com/maps/search/Chartwell+Drive+Umhlanga+Durban" target="_blank" class="shop-dir-btn">📍 Directions</a>
        <a href="tel:0315550456" class="shop-call-btn">📞 031 555 0456</a>
      </div>
    </div>
  </div>

  <!-- Musgrave -->
  <div class="shop-card">
    <div class="shop-card-header">
      <div class="shop-name">Musgrave</div>
      <span class="shop-badge">Branch</span>
    </div>
    <div class="shop-body">
      <div class="shop-detail">
        <span class="shop-detail-icon">📍</span>
        <div class="shop-detail-text"><strong>Lower Ground, Musgrave Centre</strong><br>Musgrave Road, Berea, 4001</div>
      </div>
      <div class="shop-detail">
        <span class="shop-detail-icon">🕐</span>
        <div class="shop-detail-text">
          <div class="shop-hours-grid">
            <span class="shop-hours-day">Mon – Fri</span><span class="shop-hours-time">9:00am – 6:00pm</span>
            <span class="shop-hours-day">Saturday</span><span class="shop-hours-time">9:00am – 5:00pm</span>
            <span class="shop-hours-day">Sunday</span><span class="shop-hours-time">10:00am – 3:00pm</span>
          </div>
        </div>
      </div>
      <div class="shop-detail">
        <span class="shop-detail-icon">🚗</span>
        <div class="shop-detail-text">Inside Musgrave Centre — ample mall parking</div>
      </div>
      <div class="shop-cta">
        <a href="https://www.google.com/maps/search/Musgrave+Centre+Musgrave+Road+Durban" target="_blank" class="shop-dir-btn">📍 Directions</a>
        <a href="tel:0315550789" class="shop-call-btn">📞 031 555 0789</a>
      </div>
    </div>
  </div>

</div>

<div class="team-blurb">
  <div class="team-blurb-title">Can't make it in? No problem.</div>
  <p>We offer courier collection from anywhere in Durban and the surrounding areas. Book online, drop your shoes at the door, and we'll handle the rest — delivery back to you included.</p>
  <a href="solemate_homepage.php?page=booking" class="btn-p">Book a collection →</a>
</div>

<?php elseif ($page === 'customizer'): ?>
<div style="background:var(--dark);padding:2rem 1.75rem 1.5rem;text-align:center;border-bottom:1px solid rgba(200,169,126,0.1)">
  <div style="display:inline-block;background:rgba(200,169,126,0.12);border:1px solid rgba(200,169,126,0.28);color:var(--tan);font-size:11px;padding:4px 14px;border-radius:20px;margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:1px">3D Shoe Studio</div>
  <h1 style="font-family:'Playfair Display',serif;font-size:clamp(1.6rem,4vw,2.4rem);font-weight:700;color:#fff;line-height:1.2;margin-bottom:0.5rem">Design your <em style="color:var(--tan);font-style:italic">perfect pair</em></h1>
  <p style="font-size:14px;color:rgba(255,255,255,0.48);max-width:420px;margin:0 auto">Rotate, zoom, and customise every part of the shoe. Then book your service with your chosen look.</p>
</div>
<?php endif; ?>

<?php if ($page !== 'customizer'): ?>
</div><!-- /page-wrap -->
<?php endif; ?>

<?php if ($page === 'customizer'): ?>
<div class="customizer-page-wrap">
<?php include 'solemate_customizer.php'; ?>
</div><!-- /customizer-page-wrap -->
<?php endif; ?>


<footer>
  <span class="footer-logo">SoleMate</span>
  123 Florida Road, Morningside, Durban &nbsp;·&nbsp; 031 555 0123 &nbsp;·&nbsp; hello@solemate.co.za<br>
  Mon–Fri 8am–6pm &nbsp;·&nbsp; Sat 9am–2pm<br>
  <span style="font-size:11px;color:rgba(255,255,255,0.28)">&copy; <?= date('Y') ?> SoleMate. All rights reserved.</span>
</footer>

<script>
(function(){
  var list = document.getElementById('svc-list');
  if(!list) return;

  var GAP = 14;

  // 8 launch directions (tx, ty) — card flies in from one of these offsets
  var DIRS = [
    {tx:-160,ty:0},{tx:160,ty:0},{tx:0,ty:-140},{tx:0,ty:140},
    {tx:-130,ty:-110},{tx:130,ty:-110},{tx:-130,ty:110},{tx:130,ty:110}
  ];
  function pickDir(i){ return DIRS[(i*3+5) % DIRS.length]; }

  var cards = Array.from(list.querySelectorAll('.item-wrapper'));
  var positions = [];

  function computeMasonry(){
    var w = list.offsetWidth;
    var cols = w < 480 ? 1 : w < 720 ? 2 : 3;
    var colW = (w - GAP*(cols-1)) / cols;
    var colH = new Array(cols).fill(0);
    positions = cards.map(function(card){
      var h = colW; // square: height === column width
      var col = colH.indexOf(Math.min.apply(null,colH));
      var x = col*(colW+GAP);
      var y = colH[col];
      colH[col] += h + GAP;
      return {x:x, y:y, w:colW, h:h};
    });
    var totalH = Math.max.apply(null,colH);
    list.style.height = totalH+'px';

    // Apply size + hidden starting transform (direction offset + scale down)
    cards.forEach(function(card,i){
      var p = positions[i];
      var d = pickDir(i);
      var isVis = card.dataset.visible === '1';
      card.style.width  = p.w+'px';
      card.style.height = p.h+'px';
      if(!isVis){
        card.style.transition = 'none';
        card.style.transform  = 'translate3d('+(p.x+d.tx)+'px,'+(p.y+d.ty)+'px,0) scale(0.82)';
        card.style.opacity    = '0';
      } else {
        // Already visible — just reposition (resize event)
        card.style.transform = 'translate3d('+p.x+'px,'+p.y+'px,0) scale(1)';
      }
    });

    // Re-run observer after positions are known
    observeCards();
  }

  var observed = false;
  function observeCards(){
    if(observed) return;
    observed = true;
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(!entry.isIntersecting) return;
        var card = entry.target;
        var i = parseInt(card.dataset.idx,10);
        var p = positions[i];
        if(!p) return;
        card.dataset.visible = '1';
        var delay = (i*0.055)+'s';
        card.style.transition =
          'transform 0.62s cubic-bezier(0.34,1.48,0.64,1) '+delay+
          ', opacity 0.45s ease '+delay;
        card.style.transform = 'translate3d('+p.x+'px,'+p.y+'px,0) scale(1)';
        card.style.opacity   = '1';
        io.unobserve(card);
      });
    },{threshold:0.08});
    cards.forEach(function(c){ io.observe(c); });
  }

  // Hover: scale up
  cards.forEach(function(card,i){
    card.addEventListener('mouseenter',function(){
      var p = positions[i];
      if(!p || card.dataset.visible!=='1') return;
      card.style.transition = 'transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s ease';
      card.style.transform  = 'translate3d('+p.x+'px,'+p.y+'px,0) scale(1.028)';
      card.style.zIndex     = '10';
    });
    card.addEventListener('mouseleave',function(){
      var p = positions[i];
      if(!p) return;
      card.style.transition = 'transform 0.3s cubic-bezier(0.34,1.56,0.64,1)';
      card.style.transform  = 'translate3d('+p.x+'px,'+p.y+'px,0) scale(1)';
      card.style.zIndex     = '1';
    });
  });

  computeMasonry();
  var ro = new ResizeObserver(function(){ observed=false; computeMasonry(); });
  ro.observe(list);
})();
</script>

<script>
/* StickerPeel drag */
(function () {
  var wrap  = document.getElementById('stickerWrap');
  var scene = document.getElementById('stickerScene');
  if (!wrap || !scene) return;

  /* Touch active class for CSS peel on mobile */
  wrap.addEventListener('touchstart', function () { wrap.classList.add('touch-active'); }, { passive: true });
  ['touchend', 'touchcancel'].forEach(function (ev) {
    wrap.addEventListener(ev, function () { wrap.classList.remove('touch-active'); }, { passive: true });
  });

  /* Drag — using fixed positioning, so we track raw clientX/Y */
  var isDragging = false;
  var startMouseX, startMouseY;
  var startRight, startTop;
  var velX = 0, velY = 0;
  var lastX, lastY, rafId;

  /* Read initial fixed position from computed style */
  function getFixedPos() {
    var rect = wrap.getBoundingClientRect();
    return { right: window.innerWidth - rect.right, top: rect.top };
  }

  function applyPos(right, top) {
    /* Clamp so sticker stays on screen */
    var rect = wrap.getBoundingClientRect();
    var w = rect.width || 150, h = rect.height || 150;
    right = Math.max(-w * 0.4, Math.min(right, window.innerWidth - w * 0.6));
    top   = Math.max(60,        Math.min(top,  window.innerHeight - h * 0.6));
    wrap.style.right = right + 'px';
    wrap.style.top   = top   + 'px';
  }

  function onPointerDown(e) {
    isDragging = true;
    var pt = e.touches ? e.touches[0] : e;
    startMouseX = pt.clientX;
    startMouseY = pt.clientY;
    var pos = getFixedPos();
    startRight = pos.right;
    startTop   = pos.top;
    lastX = pt.clientX;
    lastY = pt.clientY;
    velX = velY = 0;
    wrap.style.transition  = 'none';
    scene.style.transition = 'none';
    cancelAnimationFrame(rafId);
    e.preventDefault();
  }

  function onPointerMove(e) {
    if (!isDragging) return;
    var pt  = e.touches ? e.touches[0] : e;
    var dx  = pt.clientX - startMouseX;
    var dy  = pt.clientY - startMouseY;
    velX = pt.clientX - lastX;
    velY = pt.clientY - lastY;
    lastX = pt.clientX;
    lastY = pt.clientY;
    /* right decreases as we move right */
    applyPos(startRight - dx, startTop + dy);
    var rot = Math.max(-22, Math.min(22, velX * 0.5));
    scene.style.transform = 'rotate(' + (12 + rot) + 'deg) scale(1.05)';
  }

  function inertiaLoop() {
    velX *= 0.88;
    velY *= 0.88;
    var pos = getFixedPos();
    applyPos(pos.right - velX, pos.top + velY);
    if (Math.abs(velX) > 0.4 || Math.abs(velY) > 0.4) {
      rafId = requestAnimationFrame(inertiaLoop);
    } else {
      wrap.style.transition  = '';
      scene.style.transition = '';
      scene.style.transform  = '';
    }
  }

  function onPointerUp() {
    if (!isDragging) return;
    isDragging = false;
    scene.style.transform = '';
    rafId = requestAnimationFrame(inertiaLoop);
  }

  wrap.addEventListener('mousedown',  onPointerDown);
  wrap.addEventListener('touchstart', onPointerDown, { passive: false });
  window.addEventListener('mousemove',  onPointerMove);
  window.addEventListener('touchmove',  onPointerMove, { passive: false });
  window.addEventListener('mouseup',    onPointerUp);
  window.addEventListener('touchend',   onPointerUp);
})();


(function () {
  var sparkColor  = '#C8A97E';
  var sparkSize   = 10;
  var sparkRadius = 15;
  var sparkCount  = 8;
  var duration    = 400;
  var extraScale  = 1.0;

  /* Canvas setup */
  var canvas = document.createElement('canvas');
  canvas.style.cssText = [
    'position:fixed',
    'top:0',
    'left:0',
    'width:100%',
    'height:100%',
    'pointer-events:none',
    'z-index:9999',
    'display:block',
    'user-select:none'
  ].join(';');
  document.body.appendChild(canvas);

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  /* Easing: ease-out */
  function easeOut(t) { return t * (2 - t); }

  /* Active sparks array */
  var sparks = [];

  /* Animation loop */
  var animId;
  function draw(ts) {
    var ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    sparks = sparks.filter(function (spark) {
      var elapsed = ts - spark.startTime;
      if (elapsed >= duration) return false;

      var progress = elapsed / duration;
      var eased    = easeOut(progress);
      var distance = eased * sparkRadius * extraScale;
      var lineLen  = sparkSize * (1 - eased);

      var x1 = spark.x + distance * Math.cos(spark.angle);
      var y1 = spark.y + distance * Math.sin(spark.angle);
      var x2 = spark.x + (distance + lineLen) * Math.cos(spark.angle);
      var y2 = spark.y + (distance + lineLen) * Math.sin(spark.angle);

      ctx.strokeStyle = sparkColor;
      ctx.lineWidth   = 2;
      ctx.beginPath();
      ctx.moveTo(x1, y1);
      ctx.lineTo(x2, y2);
      ctx.stroke();

      return true;
    });

    animId = requestAnimationFrame(draw);
  }
  animId = requestAnimationFrame(draw);

  /* Fire sparks on every click */
  document.addEventListener('click', function (e) {
    var x   = e.clientX;
    var y   = e.clientY;
    var now = performance.now();
    for (var i = 0; i < sparkCount; i++) {
      sparks.push({
        x:         x,
        y:         y,
        angle:     (2 * Math.PI * i) / sparkCount,
        startTime: now
      });
    }
  });
})();
</script>


<script>
// ── Wishlist heart toggle ─────────────────────────────
async function toggleWishlist(btn, serviceId) {
  const loggedIn = <?= isCustomerLoggedIn() ? 'true' : 'false' ?>;
  if (!loggedIn) {
    window.location.href = 'solemate_customer_login.php?redirect=' +
      encodeURIComponent(window.location.href);
    return;
  }
  btn.disabled = true;
  btn.style.opacity = '0.6';
  try {
    const fd = new FormData();
    fd.append('service_id', serviceId);
    const res = await fetch('solemate_wishlist_toggle.php', { method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.ok) {
      btn.textContent      = data.in_wishlist ? '♥' : '♡';
      btn.title            = data.in_wishlist ? 'Remove from wishlist' : 'Save to wishlist';
      btn.classList.toggle('wl-active', data.in_wishlist);
      // Brief bounce
      btn.style.transform  = 'scale(1.3)';
      setTimeout(() => btn.style.transform = '', 200);
    }
  } catch(e) { console.error('Wishlist error', e); }
  btn.disabled = false;
  btn.style.opacity = '';
}
</script>
</body>
</html>