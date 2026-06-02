<?php
$pageTitle = 'Partners';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/partners.php';
require_admin();
$pdo = db();

$partners = $pdo->query(
    "SELECT p.*,
        (SELECT COALESCE(SUM(commission_amount),0) FROM partner_commissions
         WHERE partner_id = p.id AND status = 'approved') AS earned,
        (SELECT COALESCE(SUM(amount),0) FROM partner_payouts
         WHERE partner_id = p.id) AS paid,
        (SELECT COUNT(*) FROM partner_commissions WHERE partner_id = p.id) AS referrals
     FROM partners p
     ORDER BY FIELD(p.status,'active','suspended','cancelled'), p.created_at DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head"><h2>Partners</h2></div>
    <?php if (!$partners): ?>
        <p class="muted">No partners yet. Approve an application to create the first partner.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Partner</th><th>Email</th><th>Code</th><th>Tier</th><th>Comm / Wholesale</th><th>Referrals</th><th>Earned</th><th>Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($partners as $p):
            $balance = round((float)$p['earned'] - (float)$p['paid'], 2); ?>
            <tr>
                <td><strong><?= e($p['name']) ?></strong><?= $p['business_name'] ? '<br><span class="muted" style="font-size:.8rem">' . e((string)$p['business_name']) . '</span>' : '' ?></td>
                <td class="muted"><?= e($p['email']) ?></td>
                <td><code><?= e($p['referral_code']) ?></code></td>
                <td><span class="tag"><?= e(label($p['tier'])) ?></span></td>
                <td class="muted"><?= e((string)$p['commission_rate']) ?>% / <?= e((string)$p['wholesale_discount_rate']) ?>%</td>
                <td><?= (int)$p['referrals'] ?></td>
                <td><?= money((float)$p['earned']) ?></td>
                <td><strong><?= money($balance) ?></strong></td>
                <td><span class="tag"><?= e(label($p['status'])) ?></span></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/partner_form.php?id=' . (int)$p['id']) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
