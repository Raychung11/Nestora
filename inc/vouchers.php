<?php
/**
 * NESTORA.my - Discount / voucher codes.
 *
 * A code applied in the cart is held in the session and re-validated
 * against the live subtotal on every page (so it drops automatically if
 * the cart no longer qualifies). It is persisted on the order and its
 * usage counter incremented at checkout.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function voucher_normalize(string $code): string
{
    return strtoupper(trim($code));
}

function voucher_find(string $code): ?array
{
    $code = voucher_normalize($code);
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM vouchers WHERE code = :c LIMIT 1');
    $stmt->execute([':c' => $code]);
    return $stmt->fetch() ?: null;
}

/**
 * @return array{ok:bool,message:string,discount:float}
 */
function voucher_validate(?array $v, float $subtotal): array
{
    if (!$v) {
        return ['ok' => false, 'message' => 'That code is not valid.', 'discount' => 0.0];
    }
    if ($v['status'] !== 'active') {
        return ['ok' => false, 'message' => 'That code is no longer active.', 'discount' => 0.0];
    }
    $today = date('Y-m-d');
    if (!empty($v['starts_at']) && $today < $v['starts_at']) {
        return ['ok' => false, 'message' => 'That code is not active yet.', 'discount' => 0.0];
    }
    if (!empty($v['expires_at']) && $today > $v['expires_at']) {
        return ['ok' => false, 'message' => 'That code has expired.', 'discount' => 0.0];
    }
    if ((int) $v['max_uses'] > 0 && (int) $v['used_count'] >= (int) $v['max_uses']) {
        return ['ok' => false, 'message' => 'That code has reached its usage limit.', 'discount' => 0.0];
    }
    if ((float) $v['min_spend'] > 0 && $subtotal < (float) $v['min_spend']) {
        return ['ok' => false,
                'message' => 'Spend at least ' . money((float) $v['min_spend']) . ' to use this code.',
                'discount' => 0.0];
    }

    $discount = $v['type'] === 'percent'
        ? $subtotal * ((float) $v['value'] / 100)
        : (float) $v['value'];
    $discount = round(min($discount, $subtotal), 2); // never exceed the subtotal

    return ['ok' => true, 'message' => 'Code applied.', 'discount' => $discount];
}

/** Apply a code to the session if valid for the current subtotal. */
function voucher_apply(string $code, float $subtotal): array
{
    $v   = voucher_find($code);
    $res = voucher_validate($v, $subtotal);
    if ($res['ok'] && $v) {
        $_SESSION['voucher'] = voucher_normalize($code);
    }
    return $res;
}

function voucher_clear(): void
{
    unset($_SESSION['voucher']);
}

/**
 * The currently applied, still-valid voucher for a given subtotal.
 * Returns null (and clears the session) if it no longer qualifies.
 *
 * @return array{code:string,discount:float,voucher:array}|null
 */
function voucher_current(float $subtotal): ?array
{
    $code = $_SESSION['voucher'] ?? '';
    if (!is_string($code) || $code === '') {
        return null;
    }
    $v   = voucher_find($code);
    $res = voucher_validate($v, $subtotal);
    if (!$res['ok'] || !$v) {
        voucher_clear();
        return null;
    }
    return ['code' => (string) $v['code'], 'discount' => $res['discount'], 'voucher' => $v];
}

/** Increment a voucher's usage counter (call once per placed order). */
function voucher_mark_used(string $code): void
{
    $code = voucher_normalize($code);
    if ($code === '') {
        return;
    }
    db()->prepare('UPDATE vouchers SET used_count = used_count + 1 WHERE code = :c')
        ->execute([':c' => $code]);
}
