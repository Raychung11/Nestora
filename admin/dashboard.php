<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

$totalOrders   = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$newInquiries  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('new','pending_confirmation')")->fetchColumn();
$pendingSupp   = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE supplier_status IN ('not_started','checking')")->fetchColumn();
$paidOrders    = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='paid'")->fetchColumn();
$monthlySales  = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' AND MONTH(created_at)=MONTH(CURRENT_DATE()) AND YEAR(created_at)=YEAR(CURRENT_DATE())")->fetchColumn();
$quizLeads     = (int) $pdo->query("SELECT COUNT(*) FROM comfort_quiz_leads WHERE status='new'")->fetchColumn();
$instRequests  = (int) $pdo->query("SELECT COUNT(*) FROM installment_requests WHERE status IN ('new','reviewing')")->fetchColumn();
$waLeads       = (int) $pdo->query("SELECT COUNT(*) FROM whatsapp_leads WHERE status='new'")->fetchColumn();
$paymentsToVerify = (int) $pdo->query("SELECT COUNT(*) FROM payment_proofs WHERE status='submitted'")->fetchColumn();

$topProducts = $pdo->query(
    "SELECT product_name, SUM(quantity) AS qty, SUM(line_total) AS revenue
     FROM order_items GROUP BY product_name ORDER BY qty DESC LIMIT 5"
)->fetchAll();

$repeatOil = $pdo->query(
    "SELECT o.customer_name, o.phone, COUNT(*) AS orders_count
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     JOIN products p ON p.id = oi.product_id
     WHERE p.product_type='essential_oil'
     GROUP BY o.phone, o.customer_name
     HAVING orders_count > 1
     ORDER BY orders_count DESC LIMIT 5"
)->fetchAll();

$recentOrders = $pdo->query(
    "SELECT * FROM orders ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="stat-grid">
    <div class="stat"><div class="num"><?= $totalOrders ?></div><div class="lbl">Total Orders</div></div>
    <div class="stat"><div class="num"><?= $newInquiries ?></div><div class="lbl">New Inquiries</div></div>
    <div class="stat"><div class="num"><?= $pendingSupp ?></div><div class="lbl">Pending Supplier</div></div>
    <div class="stat"><div class="num"><?= $paidOrders ?></div><div class="lbl">Paid Orders</div></div>
    <div class="stat"><div class="num"><?= money($monthlySales) ?></div><div class="lbl">Monthly Sales</div></div>
    <div class="stat"><div class="num"><?= $quizLeads ?></div><div class="lbl">New Quiz Leads</div></div>
    <div class="stat"><div class="num"><?= $instRequests ?></div><div class="lbl">Installment Requests</div></div>
    <div class="stat"><div class="num"><?= $waLeads ?></div><div class="lbl">New WhatsApp Leads</div></div>
    <div class="stat"><div class="num"><?= $paymentsToVerify ?></div><div class="lbl">Payments to Verify</div></div>
</div>

<?php if ($paymentsToVerify > 0): ?>
<div class="flash flash-info">
    <?= $paymentsToVerify ?> payment proof<?= $paymentsToVerify === 1 ? '' : 's' ?> awaiting verification.
    <a href="<?= base_url('/admin/payments.php?status=submitted') ?>">Review now &rarr;</a>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head"><h2>Recent orders</h2><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/orders.php') ?>">View all</a></div>
    <?php if (!$recentOrders): ?><p class="muted">No orders yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
            <tr>
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= money((float)$o['total_amount']) ?></td>
                <td><span class="badge badge-new"><?= e(label($o['order_status'])) ?></span></td>
                <td><span class="badge badge-<?= $o['payment_status']==='paid'?'paid':'pending' ?>"><?= e(label($o['payment_status'])) ?></span></td>
                <td class="muted"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/order_view.php?id=' . (int)$o['id']) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
    <div class="panel">
        <h2>Top products</h2>
        <?php if (!$topProducts): ?><p class="muted">No sales data yet.</p><?php else: ?>
        <table class="table">
            <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topProducts as $tp): ?>
                <tr><td><?= e($tp['product_name']) ?></td><td><?= (int)$tp['qty'] ?></td><td><?= money((float)$tp['revenue']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h2>Essential oil repeat customers</h2>
        <?php if (!$repeatOil): ?><p class="muted">No repeat scent customers yet.</p><?php else: ?>
        <table class="table">
            <thead><tr><th>Customer</th><th>Phone</th><th>Orders</th></tr></thead>
            <tbody>
            <?php foreach ($repeatOil as $rc): ?>
                <tr><td><?= e($rc['customer_name']) ?></td><td><?= e($rc['phone']) ?></td><td><?= (int)$rc['orders_count'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php admin_layout_end(); ?>
