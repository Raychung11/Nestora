<?php
require_once __DIR__ . '/inc/functions.php';
$pageTitle = 'Terms & Conditions';
require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container" style="max-width:780px">
        <div class="section-head" style="margin-bottom:30px"><h2>Terms &amp; Conditions</h2></div>
        <p class="muted">Last updated: <?= date('F Y') ?></p>

        <h3 style="margin:24px 0 10px">1. Orders</h3>
        <p class="muted">All orders are subject to confirmation by the Nestora team. Delivery timeline will be confirmed by our Nestora team after order confirmation.</p>

        <h3 style="margin:24px 0 10px">2. Pricing</h3>
        <p class="muted">Prices are listed in Malaysian Ringgit (RM). Promotional prices apply for the stated period and selected items only.</p>

        <h3 style="margin:24px 0 10px">3. Monthly comfort plan</h3>
        <p class="muted">Installment plans of 6, 12 or 24 months are available on selected items and are subject to manual approval. Bring comfort home today, pay comfortably over time.</p>

        <h3 style="margin:24px 0 10px">4. Returns</h3>
        <p class="muted">Please contact our team regarding any concerns with your order so we can take care of you.</p>

        <h3 style="margin:24px 0 10px">5. Contact</h3>
        <p class="muted">For questions about these terms, contact <strong><?= e(get_setting('contact_email', 'hello@nestora.my')) ?></strong>.</p>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
