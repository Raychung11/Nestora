<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../inc/auth.php';
$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');

    if ($action === 'save_settings') {
        $keys = [
            'site_name','tagline','hero_headline','hero_subtext',
            'whatsapp_number','whatsapp_default_message','contact_email','contact_phone',
            'installment_public_text','delivery_public_text','ambassador_name','ambassador_text',
            'bank_name','bank_account_name','bank_account_number','payment_instructions',
        ];
        $up = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (:k,:v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($keys as $k) {
            $up->execute([':k' => $k, ':v' => input($k)]);
        }
        set_flash('success', 'Settings saved.');

    } elseif ($action === 'add_admin') {
        $name = input('admin_name');
        $email = input('admin_email');
        $pass  = (string) ($_POST['admin_password'] ?? '');
        $role  = in_array(input('admin_role'), ['admin','sales_admin','supplier_admin'], true) ? input('admin_role') : 'admin';
        if ($name && $email && strlen($pass) >= 8) {
            try {
                $pdo->prepare(
                    'INSERT INTO admin_users (name, email, password_hash, role, status)
                     VALUES (:n,:e,:p,:r,\'active\')'
                )->execute([':n'=>$name, ':e'=>$email, ':p'=>password_hash($pass, PASSWORD_DEFAULT), ':r'=>$role]);
                set_flash('success', 'Admin user added.');
            } catch (Throwable $e) {
                set_flash('error', 'Could not add admin (email may already exist).');
            }
        } else {
            set_flash('error', 'Name, email and a password of at least 8 characters are required.');
        }

    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $row = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id=:id');
        $row->execute([':id' => $admin['id']]);
        $hash = $row->fetchColumn();
        if ($hash && password_verify($current, (string)$hash) && strlen($new) >= 8) {
            $pdo->prepare('UPDATE admin_users SET password_hash=:p WHERE id=:id')
                ->execute([':p'=>password_hash($new, PASSWORD_DEFAULT), ':id'=>$admin['id']]);
            set_flash('success', 'Password updated.');
        } else {
            set_flash('error', 'Current password incorrect, or new password too short (min 8).');
        }
    }
    redirect(base_url('/admin/settings.php'));
}

$admins = $pdo->query("SELECT id, name, email, role, status, last_login_at FROM admin_users ORDER BY id")->fetchAll();
require_once __DIR__ . '/../inc/admin_layout.php';

$s = fn(string $k, string $d='') => get_setting($k, $d);
?>
<form class="panel" method="post" style="max-width:760px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <h2>Site &amp; homepage content</h2>
    <div class="form-row">
        <div class="field"><label>Site name</label><input type="text" name="site_name" value="<?= e((string)$s('site_name','NESTORA')) ?>"></div>
        <div class="field"><label>Tagline</label><input type="text" name="tagline" value="<?= e((string)$s('tagline')) ?>"></div>
    </div>
    <div class="field"><label>Hero headline</label><input type="text" name="hero_headline" value="<?= e((string)$s('hero_headline')) ?>"></div>
    <div class="field"><label>Hero subtext</label><textarea name="hero_subtext"><?= e((string)$s('hero_subtext')) ?></textarea></div>
    <div class="form-row">
        <div class="field"><label>WhatsApp number (digits only, with country code)</label><input type="text" name="whatsapp_number" value="<?= e((string)$s('whatsapp_number')) ?>"></div>
        <div class="field"><label>Default WhatsApp message</label><input type="text" name="whatsapp_default_message" value="<?= e((string)$s('whatsapp_default_message')) ?>"></div>
    </div>
    <div class="form-row">
        <div class="field"><label>Contact email</label><input type="text" name="contact_email" value="<?= e((string)$s('contact_email')) ?>"></div>
        <div class="field"><label>Contact phone</label><input type="text" name="contact_phone" value="<?= e((string)$s('contact_phone')) ?>"></div>
    </div>
    <div class="field"><label>Installment public text</label><input type="text" name="installment_public_text" value="<?= e((string)$s('installment_public_text')) ?>"></div>
    <div class="field"><label>Delivery public text</label><input type="text" name="delivery_public_text" value="<?= e((string)$s('delivery_public_text')) ?>"></div>
    <div class="form-row">
        <div class="field"><label>Ambassador name</label><input type="text" name="ambassador_name" value="<?= e((string)$s('ambassador_name')) ?>"></div>
        <div class="field"><label>Ambassador text</label><input type="text" name="ambassador_text" value="<?= e((string)$s('ambassador_text')) ?>"></div>
    </div>

    <h3 style="margin:22px 0 12px">Bank &amp; manual payment</h3>
    <div class="form-row">
        <div class="field"><label>Bank name</label><input type="text" name="bank_name" value="<?= e((string)$s('bank_name')) ?>"></div>
        <div class="field"><label>Account name</label><input type="text" name="bank_account_name" value="<?= e((string)$s('bank_account_name')) ?>"></div>
    </div>
    <div class="field"><label>Account number</label><input type="text" name="bank_account_number" value="<?= e((string)$s('bank_account_number')) ?>"></div>
    <div class="field"><label>Payment instructions</label><textarea name="payment_instructions"><?= e((string)$s('payment_instructions')) ?></textarea></div>

    <button class="btn btn-primary btn-block" type="submit">Save settings</button>
</form>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">
    <div class="panel">
        <h2>Admin users</h2>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td><strong><?= e($a['name']) ?></strong></td>
                    <td class="muted"><?= e($a['email']) ?></td>
                    <td><?= e(label($a['role'])) ?></td>
                    <td class="muted"><?= $a['last_login_at'] ? e(date('d M Y H:i', strtotime($a['last_login_at']))) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" class="mt">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_admin">
            <h3 style="margin-bottom:12px">Add admin user</h3>
            <div class="field"><label>Name</label><input type="text" name="admin_name" required></div>
            <div class="field"><label>Email</label><input type="email" name="admin_email" required></div>
            <div class="field"><label>Password (min 8)</label><input type="password" name="admin_password" required></div>
            <div class="field">
                <label>Role</label>
                <select name="admin_role">
                    <option value="admin">Admin</option>
                    <option value="sales_admin">Sales Admin</option>
                    <option value="supplier_admin">Supplier Admin</option>
                </select>
            </div>
            <button class="btn btn-soft btn-block" type="submit">Add admin</button>
        </form>
    </div>

    <form class="panel" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <h2>Change my password</h2>
        <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
        <div class="field"><label>New password (min 8)</label><input type="password" name="new_password" required></div>
        <button class="btn btn-primary btn-block" type="submit">Update password</button>
    </form>
</div>
<?php admin_layout_end(); ?>
