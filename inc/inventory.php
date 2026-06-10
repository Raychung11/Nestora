<?php
/**
 * NESTORA.my - Stock / inventory.
 *
 * Inventory is opt-in per product (products.track_inventory). When an
 * order is paid, stock is decremented exactly once (guarded by
 * orders.stock_decremented). A bundle decrements its component products.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

/** Whether a product can be ordered for the requested quantity. */
function inventory_in_stock(array $product, int $wanted = 1): bool
{
    if (empty($product['track_inventory'])) {
        return true; // not tracked = always orderable
    }
    return (int) $product['stock_quantity'] >= max(1, $wanted);
}

/** Remaining quantity, or null when the product is not inventory-tracked. */
function inventory_remaining(array $product): ?int
{
    return empty($product['track_inventory']) ? null : (int) $product['stock_quantity'];
}

/** Decrement a single tracked product by $qty and flip to unavailable at 0. */
function inventory_apply(PDO $pdo, int $productId, int $qty): void
{
    if ($qty <= 0) {
        return;
    }
    $pdo->prepare(
        "UPDATE products
         SET stock_quantity = GREATEST(0, stock_quantity - :q),
             stock_status   = IF(stock_quantity - :q2 <= 0, 'unavailable', stock_status)
         WHERE id = :id AND track_inventory = 1"
    )->execute([':q' => $qty, ':q2' => $qty, ':id' => $productId]);
}

/**
 * Decrement stock for every line of a paid order. Idempotent: the order's
 * stock_decremented flag is set first (guarded) so concurrent callers
 * (webhook + admin action) cannot double-decrement.
 */
function inventory_decrement_for_order(PDO $pdo, int $orderId): void
{
    $guard = $pdo->prepare(
        'UPDATE orders SET stock_decremented = 1 WHERE id = :id AND stock_decremented = 0'
    );
    $guard->execute([':id' => $orderId]);
    if ($guard->rowCount() === 0) {
        return; // already done (or order missing)
    }

    $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = :id');
    $items->execute([':id' => $orderId]);

    foreach ($items->fetchAll() as $it) {
        $pid = (int) ($it['product_id'] ?? 0);
        $qty = (int) $it['quantity'];
        if ($pid <= 0 || $qty <= 0) {
            continue;
        }
        $p = $pdo->prepare('SELECT product_type, track_inventory FROM products WHERE id = :id');
        $p->execute([':id' => $pid]);
        $prod = $p->fetch();
        if (!$prod) {
            continue;
        }
        if ($prod['product_type'] === 'bundle') {
            // Decrement each component that is itself inventory-tracked.
            $comp = $pdo->prepare('SELECT product_id, quantity FROM bundle_items WHERE bundle_id = :b');
            $comp->execute([':b' => $pid]);
            foreach ($comp->fetchAll() as $c) {
                inventory_apply($pdo, (int) $c['product_id'], $qty * (int) $c['quantity']);
            }
        }
        if (!empty($prod['track_inventory'])) {
            inventory_apply($pdo, $pid, $qty);
        }
    }
}

/** Inventory-tracked products at or below their low-stock threshold. */
function inventory_low_stock(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, name, sku, stock_quantity, low_stock_threshold
         FROM products
         WHERE track_inventory = 1 AND low_stock_threshold > 0
           AND stock_quantity <= low_stock_threshold
         ORDER BY stock_quantity ASC, name ASC"
    )->fetchAll();
}
