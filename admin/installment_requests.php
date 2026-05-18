<?php
$pageTitle = 'Installment Requests';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = in_array(input('status'), ['new','reviewing','approved','rejected'], true) ? input('status') : 'new';
    $pdo->prepare('UPDATE installment_requests SET status=:s, admin_notes=:n WHERE id=:id')
        ->execute([':s'=>$status, ':n'=>input('admin_notes'), ':id'=>(int)input('id')]);
    set_flash('success', 'Installment request updated.');
    redirect(base_url('/admin/installment_requests.php'));
}

$rows = $pdo->query(
    "SELECT r.*, o.order_number FROM installment_requests r
     LEFT JOIN orders o ON o.id = r.order_id ORDER BY r.created_at DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>Installment Requests</h2>
    <?php if (!$rows): ?><p class="muted">No installment requests yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Customer</th><th>Order</th><th>Total</th><th>Plan</th><th>Monthly</th><th>Status</th><th>Notes</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['customer_name']) ?></strong><br><span class="muted"><?= e($r['phone']) ?></span></td>
                <td><?= e((string)($r['order_number'] ?? '-')) ?></td>
                <td><?= money((float)$r['total_amount']) ?></td>
                <td><?= (int)$r['months'] ?> months</td>
                <td><?= money((float)$r['monthly_payment']) ?></td>
                <td>
                    <form method="post" style="display:flex;flex-direction:column;gap:6px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <select name="status">
                            <?php foreach (['new','reviewing','approved','rejected'] as $s): ?>
                                <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="admin_notes" value="<?= e((string)$r['admin_notes']) ?>" placeholder="Notes">
                        <button class="btn btn-soft btn-sm" type="submit">Save</button>
                    </form>
                </td>
                <td class="muted"><?= e((string)$r['admin_notes']) ?></td>
                <td class="muted"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
