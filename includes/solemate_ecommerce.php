<?php
// ═══════════════════════════════════════════════════════
//  solemate_ecommerce.php  —  Coupon · Gift Card · Loyalty
// ═══════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────────
//  SITE SETTINGS  (feature toggles)
// ─────────────────────────────────────────────────────────

/**
 * Returns ['gift_cards'=>bool,'coupons'=>bool,'loyalty'=>bool,'referrals'=>bool].
 * All default to true (enabled) if dbo.site_settings doesn't exist yet
 * or a key hasn't been set.
 */
function getSiteSettings(PDO $pdo): array {
    $defaults = ['gift_cards' => true, 'coupons' => true, 'loyalty' => true, 'referrals' => true];
    try {
        $exists = (bool)$pdo->query("SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.site_settings') AND type='U'")->fetchColumn();
        if (!$exists) return $defaults;
        $rows = $pdo->query("SELECT setting_key, setting_value FROM dbo.site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($defaults as $key => $val) {
            if (isset($rows[$key])) $defaults[$key] = ($rows[$key] === '1');
        }
    } catch (PDOException $e) {}
    return $defaults;
}

// ─────────────────────────────────────────────────────────
//  COUPON HELPERS
// ─────────────────────────────────────────────────────────

/**
 * Validate a coupon code against the DB.
 * Returns ['ok'=>bool, 'discount'=>float, 'message'=>string, 'coupon'=>row|null]
 */
function validateCoupon(PDO $pdo, string $code, float $subtotal): array {
    $code = strtoupper(trim($code));

    if (!$code) return ['ok'=>false,'discount'=>0,'message'=>'Enter a coupon code.','coupon'=>null];

    $stmt = $pdo->prepare("SELECT * FROM dbo.coupons WHERE code=? AND is_active=1");
    $stmt->execute([$code]);
    $c = $stmt->fetch();

    if (!$c) return ['ok'=>false,'discount'=>0,'message'=>'Coupon not found or inactive.','coupon'=>null];

    $today = date('Y-m-d');
    if ($c['valid_from'] && $today < $c['valid_from'])
        return ['ok'=>false,'discount'=>0,'message'=>'Coupon is not valid yet.','coupon'=>null];
    if ($c['valid_until'] && $today > $c['valid_until'])
        return ['ok'=>false,'discount'=>0,'message'=>'Coupon has expired.','coupon'=>null];
    if ($c['max_uses'] > 0 && $c['uses_count'] >= $c['max_uses'])
        return ['ok'=>false,'discount'=>0,'message'=>'Coupon has reached its usage limit.','coupon'=>null];
    if ($subtotal < (float)$c['min_order'])
        return ['ok'=>false,'discount'=>0,'message'=>'Minimum order of R'.number_format($c['min_order'],0).' required.','coupon'=>null];

    $discount = $c['type'] === 'percent'
        ? round($subtotal * ((float)$c['value'] / 100), 2)
        : min((float)$c['value'], $subtotal);

    return ['ok'=>true,'discount'=>$discount,'message'=>'Coupon applied — R'.number_format($discount,2).' off!','coupon'=>$c];
}

/** Increment uses_count when an order is paid. */
function redeemCoupon(PDO $pdo, string $code): void {
    $pdo->prepare("UPDATE dbo.coupons SET uses_count=uses_count+1 WHERE code=?")->execute([strtoupper($code)]);
}

// ─────────────────────────────────────────────────────────
//  GIFT CARD HELPERS
// ─────────────────────────────────────────────────────────

/**
 * Validate a gift card code.
 * Returns ['ok'=>bool, 'balance'=>float, 'message'=>string, 'card'=>row|null]
 */
