<?php
require_once __DIR__ . '/inc/customer_auth.php';

$pageTitle = 'Create your Nestora account';

if (current_customer()) {
    redirect(base_url('/account.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name    = input('name');
    $phone   = input('phone');
    $email   = input('email');
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($name === '')  { $errors[] = 'Please enter your name.'; }
    if ($phone === '') { $errors[] = 'Please enter a contact number.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email address.'; }
    if (strlen($pass) < 8) { $errors[] = 'Password must be at least 8 characters.'; }
    if ($pass !== $confirm) { $errors[] = 'Passwords do not match.'; }

    if (!$errors) {
        [$ok, $msg] = customer_register($name, $phone, $email, $pass);
        if ($ok) {
            set_flash('success', $msg);
            redirect(base_url('/account.php'));
        }
        $errors[] = $msg;
    }
}

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Join Nestora</span>
        <h2>Create your account</h2>
        <p>Track your orders and bring comfort home, comfortably.</p>
    </div>
</section>
<section>
    <div class="container">
        <form class="form-card" method="post" style="max-width:520px">
            <?= csrf_field() ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error"><?= e($err) ?></div>
            <?php endforeach; ?>
            <div class="field"><label>Full name</label><input type="text" name="name" value="<?= e(input('name')) ?>" required></div>
            <div class="form-row">
                <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" value="<?= e(input('phone')) ?>" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?= e(input('email')) ?>" required></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Password</label><input type="password" name="password" required></div>
                <div class="field"><label>Confirm password</label><input type="password" name="password_confirm" required></div>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button>
            <p class="muted" style="text-align:center;margin-top:16px">
                Already have an account? <a href="<?= base_url('/login.php') ?>" style="color:var(--terracotta)">Sign in</a>
            </p>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
