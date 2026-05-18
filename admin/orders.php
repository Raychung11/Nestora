<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

$filter = input('status');
$sql = "SELECT * FROM orders";
$params = [];
if ($filter !== '') {
    $sql .= " WHERE order_status = :s";
    $params[':s'] = $filter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statuses = ['new','pending_confirmation','payment_pending','paid','supplier_checking','supplier_confirmed','preparing_delivery','delivered','completed','cancelled','refunded'];

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Orders</h2>
        <form method="get">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $filter===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if (!$orders): ?>
        <p class="muted">No orders found.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Order #</th><th>Customer</th><th>Phone</th><th>Total</th><th>Order status</th><th>Payment</th><th>Supplier</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td><?= e($o['customer_name']) ?></td>
                <td class="muted"><?= e($o['phone']) ?></td>
                <td><?= money((float)$o['total_amount']) ?></td>
                <td><span class="tag"><?= e(label($o['order_status'])) ?></span></td>
                <td><span class="badge badge-<?= $o['payment_status']==='paid'?'paid':'pending' ?>"><?= e(label($o['payment_status'])) ?></span></td>
                <td class="muted"><?= e(label($o['supplier_status'])) ?></td>
                <td class="muted"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/order_view.php?id=' . (int)$o['id']) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