function validateGiftCard(PDO $pdo, string $code): array {
    $code = strtoupper(trim($code));
    if (!$code) return ['ok'=>false,'balance'=>0,'message'=>'Enter a gift card code.','card'=>null];

    $stmt = $pdo->prepare("SELECT * FROM dbo.gift_cards WHERE code=? AND is_active=1");
    $stmt->execute([$code]);
    $card = $stmt->fetch();

    if (!$card) return ['ok'=>false,'balance'=>0,'message'=>'Gift card not found or already used.','card'=>null];
    if ($card['expires_at'] && date('Y-m-d') > $card['expires_at'])
        return ['ok'=>false,'balance'=>0,'message'=>'Gift card has expired.','card'=>null];
    if ((float)$card['balance'] <= 0)
        return ['ok'=>false,'balance'=>0,'message'=>'Gift card has no remaining balance.','card'=>null];

    return ['ok'=>true,'balance'=>(float)$card['balance'],'message'=>'Gift card valid — R'.number_format($card['balance'],2).' available.','card'=>$card];
}

/**
 * Deduct amount from gift card and log transaction.
 * Returns actual amount deducted (may be < requested if balance is lower).
 */
function redeemGiftCard(PDO $pdo, string $code, float $amount, string $cartRef): float {
    $stmt = $pdo->prepare("SELECT id, balance FROM dbo.gift_cards WHERE code=? AND is_active=1");
    $stmt->execute([$code]);
    $card = $stmt->fetch();
    if (!$card) return 0;

    $deduct    = min((float)$card['balance'], $amount);
    $newBal    = (float)$card['balance'] - $deduct;
    $pdo->prepare("UPDATE dbo.gift_cards SET balance=? WHERE id=?")->execute([$newBal,$card['id']]);
    $pdo->prepare("INSERT INTO dbo.gift_card_transactions (gift_card_id,cart_ref,amount,balance_after) VALUES (?,?,?,?)")
        ->execute([$card['id'],$cartRef,$deduct,$newBal]);
    if ($newBal <= 0)
        $pdo->prepare("UPDATE dbo.gift_cards SET is_active=0 WHERE id=?")->execute([$card['id']]);
    return $deduct;
}

/** Generate a unique gift card code: GC-XXXX-XXXX */
function generateGiftCardCode(PDO $pdo): string {
    do {
        $code = 'GC-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $chk  = $pdo->prepare("SELECT id FROM dbo.gift_cards WHERE code=?");
        $chk->execute([$code]);
    } while ($chk->fetch());
    return $code;
}

// ─────────────────────────────────────────────────────────
//  LOYALTY POINTS HELPERS
//
//  Earn: 1 point per R1 spent (rounded down)
//  Redeem: 100 points = R10 off (R0.10 per point)
//  Max redeem per order: 50% of subtotal
// ─────────────────────────────────────────────────────────

const LOYALTY_EARN_PER_RAND     = 1;     // points per R1 spent
const LOYALTY_REDEEM_PER_POINT  = 0.10;  // R per point
const LOYALTY_MIN_REDEEM        = 100;   // minimum points to redeem
const LOYALTY_MAX_REDEEM_PCT    = 0.50;  // max 50% of order via points

/** How many points does a customer earn on a given amount? */
function loyaltyPointsEarned(float $amountPaid): int {
    return (int)floor($amountPaid * LOYALTY_EARN_PER_RAND);
}

/** Rand value of a given points quantity. */
function loyaltyPointsValue(int $points): float {
    return round($points * LOYALTY_REDEEM_PER_POINT, 2);
}

/**
 * Maximum points a customer can redeem on this order.
 * Capped at 50% of subtotal.
 */
function loyaltyMaxRedeem(int $customerPoints, float $subtotal): int {
    if ($customerPoints < LOYALTY_MIN_REDEEM) return 0;
    $maxByBalance = $customerPoints;
    $maxByOrder   = (int)floor(($subtotal * LOYALTY_MAX_REDEEM_PCT) / LOYALTY_REDEEM_PER_POINT);
    return min($maxByBalance, $maxByOrder);
}

/** Award points to a customer and log transaction. */
function awardLoyaltyPoints(PDO $pdo, int $customerId, int $points, string $cartRef, string $desc = 'Order payment'): void {
    if ($points <= 0 || $customerId <= 0) return;
    $pdo->prepare("UPDATE dbo.customers SET loyalty_points=loyalty_points+? WHERE id=?")->execute([$points,$customerId]);
    $pdo->prepare("INSERT INTO dbo.loyalty_transactions (customer_id,cart_ref,points,type,description) VALUES (?,?,?,N'earned',?)")
        ->execute([$customerId,$cartRef,$points,$desc]);
}

