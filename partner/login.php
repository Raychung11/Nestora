<?php
require_once __DIR__ . '/../inc/partner_auth.php';
require_once __DIR__ . '/../inc/security.php';

if (current_partner()) {
    redirect(base_url('/partner/dashboard.php'));
}

$pageTitle = 'Partner Sign in';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$allowed, $wait] = login_throttle_check('partner');
    if (!$allowed) {
        $error = login_lock_message($wait);
    } else {
        if (partner_login(input('email'), (string) ($_POST['password'] ?? ''))) {
            login_record_success('partner');
            set_flash('success', 'Welcome back.');
            redirect(base_url('/partner/dashboard.php'));
        }
        login_record_failure('partner');
        $error = 'Invalid email or password.';
    }
}
require_once __DIR__ . '/../inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Nestora Partner Portal</span>
        <h2>Sign in to your Partner Portal</h2>
    </div>
</section>
<section>
    <div class="container">
        <form class="form-card" method="post" style="max-width:460px">
            <?= csrf_field() ?>
            <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
            <div class="field"><label>Email</label><input type="email" name="email" value="<?= e(input('email')) ?>" required autofocus></div>
            <div class="field"><label>Password</label><input type="password" name="password" required></div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
            <p class="muted" style="text-align:center;margin-top:16px">
                Want to join? <a href="<?= base_url('/partnership.php') ?>" style="color:var(--terracotta)">Apply to become a Nestora Partner</a>
            </p>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
