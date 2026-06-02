<?php
$pageTitle = 'Partner Applications';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/partners.php';
require_once __DIR__ . '/../inc/mailer.php';
$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    $appId  = (int) input('id');

    $aStmt = $pdo->prepare('SELECT * FROM partner_applications WHERE id = :id');
    $aStmt->execute([':id' => $appId]);
    $app = $aStmt->fetch();

    if (!$app) {
        set_flash('error', 'Application not found.');
        redirect(base_url('/admin/partner_applications.php'));
    }

    if ($action === 'approve' && $app['status'] === 'new') {
        $tier  = in_array(input('tier'), ['starter', 'elite'], true) ? input('tier') : (string) $app['requested_tier'];
        $defs  = partner_tier_defaults($tier);
        $rateC = input('commission_rate') !== '' ? (float) input('commission_rate') : $defs['commission'];
        $rateW = input('wholesale_discount_rate') !== '' ? (float) input('wholesale_discount_rate') : $defs['wholesale'];

        // Don't approve if a partner with this email already exists.
        $exists = $pdo->prepare('SELECT id FROM partners WHERE email = :e LIMIT 1');
        $exists->execute([':e' => $app['email']]);
        if ($exists->fetchColumn()) {
            set_flash('error', 'A partner with this email already exists.');
            redirect(base_url('/admin/partner_applications.php'));
        }

        $tempPass = bin2hex(random_bytes(5)); // 10-char temp password
        $code     = generate_referral_code($pdo);

        $pdo->prepare(
            "INSERT INTO partners
             (name, business_name, email, phone, password_hash, referral_code,
              tier, commission_rate, wholesale_discount_rate, status, application_id)
             VALUES (:n,:bn,:e,:p,:ph,:c,:t,:rc,:rw,'active',:aid)"
        )->execute([
            ':n' => $app['name'], ':bn' => $app['business_name'],
            ':e' => $app['email'], ':p' => $app['phone'],
            ':ph' => password_hash($tempPass, PASSWORD_DEFAULT),
            ':c' => $code, ':t' => $tier, ':rc' => $rateC, ':rw' => $rateW,
            ':aid' => $appId,
        ]);
        $pdo->prepare("UPDATE partner_applications SET status='approved', reviewed_by=:b, reviewed_at=NOW() WHERE id=:id")
            ->execute([':b' => (int) $admin['id'], ':id' => $appId]);

        $portal = site_origin() . base_url('/partner/login.php');
        $refLink = site_origin() . base_url('/?ref=' . urlencode($code));
        send_mail((string) $app['email'],
            'Welcome to the Nestora Partner Program',
            mail_template('Your partner account is ready',
                '<p>Hi ' . e((string) $app['name']) . ', welcome to Nestora as a <strong>'
                . e(label($tier)) . ' Partner</strong>.</p>'
                . '<p><strong>Login:</strong> <a href="' . e($portal) . '">' . e($portal) . '</a><br>'
                . '<strong>Email:</strong> ' . e((string) $app['email']) . '<br>'
                . '<strong>Temporary password:</strong> ' . e($tempPass) . '</p>'
                . '<p>Please sign in and change your password.</p>'
                . '<p><strong>Your referral code:</strong> ' . e($code) . '<br>'
                . '<strong>Your referral link:</strong> <a href="' . e($refLink) . '">' . e($refLink) . '</a></p>'));
        set_flash('success', 'Application approved. Welcome email sent.');
    } elseif ($action === 'reject' && $app['status'] === 'new') {
        $pdo->prepare("UPDATE partner_applications SET status='rejected', reviewed_by=:b, reviewed_at=NOW() WHERE id=:id")
            ->execute([':b' => (int) $admin['id'], ':id' => $appId]);
        set_flash('info', 'Application rejected.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM partner_applications WHERE id = :id')->execute([':id' => $appId]);
        set_flash('success', 'Application deleted.');
    }
    redirect(base_url('/admin/partner_applications.php'));
}

$apps = $pdo->query(
    "SELECT * FROM partner_applications
     ORDER BY FIELD(status,'new','approved','rejected'), created_at DESC"
)->fetchAll();

$starter = partner_tier_defaults('starter');
$elite   = partner_tier_defaults('elite');
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head"><h2>Partner applications</h2></div>
    <p class="muted">Approving an application creates a partner account, generates a referral code, and emails the welcome message with a temporary password.</p>
    <?php if (!$apps): ?>
        <p class="muted">No applications yet.</p>
    <?php else: ?>
        <?php foreach ($apps as $app): ?>
            <div class="panel" style="margin:14px 0;background:var(--cream)">
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                    <div>
                        <strong><?= e($app['name']) ?></strong>
                        <?php if ($app['business_name']): ?> &middot; <?= e((string)$app['business_name']) ?><?php endif; ?>
                        <span class="tag" style="margin-left:8px"><?= e(label($app['status'])) ?></span>
                        <span class="tag">Requested: <?= e(label($app['requested_tier'])) ?></span>
                        <br><span class="muted" style="font-size:.85rem"><?= e((string)$app['email']) ?> &middot; <?= e((string)$app['phone']) ?> &middot; <?= e(date('d M Y', strtotime($app['created_at']))) ?></span>
                        <?php if ($app['message']): ?><p class="muted" style="margin-top:8px"><?= nl2br(e((string)$app['message'])) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <?php if ($app['status'] === 'new'): ?>
                            <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;justify-content:end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                                <div class="field" style="margin:0">
                                    <label style="font-size:.78rem">Tier</label>
                                    <select name="tier" onchange="var d=this.value==='elite'?<?= json_encode([$elite['commission'],$elite['wholesale']]) ?>:<?= json_encode([$starter['commission'],$starter['wholesale']]) ?>;this.form.commission_rate.value=d[0];this.form.wholesale_discount_rate.value=d[1];">
                                        <option value="starter" <?= $app['requested_tier']==='starter'?'selected':'' ?>>Starter</option>
                                        <option value="elite"   <?= $app['requested_tier']==='elite'?'selected':'' ?>>Elite</option>
                                    </select>
                                </div>
                                <div class="field" style="margin:0;width:90px">
                                    <label style="font-size:.78rem">Comm %</label>
                                    <input type="number" step="0.01" name="commission_rate" value="<?= e((string)($app['requested_tier']==='elite'?$elite['commission']:$starter['commission'])) ?>">
                                </div>
                                <div class="field" style="margin:0;width:90px">
                                    <label style="font-size:.78rem">Wholesale %</label>
                                    <input type="number" step="0.01" name="wholesale_discount_rate" value="<?= e((string)($app['requested_tier']==='elite'?$elite['wholesale']:$starter['wholesale'])) ?>">
                                </div>
                                <button class="btn btn-primary btn-sm" type="submit">Approve &amp; create</button>
                            </form>
                            <form method="post" data-confirm="Reject this application?" style="margin-top:8px;text-align:right">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                                <button class="btn btn-soft btn-sm" type="submit">Reject</button>
                            </form>
                        <?php else: ?>
                            <form method="post" data-confirm="Delete this application record?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