/** Deduct redeemed points and log transaction. */
function redeemLoyaltyPoints(PDO $pdo, int $customerId, int $points, string $cartRef): void {
    if ($points <= 0 || $customerId <= 0) return;
    $pdo->prepare("UPDATE dbo.customers SET loyalty_points=CASE WHEN loyalty_points-? < 0 THEN 0 ELSE loyalty_points-? END WHERE id=?")
        ->execute([$points,$points,$customerId]);
    $pdo->prepare("INSERT INTO dbo.loyalty_transactions (customer_id,cart_ref,points,type,description) VALUES (?,?,?,N'redeemed',N'Redeemed at checkout')")
        ->execute([$customerId,$cartRef,-$points]);
}

/** Admin manual adjustment (add or subtract). */
function adjustLoyaltyPoints(PDO $pdo, int $customerId, int $delta, string $reason): void {
    $pdo->prepare("UPDATE dbo.customers SET loyalty_points=CASE WHEN loyalty_points+? < 0 THEN 0 ELSE loyalty_points+? END WHERE id=?")
        ->execute([$delta,$delta,$customerId]);
    $type = $delta >= 0 ? 'adjusted' : 'adjusted';
    $pdo->prepare("INSERT INTO dbo.loyalty_transactions (customer_id,points,type,description) VALUES (?,?,N'adjusted',?)")
        ->execute([$customerId,$delta,$reason]);
}

// ─────────────────────────────────────────────────────────
//  CART SESSION HELPERS
// ─────────────────────────────────────────────────────────

function cartGet(): array {
    if (session_status()===PHP_SESSION_NONE) session_start();
    return $_SESSION['sm_cart'] ?? [
        'item'           => null,
        'coupon_code'    => null,  'coupon_discount'    => 0,
        'gift_card_code' => null,  'gift_card_discount' => 0,
        'loyalty_points' => 0,     'loyalty_discount'   => 0,
    ];
}

function cartSet(array $cart): void {
    if (session_status()===PHP_SESSION_NONE) session_start();
    $_SESSION['sm_cart'] = $cart;
}

function cartClear(): void {
    if (session_status()===PHP_SESSION_NONE) session_start();
    unset($_SESSION['sm_cart']);
}

function cartTotal(array $cart): float {
    $sub = (float)($cart['item']['price'] ?? 0);
    // Custom shoes store their own fee (R85); standard uses match (R80/R120)
    $collectionFee = isset($cart['item']['collection_fee'])
        ? (float)$cart['item']['collection_fee']
        : match($cart['item']['collection'] ?? 'drop-off') {
            'courier' => 80.0, 'home' => 120.0, default => 0.0
        };
    $total = $sub + $collectionFee
        - (float)($cart['coupon_discount']    ?? 0)
        - (float)($cart['gift_card_discount'] ?? 0)
        - (float)($cart['loyalty_discount']   ?? 0);
    return max(0, round($total, 2));
}

// ─────────────────────────────────────────────────────────
//  LOYALTY TIERS
//
//  Bronze:   0 – 499 pts      🥉  standard earn rate
//  Silver:   500 – 1,499 pts  🥈  1.25× earn rate
//  Gold:     1,500 – 3,999 pts 🥇  1.5× earn rate
//  Platinum: 4,000+ pts       💎  2× earn rate
// ─────────────────────────────────────────────────────────

const LOYALTY_TIERS = [
    'Bronze'   => ['min'=>0,    'max'=>499,   'icon'=>'🥉', 'color'=>'#cd7f32', 'bg'=>'#fdf3e7', 'multiplier'=>1.00, 'next'=>'Silver',   'next_at'=>500],
    'Silver'   => ['min'=>500,  'max'=>1499,  'icon'=>'🥈', 'color'=>'#9e9e9e', 'bg'=>'#f5f5f5', 'multiplier'=>1.25, 'next'=>'Gold',     'next_at'=>1500],
    'Gold'     => ['min'=>1500, 'max'=>3999,  'icon'=>'🥇', 'color'=>'#d4a012', 'bg'=>'#fffbea', 'multiplier'=>1.50, 'next'=>'Platinum', 'next_at'=>4000],
    'Platinum' => ['min'=>4000, 'max'=>PHP_INT_MAX, 'icon'=>'💎', 'color'=>'#5c35a0', 'bg'=>'#f3f0fb', 'multiplier'=>2.00, 'next'=>null, 'next_at'=>null],
];

