<?php
// ─── SoleMate Data Store ───────────────────────────────────────────────────
// In a real app this would be a MySQL/PostgreSQL database.
// For this demo, data lives in PHP arrays and $_SESSION.

session_start();

// ── Services catalogue ─────────────────────────────────────────────────────
define('SERVICES', [
    'deep-clean'          => ['name' => 'Deep Clean',          'icon' => '🧽', 'price' => 350, 'unit' => 'per pair', 'from' => false,
        'desc' => 'Full interior and exterior clean, deodorise, and condition. Restores freshness to any shoe.'],
    'sole-repair'         => ['name' => 'Sole Repair',         'icon' => '👟', 'price' => 450, 'unit' => 'from',     'from' => true,
        'desc' => 'Resole, re-glue, or replace worn soles. Extends shoe life by years.'],
    'leather-restoration' => ['name' => 'Leather Restoration', 'icon' => '✨', 'price' => 600, 'unit' => 'from',     'from' => true,
        'desc' => 'Repair scuffs, restore colour, deep condition. Premium leather treatment.'],
    'sneaker-whitening'   => ['name' => 'Sneaker Whitening',   'icon' => '⚪', 'price' => 280, 'unit' => 'per pair', 'from' => false,
        'desc' => 'Restore white midsoles and uppers to factory-fresh brightness.'],
    'heel-replacement'    => ['name' => 'Heel Replacement',    'icon' => '👠', 'price' => 380, 'unit' => 'per pair', 'from' => false,
        'desc' => 'Replace worn-down heels on pumps, boots, and dress shoes.'],
    'full-restoration'    => ['name' => 'Full Restoration',    'icon' => '🔧', 'price' => 950, 'unit' => 'from',     'from' => true,
        'desc' => 'Complete overhaul: clean, repair, restore colour, replace sole. Like new.'],
]);

// ── Order step labels ───────────────────────────────────────────────────────
define('ORDER_STEPS', ['Received', 'Assessment', 'In service', 'Quality check', 'Ready']);

// ── Seed orders (simulate DB rows) ─────────────────────────────────────────
$SEED_ORDERS = [
    'SM-20240421' => ['step'=>3,'service'=>'Deep Clean',          'shoe'=>'Nike Air Max 90',   'date'=>'18 Apr 2026','eta'=>'21 Apr 2026',          'name'=>'Siya K.',   'email'=>'siya@example.com'],
    'SM-20240388' => ['step'=>5,'service'=>'Full Restoration',    'shoe'=>'Timberland Boots',  'date'=>'12 Apr 2026','eta'=>'Ready for collection', 'name'=>'Marco G.',  'email'=>'marco@example.com'],
    'SM-20240372' => ['step'=>2,'service'=>'Leather Restoration', 'shoe'=>'Oxford Brogues',    'date'=>'10 Apr 2026','eta'=>'23 Apr 2026',          'name'=>'Nomsa M.',  'email'=>'nomsa@example.com'],
    'SM-20240301' => ['step'=>4,'service'=>'Sneaker Whitening',   'shoe'=>'Adidas Stan Smith', 'date'=>'05 Apr 2026','eta'=>'22 Apr 2026',          'name'=>'Aryan P.',  'email'=>'aryan@example.com'],
    'SM-20240255' => ['step'=>1,'service'=>'Heel Replacement',    'shoe'=>'Black Pumps',       'date'=>'20 Apr 2026','eta'=>'24 Apr 2026',          'name'=>'Thabo N.',  'email'=>'thabo@example.com'],
];

// Merge seed orders with session-created bookings
if (!isset($_SESSION['orders'])) {
    $_SESSION['orders'] = [];
}
$ALL_ORDERS = array_merge($SEED_ORDERS, $_SESSION['orders']);

// ── Reviews ────────────────────────────────────────────────────────────────
define('REVIEWS', [
    ['initials'=>'SK','name'=>'Siya Khoza',      'loc'=>'Berea, Durban',       'stars'=>5,'text'=>'My Air Force 1s look better than the day I bought them. Genuinely blown away by the results.'],
    ['initials'=>'NM','name'=>'Nomsa Mthembu',   'loc'=>'Umhlanga, Durban',    'stars'=>5,'text'=>'Italian leather oxfords with deep scuffs — the restoration was absolutely remarkable.'],
    ['initials'=>'AP','name'=>'Aryan Patel',      'loc'=>'Westville, Durban',   'stars'=>5,'text'=>'Three pairs via courier. Smooth tracking, SMS updates, incredible results. Will use again.'],
    ['initials'=>'MG','name'=>'Marco Govender',  'loc'=>'Chatsworth, Durban',  'stars'=>5,'text'=>'Timberlands fully restored — new soles, cleaned, waterproofed. Looked brand new!'],
    ['initials'=>'TN','name'=>'Thandeka Nxumalo','loc'=>'Pinetown, Durban',    'stars'=>5,'text'=>'Brought in a pair of heels I thought were ruined. They came back perfect. Incredible craftsmanship.'],
    ['initials'=>'RS','name'=>'Rajan Sharma',     'loc'=>'Phoenix, Durban',     'stars'=>5,'text'=>'Quick turnaround, kept me updated via SMS. My suede loafers look immaculate. Highly recommend.'],
]);

// ── Helper functions ────────────────────────────────────────────────────────

function generateOrderId(): string {
    $date = date('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return "SM-{$date}-{$rand}";
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function serviceOptions(string $selected = ''): string {
    $html = '<option value="">— Select a service —</option>';
    foreach (SERVICES as $key => $svc) {
        $label   = $svc['name'] . ' — R' . number_format($svc['price']);
        $sel     = ($selected === $key) ? ' selected' : '';
        $html   .= "<option value=\"{$key}\"{$sel}>{$label}</option>";
    }
    return $html;
}