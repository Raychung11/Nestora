<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/mailer.php';
require_once __DIR__ . '/inc/partners.php';

$pageTitle = 'Partnership Program';
$pageDesc  = 'Become a Nestora Starter or Elite Partner — share comfort, earn together.';

$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!partner_program_enabled()) {
        set_flash('info', 'Partnership applications are currently closed.');
        redirect(base_url('/partnership.php'));
    }
    $name     = input('name');
    $business = input('business_name');
    $email    = input('email');
    $phone    = input('phone');
    $message  = input('message');
    $tier     = in_array(input('requested_tier'), ['starter', 'elite'], true) ? input('requested_tier') : 'starter';

    if ($name === '')                                                   { $errors[] = 'Please enter your name.'; }
    if ($phone === '')                                                  { $errors[] = 'Please enter a contact number.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))    { $errors[] = 'Please enter a valid email.'; }

    if (!$errors) {
        try {
            db()->prepare(
                'INSERT INTO partner_applications
                 (name, business_name, email, phone, message, requested_tier, status)
                 VALUES (:n,:bn,:e,:p,:m,:t,\'new\')'
            )->execute([
                ':n' => $name, ':bn' => $business ?: null, ':e' => $email,
                ':p' => $phone, ':m' => $message ?: null, ':t' => $tier,
            ]);
            $summary = '<p><strong>' . e($name) . '</strong>'
                . ($business ? ' (' . e($business) . ')' : '')
                . '<br>' . e($email) . ' &middot; ' . e($phone)
                . '<br>Tier requested: <strong>' . e(label($tier)) . '</strong></p>'
                . ($message ? '<p>' . nl2br(e($message)) . '</p>' : '');
            notify_admin('New partnership application',
                mail_template('Partnership application received', $summary
                    . '<p>Review and approve in Admin &rarr; Partner Applications.</p>'), $email);
            send_mail($email, 'We have received your Nestora partnership application',
                mail_template('Thank you for applying',
                    '<p>Hi ' . e($name) . ', thank you for your interest in the Nestora Partnership.</p>'
                    . '<p>Our team will review your application and reach out shortly.</p>'
                    . $summary));
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = 'We could not submit your application right now. Please try again.';
        }
    }
}

$starter = partner_tier_defaults('starter');
$elite   = partner_tier_defaults('elite');
$intro   = (string) get_setting('partner_program_public_text',
    'Grow with Nestora. Earn from every comfort home you inspire — Starter or Elite, your tier, your terms.');

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:60px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">NESTORA &middot; Partnership</span>
        <h2>Become a Partner</h2>
        <p><?= e($intro) ?></p>
    </div>
</section>

<section>
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:36px">
            <div class="form-card" style="margin:0;max-width:none">
                <span class="eyebrow">Tier 1</span>
                <h3 style="margin:8px 0 16px">Starter Partner</h3>
                <p class="muted">Begin sharing the Nestora comfort and earn on every referred order.</p>
                <ul style="margin:14px 0 0;padding-left:18px;color:var(--brown);line-height:1.85">
                    <li><strong><?= e((string)$starter['commission']) ?>%</strong> commission on referred sales</li>
                    <li><strong><?= e((string)$starter['wholesale']) ?>%</strong> off retail for your own stock orders</li>
                    <li>Personal referral code and link</li>
                    <li>Partner portal dashboard</li>
                </ul>
            </div>
            <div class="form-card" style="margin:0;max-width:none;border-color:var(--terracotta)">
                <span class="eyebrow" style="color:var(--terracotta)">Tier 2</span>
                <h3 style="margin:8px 0 16px">Elite Partner</h3>
                <p class="muted">For dedicated partners ready to grow with us — higher earnings, deeper benefits.</p>
                <ul style="margin:14px 0 0;padding-left:18px;color:var(--brown);line-height:1.85">
                    <li><strong><?= e((string)$elite['commission']) ?>%</strong> commission on referred sales</li>
                    <li><strong><?= e((string)$elite['wholesale']) ?>%</strong> off retail for your own stock orders</li>
                    <li>Priority Nestora support</li>
                    <li>Featured placement opportunities</li>
                </ul>
            </div>
        </div>

        <?php if ($done): ?>
            <div class="form-card" style="text-align:center;max-width:560px">
                <h3>Application received</h3>
                <p class="muted">Thank you. Our Nestora team will review your application and reach out within a few working days.</p>
                <a class="btn btn-primary btn-lg" href="<?= base_url('/index.php') ?>">Back to home</a>
            </div>
        <?php else: ?>
            <?php if (!partner_program_enabled()): ?>
                <div class="flash flash-info">Partnership applications are currently closed. Please check back soon.</div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
            <form class="form-card" method="post" style="max-width:560px">
                <?= csrf_field() ?>
                <h3 style="margin-bottom:14px">Apply to join</h3>
                <div class="form-row">
                    <div class="field"><label>Full name *</label><input type="text" name="name" value="<?= e(input('name')) ?>" required></div>
                    <div class="field"><label>Business name (optional)</label><input type="text" name="business_name" value="<?= e(input('business_name')) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>Email *</label><input type="email" name="email" value="<?= e(input('email')) ?>" required></div>
                    <div class="field"><label>Phone / WhatsApp *</label><input type="text" name="phone" value="<?= e(input('phone')) ?>" required></div>
                </div>
                <div class="field">
                    <label>Tier you are applying for</label>
                    <select name="requested_tier">
                        <option value="starter" <?= input('requested_tier','starter')==='starter'?'selected':'' ?>>Starter Partner</option>
                        <option value="elite"   <?= input('requested_tier','starter')==='elite'?'selected':'' ?>>Elite Partner</option>
                    </select>
                </div>
                <div class="field"><label>Tell us a little about you (optional)</label><textarea name="message"><?= e(input('message')) ?></textarea></div>
                <button class="btn btn-primary btn-lg btn-block" type="submit" <?= partner_program_enabled() ? '' : 'disabled' ?>>Submit application</button>
                <p class="muted" style="text-align:center;margin-top:14px;font-size:.86rem">Already a partner? <a href="<?= base_url('/partner/login.php') ?>" style="color:var(--terracotta)">Sign in to your portal</a></p>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
