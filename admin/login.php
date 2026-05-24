<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/security.php';

if (current_admin()) {
    redirect(base_url('/admin/dashboard.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$allowed, $wait] = login_throttle_check('admin');
    if (!$allowed) {
        $error = login_lock_message($wait);
    } else {
        $email = input('email');
        $pass  = $_POST['password'] ?? '';
        if (admin_login($email, (string) $pass)) {
            login_record_success('admin');
            set_flash('success', 'Welcome back.');
            redirect(base_url('/admin/dashboard.php'));
        }
        login_record_failure('admin');
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nestora Admin &middot; Sign in</title>
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body class="admin-body">
<div class="admin-login-wrap">
    <form class="admin-login-card" method="post">
        <?= csrf_field() ?>
        <span class="brand-mark">NESTORA</span>
        <div class="sub">Admin Console</div>
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
    </form>
</div>
</body>
</html>
