<?php
$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/purchasing.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (input('action') === 'delete') {
        // Only draft/cancelled POs can be deleted (received stock stays intact).
        $pdo->prepare("DELETE FROM purchase_orders WHERE id = :id AND status IN ('draft','cancelled')")
            ->execute([':id' => (int) input('id')]);
        set_flash('success', 'Purchase order deleted.');
    }
    redirect(base_url('/admin/purchase_orders.php'));
}

$pos = $pdo->query(
    "SELECT po.*, s.company_name
     FROM purchase_orders po
     LEFT JOIN suppliers s ON s.id = po.supplier_id
     ORDER BY FIELD(po.status,'ordered','partial','draft','received','cancelled'), po.created_at DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Purchase orders</h2>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/purchase_order_form.php') ?>">+ New purchase order</a>
    </div>
    <p class="muted" style="margin-top:-6px">Order stock from suppliers and receive it into inventory. Receiving a PO increases product stock automatically.</p>
    <?php if (!$pos): ?>
        <p class="muted">No purchase orders yet.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>PO #</th><th>Supplier</th><th>Ordered</th><th>Expected</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pos as $po): ?>
            <tr>
                <td><strong><?= e($po['po_number']) ?></strong></td>
                <td><?= e($po['company_name'] ?? '—') ?></td>
                <td class="muted"><?= $po['order_date'] ? e(date('d M Y', strtotime($po['order_date']))) : '—' ?></td>
                <td class="muted"><?= $po['expected_date'] ? e(date('d M Y', strtotime($po['expected_date']))) : '—' ?></td>
                <td><?= money((float)$po['total']) ?></td>
                <td><span class="tag"><?= e(label($po['payment_status'])) ?></span></td>
                <td><span class="badge badge-<?= $po['status']==='received'?'paid':($po['status']==='cancelled'?'unavailable':'preorder') ?>"><?= e(label($po['status'])) ?></span></td>
                <td>
                    <div class="actions-inline">
                        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/purchase_order_form.php?id=' . (int)$po['id']) ?>">Open</a>
                        <?php if (in_array($po['status'], ['draft','cancelled'], true)): ?>
                            <form method="post" data-confirm="Delete this purchase order?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$po['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
