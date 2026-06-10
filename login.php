<?php
require_once __DIR__ . '/inc/customer_auth.php';
require_once __DIR__ . '/inc/security.php';

$pageTitle = 'Sign in';

if (current_customer()) {
    redirect(base_url('/account.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$allowed, $wait] = login_throttle_check('customer');
    if (!$allowed) {
        $error = login_lock_message($wait);
    } else {
        $email = input('email');
        $pass  = (string) ($_POST['password'] ?? '');

        if (customer_login($email, $pass)) {
            login_record_success('customer');
            set_flash('success', 'Welcome back.');
            redirect(base_url('/account.php'));
        }
        login_record_failure('customer');
        $error = 'Invalid email or password.';
    }
}

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Welcome back</span>
        <h2>Sign in to Nestora</h2>
        <p>A home that takes care of you.</p>
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
                New to Nestora? <a href="<?= base_url('/register.php') ?>" style="color:var(--terracotta)">Create an account</a>
            </p>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
