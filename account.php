<?php
require_once __DIR__ . '/inc/customer_auth.php';
require_once __DIR__ . '/inc/documents.php';

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
<?php require_once __DIR__ . '/inc/footer.php'; ?>
