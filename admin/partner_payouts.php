<?php
$pageTitle = 'Partner Payouts';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/partners.php';
$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (input('action') === 'record') {
        $pid    = (int) input('partner_id');
        $amount = round((float) input('amount'), 2);
        $method = input('method') ?: null;
        $ref    = input('reference') ?: null;
        $notes  = input('notes') ?: null;
        $date   = input('paid_at') ?: date('Y-m-d');
        if ($pid > 0 && $amount > 0) {
            $pdo->prepare(
                'INSERT INTO partner_payouts (partner_id, amount, method, reference, notes, paid_at, recorded_by)
                 VALUES (:pid,:amt,:m,:r,:n,:d,:by)'
            )->execute([
                ':pid' => $pid, ':amt' => $amount, ':m' => $method,
                ':r' => $ref, ':n' => $notes, ':d' => $date, ':by' => (int) $admin['id'],
            ]);
            set_flash('success', 'Payout recorded.');
        } else {
            set_flash('error', 'Pick a partner and enter an amount above zero.');
        }
    } elseif (input('action') === 'delete') {
        $pdo->prepare('DELETE FROM partner_payouts WHERE id = :id')->execute([':id' => (int) input('id')]);
        set_flash('success', 'Payout removed.');
    }
    redirect(base_url('/admin/partner_payouts.php' . (input('partner_id') ? '?partner_id=' . (int)input('partner_id') : '')));
}

$preselect = (int) input('partner_id');
$partners  = $pdo->query("SELECT id, name, business_name, referral_code, tier FROM partners WHERE status='active' ORDER BY name")->fetchAll();

$payouts = $pdo->query(
    "SELECT pp.*, p.name AS partner_name
     FROM partner_payouts pp
     LEFT JOIN partners p ON p.id = pp.partner_id
     ORDER BY pp.paid_at DESC, pp.id DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start">
    <div class="panel">
        <div class="panel-head"><h2>Payout history</h2></div>
        <?php if (!$payouts): ?>
            <p class="muted">No payouts yet.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Date</th><th>Partner</th><th>Amount</th><th>Method</th><th>Ref</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payouts as $po): ?>
                <tr>
                    <td class="muted"><?= e($po['paid_at'] ?: date('d M Y', strtotime($po['created_at']))) ?></td>
                    <td><?= e((string)$po['partner_name']) ?></td>
                    <td><strong><?= money((float)$po['amount']) ?></strong></td>
                    <td class="muted"><?= e((string)$po['method']) ?></td>
                    <td class="muted"><?= e((string)$po['reference']) ?></td>
                    <td>
                        <form method="post" data-confirm="Delete this payout?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$po['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <form class="panel" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record">
        <h2>Record a payout</h2>
        <div class="field">
            <label>Partner</label>
            <select name="partner_id" required onchange="document.getElementById('bal').textContent='—'">
                <option value="">— Select partner —</option>
                <?php foreach ($partners as $p):
                    $bal = partner_balance($pdo, (int) $p['id']); ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $preselect===(int)$p['id']?'selected':'' ?>>
                        <?= e($p['name']) ?> — <?= e($p['referral_code']) ?> (balance <?= money($bal) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="field"><label>Amount (RM)</label><input type="number" step="0.01" min="0" name="amount" required></div>
            <div class="field"><label>Paid date</label><input type="date" name="paid_at" value="<?= e(date('Y-m-d')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Method</label><input type="text" name="method" placeholder="Bank transfer / cash"></div>
            <div class="field"><label>Reference</label><input type="text" name="reference" placeholder="Bank ref"></div>
        </div>
        <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
        <button class="btn btn-primary btn-block" type="submit">Record payout</button>
    </form>
</div>
<?php admin_layout_end(); ?>
