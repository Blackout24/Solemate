<?php
// ═══════════════════════════════════════════════════════
//  index.php  —  SoleMate Main Website
//  All data comes from shoe_repair_db via PDO (db.php)
// ═══════════════════════════════════════════════════════
require 'includes/solemate_database_connection.php';

$page       = $_GET['page'] ?? 'services';
$validPages = ['services', 'booking', 'tracker', 'calculator', 'reviews', 'about'];
if (!in_array($page, $validPages)) $page = 'services';

$services = $pdo->query("SELECT * FROM services ORDER BY sort_order")->fetchAll();

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
            FROM orders o
            LEFT JOIN services s ON o.service_id = s.id
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

nav{background:var(--dark);padding:0 1.75rem;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(0,0,0,0.35)}
.logo{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--tan);text-decoration:none}
.logo span{color:var(--accent)}
.nav-tabs{display:flex;gap:3px;flex-wrap:wrap}
.nav-tab{color:rgba(255,255,255,0.5);font-size:13px;padding:6px 13px;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;transition:all 0.15s}
.nav-tab:hover{color:var(--tan)}
.nav-tab.active{background:rgba(200,169,126,0.15);color:var(--tan)}
.admin-link{color:rgba(255,255,255,0.28);font-size:12px;text-decoration:none;margin-left:0.5rem;border-left:1px solid rgba(255,255,255,0.1);padding-left:0.75rem}
.admin-link:hover{color:var(--tan)}

.hero{background:var(--dark);padding:4rem 2rem 3.5rem;text-align:center;position:relative;overflow:hidden}
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

.svc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem}
.svc-card{background:#fff;border:1px solid rgba(107,66,38,0.12);border-radius:12px;padding:1.375rem;text-decoration:none;color:inherit;display:block;position:relative;overflow:hidden;transition:transform 0.18s,box-shadow 0.18s}
.svc-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--accent);transform:scaleX(0);transition:transform 0.2s;transform-origin:left}
.svc-card:hover::after{transform:scaleX(1)}
.svc-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(107,66,38,0.11)}
.svc-icon{font-size:26px;margin-bottom:0.7rem;display:block}
.svc-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:0.35rem}
.svc-desc{font-size:12px;color:var(--muted);line-height:1.58;margin-bottom:0.875rem}
.svc-price{font-size:17px;font-weight:500;color:var(--accent)}
.svc-price span{font-size:11px;color:var(--muted);font-weight:400}

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
.step-dot{width:30px;height:30px;border-radius:50%;border:2px solid var(--light);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--muted);font-weight:500}
.step.done .step-dot{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 0 8px var(--accent),0 0 16px var(--accent),0 0 22px rgba(212,86,26,0.8)}
.step.active .step-dot{border-color:var(--accent);color:var(--accent);background:#fff;box-shadow:0 0 10px var(--accent),0 0 20px rgba(212,86,26,0.7);animation:pulseDot 1.2s infinite}
.step-lbl{font-size:10px;color:var(--muted);text-align:center;max-width:70px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
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
.calc-book-btn{margin-top:1.25rem;background:var(--accent);color:#fff;border:none;padding:12px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:block;text-align:center;transition:opacity 0.15s;width:100%}
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
</style>
</head>
<body>

<nav>
  <a href="solemate_homepage.php" class="logo">Sole<span>Mate</span></a>
  <div style="display:flex;align-items:center">
    <div class="nav-tabs">
      <?php
      $tabs = ['services'=>'Services','booking'=>'Book','tracker'=>'Track Order','calculator'=>'Quote','reviews'=>'Reviews','about'=>'About Us'];
      foreach ($tabs as $key => $label) {
          $active = ($page === $key) ? ' active' : '';
          echo "<a href=\"solemate_homepage.php?page={$key}\" class=\"nav-tab{$active}\">{$label}</a>";
      }
      ?>
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
</div>
<div class="stats-bar">
  <?php $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(); ?>
  <div><div class="stat-num"><?= number_format(max((int)$orderCount, 12847)) ?>+</div><div class="stat-lbl">Orders completed</div></div>
  <div><div class="stat-num">4.9 ★</div><div class="stat-lbl">Average rating</div></div>
  <div><div class="stat-num">48h</div><div class="stat-lbl">Avg turnaround</div></div>
  <div><div class="stat-num">15+</div><div class="stat-lbl">Years experience</div></div>
</div>
<?php endif; ?>

<div class="page-wrap">

<?php if ($page === 'services'): ?>
<div class="sec-label">What we offer</div>
<div class="sec-title">Expert care for every pair</div>
<div class="sec-sub">From sneakers to leather dress shoes — our certified cobblers handle it all. Click a service to book.</div>
<div class="svc-grid">
  <?php foreach ($services as $s): ?>
  <a href="solemate_homepage.php?page=booking&service_id=<?= $s['id'] ?>" class="svc-card">
    <span class="svc-icon"><?= $s['icon'] ?></span>
    <div class="svc-name"><?= e($s['service_name']) ?></div>
    <div class="svc-desc"><?= e($s['description']) ?></div>
    <div class="svc-price">R<?= number_format($s['price'], 0) ?> <span><?= $s['price_from'] ? 'from' : 'per pair' ?></span></div>
  </a>
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
    $isDropOff = ($o['collection'] === 'drop-off');
    $statusStep = $isDropOff
        ? ['Received'=>1,'Assessment'=>2,'In service'=>3,'Quality check'=>4,'Ready'=>5]
        : ['Pending'=>1,'Received'=>2,'Assessment'=>3,'In service'=>4,'Quality check'=>5,'Ready'=>6];
    $stepLabels = $isDropOff
        ? ['Received','Assessment','In service','QC Check','Ready']
        : ['Pending','Received','Assessment','In service','QC Check','Ready'];
    $totalSteps = count($stepLabels);
    $step = $statusStep[$o['status']] ?? 1;
    $pct  = round(($step - 1) / ($totalSteps - 1) * 100);
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

<?php endif; ?>

</div><!-- /page-wrap -->

<footer>
  <span class="footer-logo">SoleMate</span>
  123 Florida Road, Morningside, Durban &nbsp;·&nbsp; 031 555 0123 &nbsp;·&nbsp; hello@solemate.co.za<br>
  Mon–Fri 8am–6pm &nbsp;·&nbsp; Sat 9am–2pm<br>
  <span style="font-size:11px;color:rgba(255,255,255,0.28)">&copy; <?= date('Y') ?> SoleMate. All rights reserved.</span>
</footer>

</body>
</html>
