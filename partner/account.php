<?php
require_once __DIR__ . '/../inc/partner_auth.php';

$session = require_partner();
$pdo = db();
$row = $pdo->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
$row->execute([':id' => $session['id']]);
$partner = $row->fetch();
if (!$partner) { partner_logout(); redirect(base_url('/partner/login.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');

    if ($action === 'profile') {
        $pdo->prepare('UPDATE partners SET name=:n, business_name=:bn, phone=:p, address=:a WHERE id=:id')
            ->execute([
                ':n'  => input('name') ?: $partner['name'],
                ':bn' => input('business_name') ?: null,
                ':p'  => input('phone') ?: null,
                ':a'  => input('address') ?: null,
                ':id' => $session['id'],
            ]);
        $_SESSION['partner']['name'] = input('name') ?: $partner['name'];
        set_flash('success', 'Profile updated.');
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, (string) $partner['password_hash'])) {
            set_flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            set_flash('error', 'New password must be at least 8 characters.');
        } else {
            $pdo->prepare('UPDATE partners SET password_hash=:p WHERE id=:id')
                ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => $session['id']]);
            set_flash('success', 'Password updated.');
        }
    }
    redirect(base_url('/partner/account.php'));
}

$pageTitle = 'Partner Account';
require_once __DIR__ . '/../inc/header.php';
?>
<section class="band-soft" style="padding:46px 0 24px">
    <div class="container">
        <span class="eyebrow">Partner Portal</span>
        <h2 style="margin:4px 0 0">My account</h2>
        <p class="muted"><a href="<?= base_url('/partner/dashboard.php') ?>" style="color:var(--terracotta)">&larr; Back to dashboard</a></p>
    </div>
</section>
<section>
    <div class="container" style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start">
        <form class="form-card" method="post" style="margin:0;max-width:none">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <h3 style="margin-bottom:14px">My details</h3>
            <div class="form-row">
                <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($partner['name']) ?>" required></div>
                <div class="field"><label>Business name</label><input type="text" name="business_name" value="<?= e((string)$partner['business_name']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Email</label><input type="email" value="<?= e($partner['email']) ?>" disabled></div>
                <div class="field"><label>Phone</label><input type="text" name="phone" value="<?= e((string)$partner['phone']) ?>"></div>
            </div>
            <div class="field"><label>Delivery address</label><textarea name="address"><?= e((string)$partner['address']) ?></textarea></div>
            <button class="btn btn-primary btn-block" type="submit">Save</button>
        </form>

        <form class="form-card" method="post" style="margin:0;max-width:none">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <h3 style="margin-bottom:14px">Change password</h3>
            <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
            <div class="field"><label>New password (min 8)</label><input type="password" name="new_password" required></div>
            <button class="btn btn-soft btn-block" type="submit">Update password</button>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
