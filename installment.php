<?php
require_once __DIR__ . '/inc/functions.php';
$pageTitle = 'Monthly Comfort Plan';

$eligible = db()->query(
    "SELECT * FROM products
     WHERE status='active' AND installment_eligible=1
     ORDER BY is_featured DESC, price DESC LIMIT 6"
)->fetchAll();

require_once __DIR__ . '/inc/header.php';
?>
<section class="band">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow" style="color:#e9c4ad">Monthly Comfort Plan</span>
        <h2>Bring comfort home today, pay comfortably over time</h2>
        <p><?= e(get_setting('installment_public_text', 'Bring comfort home today, pay comfortably over time.')) ?></p>
    </div>
</section>

<section>
    <div class="container" style="max-width:860px">
        <div class="section-head"><h2>How it works</h2></div>
        <div class="eco-grid" style="grid-template-columns:repeat(3,1fr)">
            <div class="eco-card"><span>01</span><h3>Choose comfort</h3><span>Pick your pieces</span></div>
            <div class="eco-card"><span>02</span><h3>Select a plan</h3><span>6 / 12 / 24 months</span></div>
            <div class="eco-card"><span>03</span><h3>Enjoy at home</h3><span>Pay monthly</span></div>
        </div>

        <div class="pd-inst" style="margin-top:34px">
            <strong>Simple monthly calculation</strong><br>
            <span class="muted">Monthly payment = product price &divide; number of months. Available on selected items, with manual approval by our Nestora team.</span>
        </div>

        <?php if ($eligible): ?>
            <div class="section-head mt"><h2>Available on a monthly comfort plan</h2></div>
            <div class="product-grid">
                <?php foreach ($eligible as $p):
                    $img = product_image_url(product_primary_image((int)$p['id']));
                    $m = (int)$p['max_installment_months'];
                    $mp = monthly_payment(effective_price($p), $m); ?>
                    <a class="product-card" href="<?= base_url('/product.php?slug=' . urlencode($p['slug'])) ?>">
                        <div class="pc-img"><img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div>
                        <div class="pc-body">
                            <h3><?= e($p['name']) ?></h3>
                            <div class="pc-price"><span class="now"><?= money(effective_price($p)) ?></span></div>
                            <span class="pc-inst">From <?= money($mp) ?>/month for <?= $m ?> months</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:36px">
            <a class="btn btn-primary btn-lg" href="<?= whatsapp_url('Hi Nestora, I would like to know more about the monthly comfort plan.') ?>" target="_blank" rel="noopener">Ask about a plan</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
