<?php
// ═══════════════════════════════════════════════════════
//  solemate_process_custom_order.php
//
//  Validates the custom shoe form, populates the cart
//  session, then redirects to solemate_checkout.php.
//  Booking + payment DB records are created by
//  solemate_payment_init.php after PayFast confirmation.
// ═══════════════════════════════════════════════════════
session_start();
require 'includes/solemate_database_connection.php';
require 'includes/solemate_ecommerce.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: solemate_homepage.php?page=customizer'); exit;
}

function xss(string $v): string { return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8'); }

// ── Collect inputs ───────────────────────────────────────
$name             = xss($_POST['customer_name']    ?? '');
$surname          = xss($_POST['surname']          ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$phone            = xss($_POST['phone']            ?? '');
$collection       = trim($_POST['collection']      ?? 'drop-off');
$delivery_address = xss($_POST['delivery_address'] ?? '');
$drop_date        = trim($_POST['drop_date']       ?? '');
$notes            = xss($_POST['notes']            ?? '');
$customization_raw= $_POST['customization_data']   ?? '';
$shoe_size        = xss($_POST['shoe_size']        ?? 'UK 6');
$size_system      = xss($_POST['size_system']      ?? 'mens');
$material         = xss($_POST['material']         ?? 'leather');
$lace_style       = xss($_POST['lace_style']       ?? 'flat');
$sole_option      = xss($_POST['sole_option']      ?? 'standard');

if (!in_array($collection, ['drop-off','courier','home'])) $collection = 'drop-off';

// ── Validate ─────────────────────────────────────────────
$errors = [];
if (!$name)                                       $errors[] = 'Your name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'A valid email address is required.';
if (in_array($collection, ['courier','home']) && !$delivery_address)
                                                  $errors[] = 'A delivery address is required.';
if (!$drop_date || strtotime($drop_date) < strtotime('+6 days'))
                                                  $errors[] = 'Choose a date at least 7 days from today (production time).';

$decoded            = [];
$customization_data = null;
if ($customization_raw) {
    $decoded = json_decode($customization_raw, true) ?? [];
    if ($decoded) $customization_data = json_encode($decoded);
}
if (!$customization_data) $errors[] = 'Design data is missing — please try again from the customizer.';

if (!empty($errors)) {
    header('Location: solemate_homepage.php?page=customizer&errors=' . urlencode(implode('|', $errors))); exit;
}

// ── Resolve service ──────────────────────────────────────
$serviceId    = null;
$serviceName  = 'Custom Shoes';
$servicePrice = 1200.00;
try {
    $s = $pdo->prepare("SELECT TOP 1 id,service_name,price FROM dbo.services WHERE service_key='custom-shoes'");
    $s->execute();
    $sRow = $s->fetch();
    if ($sRow) { $serviceId = (int)$sRow['id']; $serviceName = $sRow['service_name']; }
    // Base price from DB, but add-ons come from the JSON (computed in JS)
} catch (\PDOException $e) { /* use defaults */ }

// ── Build cart item ──────────────────────────────────────
// Use the JS-calculated total_price (includes all add-ons)
// Clamp: must be >= 1200 and <= 20000 to reject tampered values
$jsTotalPrice = isset($decoded['total_price']) ? (float)$decoded['total_price'] : 0.0;
if ($jsTotalPrice >= 1200 && $jsTotalPrice <= 20000) {
    $servicePrice = round($jsTotalPrice, 2);
}
// price_breakdown for checkout display
$priceBreakdown = $decoded['price_breakdown'] ?? [['label' => 'Custom shoe (1 pair)', 'cost' => $servicePrice]];

$sizeLabel     = $decoded['size'] ?? $shoe_size;
$shoe_type     = "Custom — {$sizeLabel} (" . ucfirst($size_system) . ") · " . ucfirst($material);
$collectionFee = in_array($collection, ['courier','home']) ? 85.0 : 0.0;
$fullNotes     = $delivery_address
    ? "Delivery address: {$delivery_address}" . ($notes ? "\n{$notes}" : '')
    : $notes;

$cart = [
    'item' => [
        'is_custom'          => true,
        'service_id'         => $serviceId,
        'service_name'       => $serviceName,
        'service_key'        => 'custom-shoes',
        'price'              => $servicePrice,
        'collection_fee'     => $collectionFee,  // R85 courier/home, 0 drop-off
        'shoe_type'          => $shoe_type,
        'drop_date'          => $drop_date,
        'collection'         => $collection,
        'notes'              => $fullNotes,
        'pairs'              => 1,
        'surname'            => $surname,
        'customer_name'      => $name,
        'email'              => $email,
        'phone'              => $phone,
        'customization_data' => $customization_data,
        'shoe_size'          => $shoe_size,
        'size_system'        => $size_system,
        'material'           => $material,
        'lace_style'         => $lace_style,
        'sole_option'        => $sole_option,
        'delivery_address'   => $delivery_address,
        'colors'             => $decoded['colors'] ?? [],
        'price_breakdown'    => $priceBreakdown,
    ],
    'coupon_code' => null, 'coupon_discount'    => 0,
    'gift_card_code' => null, 'gift_card_discount' => 0,
    'loyalty_points' => 0,    'loyalty_discount'   => 0,
];

cartSet($cart);
header('Location: solemate_checkout.php');
exit;