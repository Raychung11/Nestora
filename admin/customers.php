<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

$customers = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) AS order_count
     FROM customers c ORDER BY c.created_at DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>Customers</h2>
    <?php if (!$customers): ?><p class="muted">No customers yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>City</th><th>Orders</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong></td>
                <td><?= e($c['phone']) ?></td>
                <td class="muted"><?= e((string)$c['email']) ?></td>
                <td><?= e((string)$c['city']) ?></td>
                <td><?= (int)$c['order_count'] ?></td>
                <td class="muted"><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
