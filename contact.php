<?php
require_once __DIR__ . '/inc/functions.php';
$pageTitle = 'Contact Nestora';
$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name    = input('name');
    $phone   = input('phone');
    $area    = input('delivery_area');
    $message = input('message');

    if ($name === '')  { $errors[] = 'Please enter your name.'; }
    if ($phone === '') { $errors[] = 'Please enter a contact number.'; }

    if (!$errors) {
        $ins = db()->prepare(
            "INSERT INTO whatsapp_leads (name, phone, delivery_area, message, source, status)
             VALUES (:n,:p,:a,:m,'contact_form','new')"
        );
        $ins->execute([':n' => $name, ':p' => $phone, ':a' => $area ?: null, ':m' => $message ?: null]);
        $sent = true;
    }
}
require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">We&rsquo;re here for you</span>
        <h2>Contact Nestora</h2>
        <p>Talk to our Nestora AI Comfort Advisor, or leave your details and we&rsquo;ll reach out warmly.</p>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($sent): ?>
            <div class="form-card" style="text-align:center">
                <h2 style="margin-bottom:12px">Thank you</h2>
                <p class="muted">We&rsquo;ve received your message and will be in touch soon.</p>
                <a class="btn btn-primary btn-lg mt" href="<?= whatsapp_url() ?>" target="_blank" rel="noopener">Chat now on WhatsApp</a>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:start">
                <form class="form-card" method="post" style="margin:0;max-width:none">
                    <?= csrf_field() ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="flash flash-error"><?= e($err) ?></div>
                    <?php endforeach; ?>
                    <div class="field"><label>Your name</label><input type="text" name="name" value="<?= e(input('name')) ?>" required></div>
                    <div class="form-row">
                        <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" value="<?= e(input('phone')) ?>" required></div>
                        <div class="field"><label>Delivery area</label><input type="text" name="delivery_area" value="<?= e(input('delivery_area')) ?>"></div>
                    </div>
                    <div class="field"><label>How can we help?</label><textarea name="message"><?= e(input('message')) ?></textarea></div>
                    <button class="btn btn-primary btn-lg btn-block" type="submit">Send message</button>
                </form>
                <div class="form-card" style="margin:0;max-width:none">
                    <h3 style="margin-bottom:14px">Reach us directly</h3>
                    <p class="muted">Email<br><strong><?= e(get_setting('contact_email', 'hello@nestora.my')) ?></strong></p>
                    <p class="muted" style="margin-top:14px">Phone<br><strong><?= e(get_setting('contact_phone', '+60 12-345 6789')) ?></strong></p>
                    <a class="btn btn-soft btn-lg mt" href="<?= whatsapp_url() ?>" target="_blank" rel="noopener">WhatsApp the Comfort Advisor</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
