<?php
require_once __DIR__ . '/inc/customer_auth.php';
require_once __DIR__ . '/inc/documents.php';
require_once __DIR__ . '/inc/subscriptions.php';

$pageTitle = 'My Account';
$session   = require_customer();
$pdo       = db();

// Load full customer record.
$cStmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
$cStmt->execute([':id' => $session['id']]);
$customer = $cStmt->fetch();
if (!$customer) {
    customer_logout();
    redirect(base_url('/login.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'subscription') {
    require_csrf();
    $subId = (int) input('subscription_id');
    $op    = input('op');
    $own   = $pdo->prepare('SELECT * FROM subscriptions WHERE id = :id AND customer_id = :cid LIMIT 1');
    $own->execute([':id' => $subId, ':cid' => $customer['id']]);
    $sub = $own->fetch();

    if (!$sub) {
        set_flash('error', 'Subscription not found.');
    } elseif ($op === 'pause' && $sub['status'] === 'active') {
        $pdo->prepare("UPDATE subscriptions SET status='paused' WHERE id=:id")->execute([':id' => $subId]);
        set_flash('success', 'Subscription paused. Resume anytime.');
    } elseif ($op === 'resume' && $sub['status'] === 'paused') {
        $next = subscription_next_date((string) $sub['frequency']);
        $pdo->prepare("UPDATE subscriptions SET status='active', next_renewal_date=:n WHERE id=:id")
            ->execute([':n' => $next, ':id' => $subId]);
        set_flash('success', 'Subscription resumed. Next refill on ' . date('d M Y', strtotime($next)) . '.');
    } elseif ($op === 'cancel' && $sub['status'] !== 'cancelled') {
        $pdo->prepare("UPDATE subscriptions SET status='cancelled' WHERE id=:id")->execute([':id' => $subId]);
        set_flash('info', 'Subscription cancelled.');
    } elseif ($op === 'frequency') {
        $freq = input('frequency');
        if (array_key_exists($freq, subscription_frequencies())) {
            $pdo->prepare("UPDATE subscriptions SET frequency=:f, next_renewal_date=:n WHERE id=:id")
                ->execute([':f' => $freq, ':n' => subscription_next_date($freq), ':id' => $subId]);
            set_flash('success', 'Delivery schedule updated.');
        }
    }
    redirect(base_url('/account.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name    = input('name');
    $phone   = input('phone');
    $address = input('address');
    if ($name !== '' && $phone !== '') {
        $pdo->prepare('UPDATE customers SET name = :n, phone = :p, address = :a WHERE id = :id')
            ->execute([':n' => $name, ':p' => $phone, ':a' => $address ?: null, ':id' => $customer['id']]);
        $_SESSION['customer']['name'] = $name;
        set_flash('success', 'Your details have been updated.');
    } else {
        set_flash('error', 'Name and phone are required.');
    }
    redirect(base_url('/account.php'));
}

// Orders linked by account id or by the account email (earlier guest orders).
$oStmt = $pdo->prepare(
    'SELECT * FROM orders
     WHERE customer_id = :id OR (email IS NOT NULL AND email = :email)
     ORDER BY created_at DESC'
);
$oStmt->execute([':id' => $customer['id'], ':email' => $customer['email']]);
$orders = $oStmt->fetchAll();

$subStmt = $pdo->prepare(
    "SELECT * FROM subscriptions
     WHERE customer_id = :id AND status <> 'cancelled'
     ORDER BY status ASC, next_renewal_date ASC"
);
$subStmt->execute([':id' => $customer['id']]);
$subscriptions = $subStmt->fetchAll();

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Welcome, <?= e($customer['name']) ?></span>
        <h2>My Account</h2>
        <p>Your comfort, all in one place.</p>
    </div>
</section>

<section>
    <div class="container">
        <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:30px;align-items:start">
            <div class="form-card" style="margin:0;max-width:none">
                <h3 style="margin-bottom:16px">Order history</h3>
                <?php if (!$orders): ?>
                    <p class="muted">No orders yet.
                        <a href="<?= base_url('/products.php?type=furniture') ?>" style="color:var(--terracotta)">Discover comfort &rarr;</a></p>
                <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Order #</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><strong><?= e($o['order_number']) ?></strong></td>
                                <td class="muted"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                                <td><?= money((float)$o['total_amount']) ?></td>
                                <td><span class="tag"><?= e(label($o['order_status'])) ?></span></td>
                                <td>
                                    <div class="actions-inline">
                                        <a class="btn btn-soft btn-sm" href="<?= base_url('/order_success.php?order=' . urlencode($o['order_number'])) ?>">View</a>
                                        <a class="btn btn-soft btn-sm" href="<?= base_url('/track.php?order=' . urlencode($o['order_number'])) ?>">Track</a>
                                        <a class="btn btn-soft btn-sm" href="<?= e(base_url('/document.php?order=' . urlencode($o['order_number']) . '&type=invoice&k=' . document_token($o['order_number']))) ?>" target="_blank" rel="noopener">Invoice</a>
                                        <?php if (!empty($o['receipt_number'])): ?>
                                            <a class="btn btn-soft btn-sm" href="<?= e(base_url('/document.php?order=' . urlencode($o['order_number']) . '&type=receipt&k=' . document_token($o['order_number']))) ?>" target="_blank" rel="noopener">Receipt</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <form class="form-card" method="post" style="margin:0;max-width:none">
                <?= csrf_field() ?>
                <h3 style="margin-bottom:16px">My details</h3>
                <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($customer['name']) ?>" required></div>
                <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" value="<?= e((string)$customer['phone']) ?>" required></div>
                <div class="field"><label>Email</label><input type="email" value="<?= e((string)$customer['email']) ?>" disabled></div>
                <div class="field"><label>Default delivery address</label><textarea name="address"><?= e((string)$customer['address']) ?></textarea></div>
                <button class="btn btn-primary btn-block" type="submit">Save details</button>
                <p class="muted" style="text-align:center;margin-top:14px">
                    <a href="<?= base_url('/logout.php') ?>">Sign out</a>
                </p>
            </form>
        </div>
    </div>
</section>
<?php if ($subscriptions || subscriptions_enabled()): ?>
<section style="padding-top:0">
    <div class="container">
        <div class="form-card" style="margin:0;max-width:none">
            <h3 style="margin-bottom:16px">Scent refill subscriptions</h3>
            <?php if (!$subscriptions): ?>
                <p class="muted">You have no active subscriptions.
                    <a href="<?= base_url('/products.php?type=essential_oil') ?>" style="color:var(--terracotta)">Subscribe &amp; save on your favourite scent &rarr;</a></p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Scent</th><th>Schedule</th><th>Per refill</th><th>Next refill</th><th>Status</th><th>Manage</th></tr></thead>
                    <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td><strong><?= e($sub['product_name']) ?></strong> &times; <?= (int)$sub['quantity'] ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px;align-items:center">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="subscription">
                                    <input type="hidden" name="op" value="frequency">
                                    <input type="hidden" name="subscription_id" value="<?= (int)$sub['id'] ?>">
                                    <select name="frequency" onchange="this.form.submit()">
                                        <?php foreach (subscription_frequencies() as $key => $f): ?>
                                            <option value="<?= e($key) ?>" <?= $sub['frequency']===$key?'selected':'' ?>><?= e($f['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button class="btn btn-soft btn-sm" type="submit">Set</button></noscript>
                                </form>
                            </td>
                            <td><?= money((float)$sub['unit_price']) ?></td>
                            <td class="muted"><?= $sub['next_renewal_date'] ? e(date('d M Y', strtotime($sub['next_renewal_date']))) : '—' ?></td>
                            <td><span class="tag"><?= e(label($sub['status'])) ?></span></td>
                            <td>
                                <div class="actions-inline">
                                    <?php if ($sub['status'] === 'active'): ?>
                                        <form method="post"><?= csrf_field() ?>
                                            <input type="hidden" name="action" value="subscription">
                                            <input type="hidden" name="op" value="pause">
                                            <input type="hidden" name="subscription_id" value="<?= (int)$sub['id'] ?>">
                                            <button class="btn btn-soft btn-sm" type="submit">Pause</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post"><?= csrf_field() ?>
                                            <input type="hidden" name="action" value="subscription">
                                            <input type="hidden" name="op" value="resume">
                                            <input type="hidden" name="subscription_id" value="<?= (int)$sub['id'] ?>">
                                            <button class="btn btn-soft btn-sm" type="submit">Resume</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" data-confirm="Cancel this subscription?"><?= csrf_field() ?>
                                        <input type="hidden" name="action" value="subscription">
                                        <input type="hidden" name="op" value="cancel">
                                        <input type="hidden" name="subscription_id" value="<?= (int)$sub['id'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Cancel</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
