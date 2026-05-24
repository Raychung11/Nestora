<?php
$pageTitle = 'Order';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/documents.php';
require_once __DIR__ . '/../inc/inventory.php';
require_once __DIR__ . '/../inc/notifications.php';
require_admin();
$pdo = db();

$id = (int) input('id');
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();
if (!$order) { set_flash('error', 'Order not found.'); redirect(base_url('/admin/orders.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $orderStatuses    = ['new','pending_confirmation','payment_pending','paid','supplier_checking','supplier_confirmed','preparing_delivery','delivered','completed','cancelled','refunded'];
    $paymentStatuses  = ['unpaid','pending','paid','failed','refunded'];
    $supplierStatuses = ['not_started','checking','confirmed','unavailable'];
    $deliveryStatuses = ['not_started','preparing','shipped','delivered'];

    $os = in_array(input('order_status'), $orderStatuses, true) ? input('order_status') : $order['order_status'];
    $ps = in_array(input('payment_status'), $paymentStatuses, true) ? input('payment_status') : $order['payment_status'];
    $ss = in_array(input('supplier_status'), $supplierStatuses, true) ? input('supplier_status') : $order['supplier_status'];
    $ds = in_array(input('delivery_status'), $deliveryStatuses, true) ? input('delivery_status') : $order['delivery_status'];
    $notes = input('admin_notes');

    $pdo->prepare(
        'UPDATE orders SET order_status=:os, payment_status=:ps, supplier_status=:ss,
         delivery_status=:ds, admin_notes=:n WHERE id=:id'
    )->execute([':os'=>$os, ':ps'=>$ps, ':ss'=>$ss, ':ds'=>$ds, ':n'=>$notes, ':id'=>$id]);

    ensure_invoice($pdo, $id);
    if ($ps === 'paid') {
        ensure_receipt($pdo, $id);
        inventory_decrement_for_order($pdo, $id);
    }

    // Email the customer if the order moved into a notify-able status.
    $fresh = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $fresh->execute([':id' => $id]);
    if ($freshOrder = $fresh->fetch()) {
        notify_order_status($pdo, $freshOrder);
    }

    set_flash('success', 'Order updated.');
    redirect(base_url('/admin/order_view.php?id=' . $id));
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemsStmt->execute([':id' => $id]);
$items = $itemsStmt->fetchAll();

$orderStatuses    = ['new','pending_confirmation','payment_pending','paid','supplier_checking','supplier_confirmed','preparing_delivery','delivered','completed','cancelled','refunded'];
$paymentStatuses  = ['unpaid','pending','paid','failed','refunded'];
$supplierStatuses = ['not_started','checking','confirmed','unavailable'];
$deliveryStatuses = ['not_started','preparing','shipped','delivered'];

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel-head">
    <h2>Order <?= e($order['order_number']) ?></h2>
    <div class="actions-inline">
        <a class="btn btn-soft btn-sm" href="<?= base_url('/document.php?order=' . urlencode($order['order_number']) . '&type=invoice') ?>" target="_blank" rel="noopener">Invoice</a>
        <?php if (!empty($order['receipt_number'])): ?>
            <a class="btn btn-soft btn-sm" href="<?= base_url('/document.php?order=' . urlencode($order['order_number']) . '&type=receipt') ?>" target="_blank" rel="noopener">Receipt</a>
        <?php endif; ?>
        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/orders.php') ?>">&larr; Back to orders</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start">
    <div class="panel">
        <h2>Items</h2>
        <table class="table">
            <thead><tr><th>Product</th><th>SKU</th><th>Unit</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['product_name']) ?></td>
                    <td class="muted"><?= e($it['sku']) ?></td>
                    <td><?= money((float)$it['unit_price']) ?></td>
                    <td><?= (int)$it['quantity'] ?></td>
                    <td><?= money((float)$it['line_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="text-align:right;margin-top:16px;font-family:var(--font-serif);font-size:1.3rem;color:var(--brown)">
            Total: <?= money((float)$order['total_amount']) ?>
        </div>

        <h2 style="margin-top:26px">Customer</h2>
        <p><strong><?= e($order['customer_name']) ?></strong></p>
        <p class="muted"><?= e($order['phone']) ?> <?= $order['email'] ? '&middot; ' . e($order['email']) : '' ?></p>
        <p class="muted"><?= nl2br(e((string)$order['address'])) ?></p>
        <p class="muted">Payment method: <?= e(label($order['payment_method'])) ?>
            <?php if ((int)$order['installment_months'] > 0): ?>
                &middot; Installment: <?= (int)$order['installment_months'] ?> months
                (<?= money(monthly_payment((float)$order['total_amount'], (int)$order['installment_months'])) ?>/mo)
            <?php endif; ?>
        </p>
    </div>

    <form class="panel" method="post">
        <?= csrf_field() ?>
        <h2>Manage</h2>
        <div class="field">
            <label>Order status</label>
            <select name="order_status">
                <?php foreach ($orderStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Payment status</label>
            <select name="payment_status">
                <?php foreach ($paymentStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $order['payment_status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Supplier status</label>
            <select name="supplier_status">
                <?php foreach ($supplierStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $order['supplier_status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Delivery status</label>
            <select name="delivery_status">
                <?php foreach ($deliveryStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $order['delivery_status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Internal notes</label><textarea name="admin_notes"><?= e((string)$order['admin_notes']) ?></textarea></div>
        <button class="btn btn-primary btn-block" type="submit">Update order</button>
    </form>
</div>
<?php admin_layout_end(); ?>