/**
 * Return the loyalty tier data for a given points balance.
 * Adds 'progress_pct' and 'points_to_next' for progress bar display.
 */
function getLoyaltyTier(int $points): array {
    foreach (LOYALTY_TIERS as $name => $tier) {
        if ($points >= $tier['min'] && $points <= $tier['max']) {
            $tier['name'] = $name;
            if ($tier['next_at'] !== null) {
                $range               = $tier['max'] - $tier['min'] + 1;
                $progress            = $points - $tier['min'];
                $tier['progress_pct']   = min(100, (int)round($progress / $range * 100));
                $tier['points_to_next'] = $tier['next_at'] - $points;
            } else {
                $tier['progress_pct']   = 100;
                $tier['points_to_next'] = 0;
            }
            return $tier;
        }
    }
    // Fallback — Bronze
    $t = LOYALTY_TIERS['Bronze'];
    $t['name'] = 'Bronze'; $t['progress_pct'] = 0; $t['points_to_next'] = 500;
    return $t;
}

/**
 * How many points does a customer earn on an amount, factoring in their tier multiplier?
 */
function loyaltyPointsEarnedWithTier(float $amountPaid, int $currentPoints): int {
    $tier       = getLoyaltyTier($currentPoints);
    $multiplier = $tier['multiplier'];
    return (int)floor($amountPaid * LOYALTY_EARN_PER_RAND * $multiplier);
}

// ─────────────────────────────────────────────────────────
//  REFERRAL SYSTEM
//
//  • Each customer gets a unique referral code on registration.
//  • When a referred friend completes their first paid booking,
//    the referral is marked 'completed'.
//  • Every 3 completed + unrewarded referrals = R100 credit
//    (awarded as 1,000 loyalty points = R100 at R0.10/pt).
// ─────────────────────────────────────────────────────────

const REFERRAL_REWARD_THRESHOLD = 3;      // friends needed per reward
const REFERRAL_REWARD_POINTS    = 1000;   // pts per batch (= R100)

/** Generate a unique referral code like REF-AB12CD */
function generateReferralCode(PDO $pdo): string {
    do {
        $code = 'REF-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $chk  = $pdo->prepare("SELECT 1 FROM dbo.customers WHERE referral_code=?");
        $chk->execute([$code]);
    } while ($chk->fetchColumn());
    return $code;
}

/**
 * Record a new referral when a referred user registers.
 * $refereeId = the new customer's id
 * $referralCode = the code they entered (from referrer)
 */
