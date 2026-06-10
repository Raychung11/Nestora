<?php
$pageTitle = 'Voucher Codes';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/vouchers.php';
require_admin();
$pdo = db();

$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM vouchers WHERE id = :id')->execute([':id' => (int) input('id')]);
        set_flash('success', 'Voucher deleted.');
        redirect(base_url('/admin/vouchers.php'));
    }

    if ($action === 'save') {
        $id        = (int) input('id');
        $code      = voucher_normalize(input('code'));
        $type      = in_array(input('type'), ['percent', 'fixed'], true) ? input('type') : 'percent';
        $value     = (float) input('value');
        $minSpend  = (float) input('min_spend');
        $maxUses   = max(0, (int) input('max_uses'));
        $startsAt  = input('starts_at') !== '' ? input('starts_at') : null;
        $expiresAt = input('expires_at') !== '' ? input('expires_at') : null;
        $desc      = input('description');
        $status    = in_array(input('status'), ['active', 'disabled'], true) ? input('status') : 'active';

        $errors = [];
        if ($code === '')   { $errors[] = 'Code is required.'; }
        if ($value <= 0)    { $errors[] = 'Discount value must be greater than zero.'; }
        if ($type === 'percent' && $value > 100) { $errors[] = 'A percentage discount cannot exceed 100%.'; }

        if (!$errors) {
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE vouchers SET code=:c, description=:d, type=:t, value=:v, min_spend=:m,
                         max_uses=:mu, starts_at=:sa, expires_at=:ea, status=:st WHERE id=:id'
                    )->execute([
                        ':c'=>$code, ':d'=>$desc ?: null, ':t'=>$type, ':v'=>$value, ':m'=>$minSpend,
                        ':mu'=>$maxUses, ':sa'=>$startsAt, ':ea'=>$expiresAt, ':st'=>$status, ':id'=>$id,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO vouchers (code, description, type, value, min_spend, max_uses, starts_at, expires_at, status)
                         VALUES (:c,:d,:t,:v,:m,:mu,:sa,:ea,:st)'
                    )->execute([
                        ':c'=>$code, ':d'=>$desc ?: null, ':t'=>$type, ':v'=>$value, ':m'=>$minSpend,
                        ':mu'=>$maxUses, ':sa'=>$startsAt, ':ea'=>$expiresAt, ':st'=>$status,
                    ]);
                }
                set_flash('success', 'Voucher saved.');
                redirect(base_url('/admin/vouchers.php'));
            } catch (Throwable $e) {
                set_flash('error', 'Could not save (the code may already exist).');
                redirect(base_url('/admin/vouchers.php'));
            }
        } else {
            set_flash('error', implode(' ', $errors));
        }
    }
}

if ($eid = (int) input('id')) {
    $st = $pdo->prepare('SELECT * FROM vouchers WHERE id = :id');
    $st->execute([':id' => $eid]);
    $editing = $st->fetch() ?: null;
}

$vouchers = $pdo->query('SELECT * FROM vouchers ORDER BY created_at DESC')->fetchAll();

$v = fn(string $k, $d = '') => $editing[$k] ?? $d;
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start">
    <div class="panel">
        <div class="panel-head"><h2>Voucher codes</h2></div>
        <?php if (!$vouchers): ?>
            <p class="muted">No vouchers yet. Create your first discount code.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Code</th><th>Discount</th><th>Min spend</th><th>Used</th><th>Valid</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($vouchers as $row):
                $disc = $row['type'] === 'percent'
                    ? rtrim(rtrim(number_format((float)$row['value'], 2), '0'), '.') . '%'
                    : money((float)$row['value']);
                $valid = ($row['starts_at'] ? date('d M', strtotime($row['starts_at'])) : '—')
                    . ' → ' . ($row['expires_at'] ? date('d M Y', strtotime($row['expires_at'])) : 'no end'); ?>
                <tr>
                    <td><strong><?= e($row['code']) ?></strong><?= $row['description'] ? '<br><span class="muted" style="font-size:.8rem">' . e($row['description']) . '</span>' : '' ?></td>
                    <td><?= e($disc) ?></td>
                    <td class="muted"><?= (float)$row['min_spend'] > 0 ? money((float)$row['min_spend']) : '—' ?></td>
                    <td class="muted"><?= (int)$row['used_count'] ?><?= (int)$row['max_uses'] > 0 ? ' / ' . (int)$row['max_uses'] : '' ?></td>
                    <td class="muted" style="font-size:.82rem"><?= e($valid) ?></td>
                    <td><span class="tag"><?= e(label($row['status'])) ?></span></td>
                    <td>
                        <div class="actions-inline">
                            <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/vouchers.php?id=' . (int)$row['id']) ?>">Edit</a>
                            <form method="post" data-confirm="Delete this voucher?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <form class="panel" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
        <h2><?= $editing ? 'Edit voucher' : 'New voucher' ?></h2>
        <div class="field"><label>Code</label><input type="text" name="code" value="<?= e((string)$v('code')) ?>" style="text-transform:uppercase" required></div>
        <div class="field"><label>Description (optional)</label><input type="text" name="description" value="<?= e((string)$v('description')) ?>"></div>
        <div class="form-row">
            <div class="field">
                <label>Type</label>
                <select name="type">
                    <option value="percent" <?= $v('type','percent')==='percent'?'selected':'' ?>>Percentage (%)</option>
                    <option value="fixed" <?= $v('type','percent')==='fixed'?'selected':'' ?>>Fixed amount (RM)</option>
                </select>
            </div>
            <div class="field"><label>Value</label><input type="number" step="0.01" name="value" value="<?= e((string)$v('value','0')) ?>" required></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Minimum spend (RM)</label><input type="number" step="0.01" name="min_spend" value="<?= e((string)$v('min_spend','0')) ?>"></div>
            <div class="field"><label>Max uses (0 = unlimited)</label><input type="number" name="max_uses" value="<?= e((string)$v('max_uses','0')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Starts (optional)</label><input type="date" name="starts_at" value="<?= e((string)$v('starts_at')) ?>"></div>
            <div class="field"><label>Expires (optional)</label><input type="date" name="expires_at" value="<?= e((string)$v('expires_at')) ?>"></div>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="active" <?= $v('status','active')==='active'?'selected':'' ?>>Active</option>
                <option value="disabled" <?= $v('status','active')==='disabled'?'selected':'' ?>>Disabled</option>
            </select>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= $editing ? 'Save voucher' : 'Create voucher' ?></button>
        <?php if ($editing): ?>
            <p class="muted" style="text-align:center;margin-top:12px"><a href="<?= base_url('/admin/vouchers.php') ?>">Cancel edit</a></p>
        <?php endif; ?>
    </form>
</div>
<?php admin_layout_end(); ?>
