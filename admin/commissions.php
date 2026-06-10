<?php
$pageTitle = 'Commissions';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/partners.php';
require_admin();
$pdo = db();

$filterPartner = (int) input('partner_id');
$filterStatus  = in_array(input('status'), ['pending','approved','paid','void'], true) ? input('status') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $cid = (int) input('id');
    $new = input('new_status');
    if (in_array($new, ['pending','approved','paid','void'], true) && $cid > 0) {
        $pdo->prepare('UPDATE partner_commissions SET status = :s WHERE id = :id')
            ->execute([':s' => $new, ':id' => $cid]);
        set_flash('success', 'Commission updated.');
    }
    redirect(base_url('/admin/commissions.php'
        . ($filterPartner ? '?partner_id=' . $filterPartner : '')));
}

$where  = ['1=1'];
$params = [];
if ($filterPartner) { $where[] = 'pc.partner_id = :pid'; $params[':pid'] = $filterPartner; }
if ($filterStatus)  { $where[] = 'pc.status = :st';     $params[':st']  = $filterStatus; }
$sql = 'SELECT pc.*, p.name AS partner_name, p.referral_code, o.order_number, o.payment_status
        FROM partner_commissions pc
        LEFT JOIN partners p ON p.id = pc.partner_id
        LEFT JOIN orders   o ON o.id = pc.order_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY pc.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Commissions<?php if ($filterPartner): ?> <span class="muted" style="font-size:.9rem">&middot; partner #<?= $filterPartner ?></span><?php endif; ?></h2>
        <form method="get" style="display:flex;gap:8px">
            <?php if ($filterPartner): ?><input type="hidden" name="partner_id" value="<?= $filterPartner ?>"><?php endif; ?>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['pending','approved','paid','void'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-soft btn-sm" type="submit">Filter</button>
        </form>
    </div>
    <?php if (!$rows): ?>
        <p class="muted">No commissions yet.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Date</th><th>Partner</th><th>Order</th><th>Base</th><th>Rate</th><th>Commission</th><th>Status</th><th>Set status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="muted"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                <td><?= e((string)$r['partner_name']) ?><br><code><?= e((string)$r['referral_code']) ?></code></td>
                <td><a href="<?= base_url('/admin/order_view.php?id=' . (int)$r['order_id']) ?>"><?= e((string)$r['order_number']) ?></a><br><span class="muted" style="font-size:.78rem">Payment: <?= e(label((string)$r['payment_status'])) ?></span></td>
                <td><?= money((float)$r['base_amount']) ?></td>
                <td class="muted"><?= e((string)$r['rate']) ?>%</td>
                <td><strong><?= money((float)$r['commission_amount']) ?></strong></td>
                <td><span class="tag"><?= e(label($r['status'])) ?></span></td>
                <td>
                    <form method="post" style="display:flex;gap:6px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <select name="new_status">
                            <?php foreach (['pending','approved','paid','void'] as $s): ?>
                                <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-soft btn-sm" type="submit">Set</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
