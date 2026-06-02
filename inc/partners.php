<?php
/**
 * NESTORA.my - Partnership program (hybrid referral + reseller).
 *
 * Tier defaults come from site_settings; a partner row stores its own
 * commission and wholesale rates (the admin can override per partner).
 * A visitor arriving with ?ref=CODE has the code stored in the session
 * (last-click wins); at order time, if the code resolves to an active
 * partner, the order is linked and a pending commission is recorded.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function partner_program_enabled(): bool
{
    return get_setting('partner_program_enabled', '0') === '1';
}

function partner_tier_defaults(string $tier): array
{
    if ($tier === 'elite') {
        return [
            'commission' => (float) get_setting('partner_elite_commission', '20'),
            'wholesale'  => (float) get_setting('partner_elite_wholesale',  '30'),
        ];
    }
    return [
        'commission' => (float) get_setting('partner_starter_commission', '10'),
        'wholesale'  => (float) get_setting('partner_starter_wholesale',  '20'),
    ];
}

/** A short, human-friendly, unique referral code (no 0/O/1/I/L). */
function generate_referral_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $stmt = $pdo->prepare('SELECT 1 FROM partners WHERE referral_code = :c LIMIT 1');
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $stmt->execute([':c' => $code]);
        if (!$stmt->fetchColumn()) {
            return $code;
        }
    }
    // Fallback (vanishingly unlikely): timestamp-based.
    return 'P' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function partner_find_by_referral_code(PDO $pdo, string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM partners WHERE referral_code = :c AND status = 'active' LIMIT 1"
    );
    $stmt->execute([':c' => $code]);
    return $stmt->fetch() ?: null;
}

function partner_set_session_ref(string $code): void
{
    $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    if ($code !== '') {
        $_SESSION['referral_code'] = $code;
    }
}

function partner_active_referral(PDO $pdo): ?array
{
    $code = (string) ($_SESSION['referral_code'] ?? '');
    if ($code === '') {
        return null;
    }
    $partner = partner_find_by_referral_code($pdo, $code);
    return $partner ? ['code' => (string) $partner['referral_code'], 'partner' => $partner] : null;
}

function partner_clear_ref(): void
{
    unset($_SESSION['referral_code']);
}

/**
 * Attach the active session referral to a freshly placed order and
 * create a pending commission. Idempotent (unique key on order_id).
 * No-op for wholesale orders.
 */
function partner_attach_to_order(PDO $pdo, int $orderId): void
{
    $oStmt = $pdo->prepare('SELECT total_amount, is_wholesale, partner_id FROM orders WHERE id = :id');
    $oStmt->execute([':id' => $orderId]);
    $order = $oStmt->fetch();
    if (!$order || (int) $order['is_wholesale'] === 1 || !empty($order['partner_id'])) {
        return;
    }

    $ref = partner_active_referral($pdo);
    if (!$ref) {
        return;
    }
    $partner = $ref['partner'];
    $base    = (float) $order['total_amount'];
    $rate    = (float) $partner['commission_rate'];
    $amount  = round($base * $rate / 100, 2);

    $pdo->prepare(
        'UPDATE orders SET partner_id = :pid, referral_code = :code WHERE id = :id'
    )->execute([':pid' => (int) $partner['id'], ':code' => $partner['referral_code'], ':id' => $orderId]);

    try {
        $pdo->prepare(
            "INSERT INTO partner_commissions
             (partner_id, order_id, base_amount, rate, commission_amount, status)
             VALUES (:pid,:oid,:base,:rate,:amt,'pending')"
        )->execute([
            ':pid' => (int) $partner['id'], ':oid' => $orderId,
            ':base' => $base, ':rate' => $rate, ':amt' => $amount,
        ]);
    } catch (Throwable $e) {
        // Unique key on order_id makes a duplicate attach a no-op.
    }
}

/** When an order becomes paid, move its pending commission to approved. */
function partner_promote_commissions_for_order(PDO $pdo, int $orderId): void
{
    $pdo->prepare(
        "UPDATE partner_commissions SET status = 'approved'
         WHERE order_id = :id AND status = 'pending'"
    )->execute([':id' => $orderId]);
}

/** Void commissions when the order is cancelled or refunded. */
function partner_void_commissions_for_order(PDO $pdo, int $orderId): void
{
    $pdo->prepare(
        "UPDATE partner_commissions SET status = 'void'
         WHERE order_id = :id AND status IN ('pending','approved')"
    )->execute([':id' => $orderId]);
}

/** Balance due to a partner = approved commissions minus recorded payouts. */
function partner_balance(PDO $pdo, int $partnerId): float
{
    $earned = (float) $pdo->query(
        "SELECT COALESCE(SUM(commission_amount),0) FROM partner_commissions
         WHERE partner_id = " . (int) $partnerId . " AND status = 'approved'"
    )->fetchColumn();
    $paid = (float) $pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM partner_payouts
         WHERE partner_id = " . (int) $partnerId
    )->fetchColumn();
    return round($earned - $paid, 2);
}

/** Wholesale unit price for a product at a partner's discount rate. */
function partner_wholesale_price(array $product, float $rate): float
{
    $price = effective_price($product);
    return round($price * (1 - max(0.0, min(90.0, $rate)) / 100), 2);
}
