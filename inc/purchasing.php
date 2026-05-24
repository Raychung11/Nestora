<?php
/**
 * NESTORA.my - Purchasing / Purchase Orders.
 *
 * Inbound stock: a PO is raised to a supplier, marked ordered, then
 * received (fully or partially) which increases product stock. Supplier
 * cost on the product is refreshed to the latest landed unit cost, and
 * payments to the supplier are tracked against the PO total.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function generate_po_number(): string
{
    return 'PO-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

/** Recompute a PO's subtotal/total from its line items. */
function po_recalc_totals(PDO $pdo, int $poId): void
{
    $sum = $pdo->prepare('SELECT COALESCE(SUM(line_total),0) FROM purchase_order_items WHERE po_id = :id');
    $sum->execute([':id' => $poId]);
    $total = (float) $sum->fetchColumn();
    $pdo->prepare('UPDATE purchase_orders SET subtotal = :s, total = :t WHERE id = :id')
        ->execute([':s' => $total, ':t' => $total, ':id' => $poId]);
}

/** Derive a payment status from amount paid vs total. */
function po_payment_status(float $total, float $paid): string
{
    if ($total > 0 && $paid >= $total - 0.001) {
        return 'paid';
    }
    return $paid > 0 ? 'partial' : 'unpaid';
}

/**
 * Apply received quantities (a map of [item_id => qty_received_now]).
 * Increases stock for the received delta, refreshes the product's
 * supplier cost, and moves the PO to partial/received as appropriate.
 */
function po_receive(PDO $pdo, int $poId, array $receivedNow): void
{
    $itemsStmt = $pdo->prepare('SELECT * FROM purchase_order_items WHERE po_id = :id');
    $itemsStmt->execute([':id' => $poId]);
    $items = $itemsStmt->fetchAll();
    if (!$items) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $allReceived = true;

        foreach ($items as $it) {
            $itemId   = (int) $it['id'];
            $ordered  = (int) $it['quantity_ordered'];
            $already  = (int) $it['quantity_received'];
            $addNow   = max(0, (int) ($receivedNow[$itemId] ?? 0));
            // Never receive more than was ordered.
            $addNow   = min($addNow, max(0, $ordered - $already));

            if ($addNow > 0) {
                $pdo->prepare(
                    'UPDATE purchase_order_items SET quantity_received = quantity_received + :q WHERE id = :id'
                )->execute([':q' => $addNow, ':id' => $itemId]);

                if (!empty($it['product_id'])) {
                    // Increase stock and refresh the latest landed cost.
                    $pdo->prepare(
                        "UPDATE products
                         SET stock_quantity = stock_quantity + :q,
                             supplier_cost  = :uc,
                             stock_status   = IF(track_inventory = 1 AND stock_quantity + :q2 > 0
                                                 AND stock_status = 'unavailable', 'available', stock_status)
                         WHERE id = :pid"
                    )->execute([
                        ':q' => $addNow, ':q2' => $addNow,
                        ':uc' => (float) $it['unit_cost'], ':pid' => (int) $it['product_id'],
                    ]);
                }
            }

            if ($already + $addNow < $ordered) {
                $allReceived = false;
            }
        }

        $status = $allReceived ? 'received' : 'partial';
        $pdo->prepare(
            'UPDATE purchase_orders
             SET status = :st, received_date = IF(:st2 = \'received\', CURDATE(), received_date)
             WHERE id = :id'
        )->execute([':st' => $status, ':st2' => $status, ':id' => $poId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
