<?php
$pageTitle = 'Scent Subscriptions';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/subscriptions.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $op    = input('op');
    $subId = (int) input('id');
    $sStmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = :id LIMIT 1');
    $sStmt->execute([':id' => $subId]);
    $sub = $sStmt->fetch();

    if (!$sub) {
        set_flash('error', 'Subscription not found.');
    } elseif ($op === 'pause') {
        $pdo->prepare("UPDATE subscriptions SET status='paused' WHERE id=:id")->execute([':id' => $subId]);
        set_flash('success', 'Subscription paused.');
    } elseif ($op === 'resume') {
        $pdo->prepare("UPDATE subscriptions SET status='active', next_renewal_date=:n WHERE id=:id")
            ->execute([':n' => subscription_next_date((string) $sub['frequency']), ':id' => $subId]);
        set_flash('success', 'Subscription resumed.');
    } elseif ($op === 'cancel') {
        $pdo->prepare("UPDATE subscriptions SET status='cancelled' WHERE id=:id")->execute([':id' => $subId]);
        set_flash('info', 'Subscription cancelled.');
    } elseif ($op === 'generate') {
        if ($sub['status'] === 'active') {
            $oid = subscription_generate_order($pdo, $sub);
            if ($oid) {
                $num = $pdo->query('SELECT order_number FROM orders WHERE id=' . (int)$oid)->fetchColumn();
                set_flash('success', 'Refill order ' . $num . ' generated and emailed to the customer.');
            } else {
                set_flash('error', 'Could not generate the refill order.');
            }
        } else {
            set_flash('error', 'Only active subscriptions can generate a refill.');
        }
    }
    redirect(base_url('/admin/subscriptions.php'));
}

$subs = $pdo->query(
    "SELECT s.*, (s.status='active' AND s.next_renewal_date IS NOT NULL AND s.next_renewal_date <= CURDATE()) AS is_due
     FROM subscriptions s
     ORDER BY FIELD(s.status,'active','paused','cancelled'), s.next_renewal_date ASC, s.id DESC"
)->fetchAll();

$dueCount = 0;
foreach ($subs as $s) { if ($s['is_due']) { $dueCount++; } }

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Scent refill subscriptions</h2>
        <?php if ($dueCount > 0): ?><span class="tag"><?= $dueCount ?> due now</span><?php endif; ?>
    </div>
    <p class="muted" style="margin-top:-6px">
        Each cycle a subscription generates a refill order + invoice and emails the customer a pay link.
        Due refills are generated automatically by the daily cron (<code>cron_subscriptions.php</code>),
        or generate one manually below.
    </p>
    <?php if (!$subs): ?>
        <p class="muted">No subscriptions yet.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Customer</th><th>Scent</th><th>Schedule</th><th>Per refill</th><th>Next refill</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($subs as $s): ?>
            <tr<?= $s['is_due'] ? ' style="background:var(--oat)"' : '' ?>>
                <td><strong><?= e($s['customer_name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e((string)$s['phone']) ?></span></td>
                <td><?= e($s['product_name']) ?> &times; <?= (int)$s['quantity'] ?></td>
                <td><?= e(subscription_frequency_label($s['frequency'])) ?></td>
                <td><?= money((float)$s['unit_price']) ?></td>
                <td class="muted"><?= $s['next_renewal_date'] ? e(date('d M Y', strtotime($s['next_renewal_date']))) : '—' ?><?= $s['is_due'] ? ' <span class="tag">due</span>' : '' ?></td>
                <td><span class="tag"><?= e(label($s['status'])) ?></span></td>
                <td>
                    <div class="actions-inline">
                        <?php if ($s['status'] === 'active'): ?>
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="op" value="generate">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-primary btn-sm" type="submit">Generate refill</button>
                            </form>
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="op" value="pause">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-soft btn-sm" type="submit">Pause</button>
                            </form>
                        <?php elseif ($s['status'] === 'paused'): ?>
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="op" value="resume">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-soft btn-sm" type="submit">Resume</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($s['status'] !== 'cancelled'): ?>
                            <form method="post" data-confirm="Cancel this subscription?"><?= csrf_field() ?>
                                <input type="hidden" name="op" value="cancel">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Cancel</button>
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
