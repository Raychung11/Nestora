<?php
$pageTitle = 'Partner';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/partners.php';
require_once __DIR__ . '/../inc/mailer.php';
require_admin();
$pdo = db();

$id = (int) input('id');
$stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id');
$stmt->execute([':id' => $id]);
$partner = $stmt->fetch();
if (!$partner) { set_flash('error', 'Partner not found.'); redirect(base_url('/admin/partners.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action', 'save');

    if ($action === 'save') {
        $tier  = in_array(input('tier'), ['starter', 'elite'], true) ? input('tier') : $partner['tier'];
        $rateC = max(0.0, min(100.0, (float) input('commission_rate')));
        $rateW = max(0.0, min(90.0,  (float) input('wholesale_discount_rate')));
        $status = in_array(input('status'), ['active','suspended','cancelled'], true) ? input('status') : $partner['status'];

        $pdo->prepare(
            'UPDATE partners
             SET name = :n, business_name = :bn, phone = :p, address = :a,
                 tier = :t, commission_rate = :rc, wholesale_discount_rate = :rw,
                 status = :st
             WHERE id = :id'
        )->execute([
            ':n' => input('name'), ':bn' => input('business_name') ?: null,
            ':p' => input('phone') ?: null, ':a' => input('address') ?: null,
            ':t' => $tier, ':rc' => $rateC, ':rw' => $rateW, ':st' => $status,
            ':id' => $id,
        ]);
        set_flash('success', 'Partner updated.');
    } elseif ($action === 'reset_password') {
        $temp = bin2hex(random_bytes(5));
        $pdo->prepare('UPDATE partners SET password_hash = :p WHERE id = :id')
            ->execute([':p' => password_hash($temp, PASSWORD_DEFAULT), ':id' => $id]);
        if ($partner['email']) {
            send_mail((string) $partner['email'],
                'Your Nestora partner password has been reset',
                mail_template('Password reset',
                    '<p>Hi ' . e((string) $partner['name']) . ', your partner password has been reset.</p>'
                    . '<p><strong>Temporary password:</strong> ' . e($temp) . '</p>'
                    . '<p>Sign in at <a href="' . e(site_origin() . base_url('/partner/login.php')) . '">'
                    . e(site_origin() . base_url('/partner/login.php')) . '</a> and change it.</p>'));
        }
        set_flash('success', 'Password reset and emailed to the partner.');
    }
    redirect(base_url('/admin/partner_form.php?id=' . $id));
}

$balance = partner_balance($pdo, $id);
$earned  = (float) $pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM partner_commissions WHERE partner_id={$id} AND status='approved'")->fetchColumn();
$pending = (float) $pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM partner_commissions WHERE partner_id={$id} AND status='pending'")->fetchColumn();
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel-head">
    <h2>Partner: <?= e($partner['name']) ?> <span class="tag" style="margin-left:8px"><?= e(label($partner['tier'])) ?></span></h2>
    <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/partners.php') ?>">&larr; Back</a>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start">
    <form class="panel" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <h2>Edit partner</h2>
        <div class="form-row">
            <div class="field"><label>Full name</label><input type="text" name="name" value="<?= e($partner['name']) ?>" required></div>
            <div class="field"><label>Business name</label><input type="text" name="business_name" value="<?= e((string)$partner['business_name']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Email</label><input type="email" value="<?= e($partner['email']) ?>" disabled></div>
            <div class="field"><label>Phone</label><input type="text" name="phone" value="<?= e((string)$partner['phone']) ?>"></div>
        </div>
        <div class="field"><label>Address</label><textarea name="address"><?= e((string)$partner['address']) ?></textarea></div>
        <div class="form-row">
            <div class="field">
                <label>Tier</label>
                <select name="tier">
                    <option value="starter" <?= $partner['tier']==='starter'?'selected':'' ?>>Starter</option>
                    <option value="elite"   <?= $partner['tier']==='elite'?'selected':'' ?>>Elite</option>
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="active"    <?= $partner['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="suspended" <?= $partner['status']==='suspended'?'selected':'' ?>>Suspended</option>
                    <option value="cancelled" <?= $partner['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="field"><label>Commission rate (%)</label><input type="number" step="0.01" min="0" max="100" name="commission_rate" value="<?= e((string)$partner['commission_rate']) ?>"></div>
            <div class="field"><label>Wholesale discount (%)</label><input type="number" step="0.01" min="0" max="90" name="wholesale_discount_rate" value="<?= e((string)$partner['wholesale_discount_rate']) ?>"></div>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Save partner</button>
    </form>

    <div class="panel">
        <h2>Summary</h2>
        <p>Referral code: <code><?= e($partner['referral_code']) ?></code><br>
            Referral link: <a href="<?= e(site_origin() . base_url('/?ref=' . urlencode($partner['referral_code']))) ?>" target="_blank" rel="noopener">share link</a></p>
        <p class="muted">Approved earnings: <strong><?= money($earned) ?></strong><br>
            Pending commissions: <?= money($pending) ?><br>
            Balance due (after payouts): <strong><?= money($balance) ?></strong></p>
        <form method="post" data-confirm="Reset this partner's password and email them a new temporary one?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <button class="btn btn-soft btn-sm" type="submit">Reset password &amp; email</button>
        </form>
        <p style="margin-top:18px"><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/commissions.php?partner_id=' . $id) ?>">View commissions</a>
            <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/partner_payouts.php?partner_id=' . $id) ?>">Record payout</a></p>
    </div>
</div>
<?php admin_layout_end(); ?>
