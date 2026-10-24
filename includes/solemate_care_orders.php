<?php
// ═══════════════════════════════════════════════════════
//  solemate_care_orders.php  —  Care-product shop helpers
//
//  Shared by: solemate_cart.php, solemate_care_checkout.php,
//  solemate_care_payment_init.php, the PayFast ITN handler and
//  completePaymentManually().
// ═══════════════════════════════════════════════════════

const CARE_DELIVERY_FEE = 80.00;          // flat courier fee (R)
const CARE_MAX_QTY      = 20;             // per product
const CARE_ORDER_STATUSES = ['Pending','Processing','Ready for collection','Out for delivery','Completed','Cancelled'];

function careTableExists(PDO $pdo, string $table): bool
{
    try {
        $st = $pdo->prepare("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(?) AND type='U'");
        $st->execute(['dbo.' . $table]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) { return false; }
}

/** Active products keyed by id. */
function careLoadProducts(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
    if (!$ids) return [];
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, icon, image_path FROM dbo.care_products WHERE is_active=1 AND id IN ($ph)");
    $stmt->execute($ids);
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[(int)$r['id']] = $r;
    return $out;
}

/**
 * Re-price a session cart ([product_id => qty]) from the database.
 * Returns ['items'=>[], 'count'=>int, 'subtotal'=>float, 'cart'=>cleanedCart]
 */
function careCartLines(PDO $pdo, array $cart): array
{
    $products = careLoadProducts($pdo, array_keys($cart));
    $items = []; $count = 0; $subtotal = 0.0; $clean = [];
    foreach ($cart as $pid => $q) {
        $pid = (int)$pid;
        if (!isset($products[$pid])) continue;               // hidden / deleted since
        $q     = max(1, min(CARE_MAX_QTY, (int)$q));
        $price = (float)$products[$pid]['price'];
        $line  = round($price * $q, 2);
        $items[] = [
            'id'    => $pid,
            'name'  => $products[$pid]['name'],
            'price' => $price,
            'qty'   => $q,
            'line'  => $line,
            'icon'  => $products[$pid]['icon'] ?: '🧴',
            'image' => $products[$pid]['image_path'] ?: '',
        ];
        $count += $q; $subtotal += $line; $clean[$pid] = $q;
    }
    return ['items' => $items, 'count' => $count, 'subtotal' => round($subtotal, 2), 'cart' => $clean];
}

function generateCareOrderRef(PDO $pdo): string
{
    do {
        $ref = 'CP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $chk = $pdo->prepare("SELECT 1 FROM dbo.care_orders WHERE order_ref=?");
        $chk->execute([$ref]);
    } while ($chk->fetchColumn());
    return $ref;
}

/**
 * Called whenever a payment is confirmed (PayFast ITN, sandbox poll, or
 * manual verification). Idempotent: only the first call for a paid
 * order does anything, so duplicate ITNs / polls never send two emails.
 */
function completeCareOrder(PDO $pdo, string $cartRef): bool
{
    if (!careTableExists($pdo, 'care_orders')) return false;

    $st = $pdo->prepare("SELECT * FROM dbo.care_orders WHERE cart_ref=?");
    $st->execute([$cartRef]);
    $order = $st->fetch();
    if (!$order || $order['payment_status'] === 'paid') return false;

    $up = $pdo->prepare("UPDATE dbo.care_orders SET payment_status=N'paid', status=N'Processing', paid_at=GETDATE() WHERE id=? AND payment_status<>N'paid'");
    $up->execute([(int)$order['id']]);
    if ($up->rowCount() === 0) return false;                  // another request got there first

    $order['status'] = 'Processing';
    $it = $pdo->prepare("SELECT name, unit_price, qty, line_total FROM dbo.care_order_items WHERE order_id=? ORDER BY id");
    $it->execute([(int)$order['id']]);
    try {
        sendCareOrderEmails($order, $it->fetchAll());
    } catch (\Throwable $e) {
        error_log('[SoleMate care order mail] ' . $e->getMessage());
    }
    return true;
}

// ── Emails ───────────────────────────────────────────────

function careEsc(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sendCareOrderEmails(array $order, array $items): void
{
    require_once __DIR__ . '/solemate_mailer.php';   // PHPMailer + smtpConfigure()

    $from     = getenv('MAIL_USERNAME') ?: '';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'SoleMate';
    $isDeliv  = ($order['delivery_method'] ?? '') === 'delivery';

    $rows = ''; $text = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #EDE4D8;font-size:14px;color:#1A0F08">'
              .  careEsc($it['name']) . ' <span style="color:#8B7355">× ' . (int)$it['qty'] . '</span></td>'
              .  '<td style="padding:8px 0;border-bottom:1px solid #EDE4D8;font-size:14px;text-align:right;color:#1A0F08">R'
              .  number_format((float)$it['line_total'], 2) . '</td></tr>';
        $text .= sprintf("  %s x%d  R%s\n", $it['name'], (int)$it['qty'], number_format((float)$it['line_total'], 2));
    }
    $fee   = (float)$order['delivery_fee'];
    $total = (float)$order['total'];

    $where = $isDeliv
        ? '<strong>Courier delivery to:</strong><br>' . nl2br(careEsc($order['delivery_address']))
        : '<strong>Collect in store:</strong><br>123 Florida Road, Morningside, Durban · Mon–Fri 8am–6pm · Sat 9am–2pm';
    $whereText = $isDeliv
        ? "Courier delivery to:\n" . $order['delivery_address']
        : "Collect in store: 123 Florida Road, Morningside, Durban (Mon-Fri 8am-6pm, Sat 9am-2pm)";

    // ── Customer email ───────────────────────────────────
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;background:#FAF7F2;padding:24px">'
      . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #EDE4D8">'
      . '<div style="background:#1A0F08;padding:22px 28px"><span style="font-family:Georgia,serif;font-size:24px;color:#C8A97E">Sole<span style="color:#D4561A">Mate</span></span></div>'
      . '<div style="padding:28px">'
      . '<h2 style="margin:0 0 6px;font-family:Georgia,serif;color:#1A0F08">Thanks for your order, ' . careEsc(explode(' ', $order['customer_name'])[0]) . '!</h2>'
      . '<p style="margin:0 0 18px;font-size:14px;color:#8B7355">Order <strong style="color:#D4561A">' . careEsc($order['order_ref']) . '</strong> — payment received.</p>'
      . '<table width="100%" cellpadding="0" cellspacing="0">' . $rows
      . '<tr><td style="padding:8px 0;font-size:13px;color:#8B7355">Subtotal</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#8B7355">R' . number_format((float)$order['subtotal'], 2) . '</td></tr>'
      . '<tr><td style="padding:2px 0 8px;font-size:13px;color:#8B7355">' . ($isDeliv ? 'Courier delivery' : 'Collection') . '</td><td style="padding:2px 0 8px;font-size:13px;text-align:right;color:#8B7355">' . ($fee > 0 ? 'R' . number_format($fee, 2) : 'Free') . '</td></tr>'
      . '<tr><td style="padding:10px 0;font-size:16px;font-weight:bold;color:#1A0F08">Total paid</td><td style="padding:10px 0;font-size:18px;font-weight:bold;text-align:right;color:#D4561A">R' . number_format($total, 2) . '</td></tr>'
      . '</table>'
      . '<div style="margin-top:18px;padding:14px 16px;background:#FAF7F2;border-left:3px solid #D4561A;border-radius:6px;font-size:13px;color:#1A0F08;line-height:1.6">' . $where . '</div>'
      . '<p style="margin:20px 0 0;font-size:12px;color:#8B7355">Questions? Call 031 555 0123 and quote your order number.</p>'
      . '</div></div></div>';

    $alt = "Thanks for your order, {$order['customer_name']}!\n"
         . "Order {$order['order_ref']} - payment received.\n\n" . $text
         . "\nSubtotal: R" . number_format((float)$order['subtotal'], 2)
         . "\n" . ($isDeliv ? 'Courier delivery' : 'Collection') . ': ' . ($fee > 0 ? 'R' . number_format($fee, 2) : 'Free')
         . "\nTotal paid: R" . number_format($total, 2) . "\n\n" . $whereText . "\n";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        smtpConfigure($mail);
        $mail->setFrom($from, $fromName);
        $mail->addAddress($order['email'], $order['customer_name']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Order confirmed — ' . $order['order_ref'] . ' · SoleMate';
        $mail->Body    = $html;
        $mail->AltBody = $alt;
        $mail->send();
    } catch (\Throwable $e) {
        error_log('[SoleMate care order customer mail] ' . $e->getMessage());
    }

    // ── Store notification ───────────────────────────────
    $adminTo = getenv('ADMIN_EMAIL') ?: $from;
    if ($adminTo) {
        $admin = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            smtpConfigure($admin);
            $admin->setFrom($from, $fromName);
            $admin->addAddress($adminTo);
            $admin->CharSet = 'UTF-8';
            $admin->Subject = 'New care order ' . $order['order_ref'] . ' — R' . number_format($total, 2);
            $admin->Body    = "New paid care order {$order['order_ref']}\n\n"
                . "Customer: {$order['customer_name']} <{$order['email']}> {$order['phone']}\n\n"
                . $text . "\nTotal: R" . number_format($total, 2) . "\n\n" . $whereText . "\n";
            $admin->send();
        } catch (\Throwable $e) {
            error_log('[SoleMate care order admin mail] ' . $e->getMessage());
        }
    }
}