function recordReferral(PDO $pdo, int $refereeId, string $referralCode): void {
    $ref = $pdo->prepare("SELECT id FROM dbo.customers WHERE referral_code=?");
    $ref->execute([$referralCode]);
    $referrer = $ref->fetch();
    if (!$referrer) return;
    $referrerId = (int)$referrer['id'];
    if ($referrerId === $refereeId) return; // can't refer yourself

    // Check referrals table exists before inserting
    $exists = $pdo->query(
        "SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.referrals') AND type='U'"
    )->fetchColumn();
    if (!$exists) return;

    // Only one referral per person
    $dup = $pdo->prepare("SELECT 1 FROM dbo.referrals WHERE referee_id=?");
    $dup->execute([$refereeId]);
    if ($dup->fetchColumn()) return;

    $pdo->prepare("
        INSERT INTO dbo.referrals (referrer_id, referee_id, status, rewarded)
        VALUES (?, ?, N'pending', 0)
    ")->execute([$referrerId, $refereeId]);

    // Save referred_by_code on the customer row too
    $pdo->prepare("UPDATE dbo.customers SET referred_by_code=? WHERE id=?")
        ->execute([$referralCode, $refereeId]);
}

/**
 * Called after a referee's first paid booking completes.
 * Marks their referral 'completed' and checks if the referrer
 * has hit the 3-friend threshold to earn R100.
 */
function completeReferral(PDO $pdo, int $refereeId): void {
    $exists = $pdo->query(
        "SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.referrals') AND type='U'"
    )->fetchColumn();
    if (!$exists) return;

    // Only complete once
    $row = $pdo->prepare("SELECT id, referrer_id, status FROM dbo.referrals WHERE referee_id=?");
    $row->execute([$refereeId]);
    $ref = $row->fetch();
    if (!$ref || $ref['status'] === 'completed') return;

    // Mark completed
    $pdo->prepare("
        UPDATE dbo.referrals SET status=N'completed', completed_at=GETDATE() WHERE id=?
    ")->execute([$ref['id']]);

    $referrerId = (int)$ref['referrer_id'];

    // Count unrewarded completed referrals for this referrer
    $cnt = $pdo->prepare("
        SELECT COUNT(*) FROM dbo.referrals
        WHERE referrer_id=? AND status=N'completed' AND rewarded=0
    ");
    $cnt->execute([$referrerId]);
    $completedCount = (int)$cnt->fetchColumn();

    // Award R100 per batch of 3
    $batches = (int)floor($completedCount / REFERRAL_REWARD_THRESHOLD);
    if ($batches >= 1) {
        $toMark = $batches * REFERRAL_REWARD_THRESHOLD;

        // Mark oldest N referrals as rewarded
        $ids = $pdo->prepare("
            SELECT TOP (?) id FROM dbo.referrals
            WHERE referrer_id=? AND status=N'completed' AND rewarded=0
            ORDER BY completed_at ASC
        ");
        $ids->execute([$toMark, $referrerId]);
        foreach ($ids->fetchAll() as $r) {
            $pdo->prepare("UPDATE dbo.referrals SET rewarded=1 WHERE id=?")
                ->execute([$r['id']]);
        }

        // Award points (1000 pts = R100 per batch)
        $pts = $batches * REFERRAL_REWARD_POINTS;
        $pdo->prepare("UPDATE dbo.customers SET loyalty_points=loyalty_points+? WHERE id=?")
            ->execute([$pts, $referrerId]);
        $pdo->prepare("
            INSERT INTO dbo.loyalty_transactions (customer_id,points,type,description)
            VALUES (?,?,N'earned',?)
        ")->execute([$referrerId, $pts, "Referral reward — {$batches}× R100 (3 friends)"]);
    }
}

/**
 * Return referral stats for display in a customer's profile.
 */
function getReferralStats(PDO $pdo, int $customerId): array {
    $exists = $pdo->query(
        "SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.referrals') AND type='U'"
    )->fetchColumn();
    if (!$exists) return ['total'=>0,'completed'=>0,'pending'=>0,'next_reward_at'=>3,'code'=>''];

    $codeRow = $pdo->prepare("SELECT referral_code FROM dbo.customers WHERE id=?");
    $codeRow->execute([$customerId]);
    $code = $codeRow->fetchColumn() ?: '';

    $stats = $pdo->prepare("
        SELECT
            COUNT(*)                                          AS total,
            SUM(CASE WHEN status=N'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status=N'pending'   THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status=N'completed' AND rewarded=0 THEN 1 ELSE 0 END) AS unrewarded_completed
        FROM dbo.referrals WHERE referrer_id=?
    ");
    $stats->execute([$customerId]);
    $row = $stats->fetch();

    $unrewarded = (int)($row['unrewarded_completed'] ?? 0);
    $nextAt     = REFERRAL_REWARD_THRESHOLD - ($unrewarded % REFERRAL_REWARD_THRESHOLD);
    if ($nextAt === REFERRAL_REWARD_THRESHOLD && $unrewarded > 0) $nextAt = 0;

    return [
        'code'           => $code,
        'total'          => (int)($row['total']     ?? 0),
        'completed'      => (int)($row['completed'] ?? 0),
        'pending'        => (int)($row['pending']   ?? 0),
        'unrewarded'     => $unrewarded,
        'next_reward_at' => $nextAt,
    ];
}