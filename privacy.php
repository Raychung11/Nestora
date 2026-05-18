<?php
require_once __DIR__ . '/inc/functions.php';
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container" style="max-width:780px">
        <div class="section-head" style="margin-bottom:30px"><h2>Privacy Policy</h2></div>
        <p class="muted">Last updated: <?= date('F Y') ?></p>

        <h3 style="margin:24px 0 10px">1. Information we collect</h3>
        <p class="muted">We collect the details you share with us — such as your name, contact number, email and delivery address — when you place an order, complete the Comfort Quiz, or contact our team.</p>

        <h3 style="margin:24px 0 10px">2. How we use your information</h3>
        <p class="muted">Your information is used to confirm and fulfil your order, provide curated comfort recommendations, and follow up on your inquiries.</p>

        <h3 style="margin:24px 0 10px">3. Data protection</h3>
        <p class="muted">We take reasonable measures to protect your personal information and do not sell your data to third parties.</p>

        <h3 style="margin:24px 0 10px">4. Contact</h3>
        <p class="muted">For any privacy questions, contact us at <strong><?= e(get_setting('contact_email', 'hello@nestora.my')) ?></strong>.</p>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
