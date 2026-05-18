<?php
require_once __DIR__ . '/inc/functions.php';

$pageTitle = get_setting('site_name', 'NESTORA');
$pageDesc  = get_setting('hero_subtext', 'A Home That Takes Care of You');

$featuredFurniture = db()->query(
    "SELECT * FROM products
     WHERE status='active' AND product_type='furniture' AND is_featured=1
     ORDER BY updated_at DESC LIMIT 3"
)->fetchAll();

$featuredOils = db()->query(
    "SELECT * FROM products
     WHERE status='active' AND product_type='essential_oil' AND is_featured=1
     ORDER BY updated_at DESC LIMIT 3"
)->fetchAll();

$testimonials = db()->query(
    "SELECT * FROM testimonials WHERE status='active' ORDER BY sort_order ASC, id ASC LIMIT 3"
)->fetchAll();

require_once __DIR__ . '/inc/header.php';

function product_card(array $p): void {
    $img = product_image_url(product_primary_image((int)$p['id']));
    $eff = effective_price($p);
    ?>
    <a class="product-card" href="<?= base_url('/product.php?slug=' . urlencode($p['slug'])) ?>">
        <div class="pc-img"><img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div>
        <div class="pc-body">
            <?php if (!empty($p['feeling_tags'])): ?>
                <span class="pc-feel"><?= e(str_replace(',', ' &middot; ', $p['feeling_tags'])) ?></span>
            <?php endif; ?>
            <h3><?= e($p['name']) ?></h3>
            <p class="pc-desc"><?= e($p['short_description']) ?></p>
            <div class="pc-price">
                <span class="now"><?= money($eff) ?></span>
                <?php if ($eff < (float)$p['price']): ?><span class="was"><?= money((float)$p['price']) ?></span><?php endif; ?>
            </div>
            <?php if ($lbl = installment_label($p)): ?>
                <span class="pc-inst"><?= e($lbl) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php
}
?>

<!-- 1. Hero -->
<section class="hero">
    <div class="container hero-inner">
        <span class="eyebrow"><?= e(get_setting('tagline', 'A Home That Takes Care of You')) ?></span>
        <h1><?= e(get_setting('hero_headline', 'More Than A Home. A Feeling.')) ?></h1>
        <p><?= e(get_setting('hero_subtext', 'Designed for emotional comfort.')) ?></p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="<?= base_url('/comfort_quiz.php') ?>">Discover Your Home Feeling</a>
            <a class="btn btn-soft btn-lg" href="<?= base_url('/products.php?type=furniture') ?>">Shop Furniture</a>
        </div>
    </div>
</section>

<!-- 2. Product ecosystem -->
<section>
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">The Nestora Ecosystem</span>
            <h2>One brand for the whole feeling of home</h2>
            <p>Thoughtfully selected pieces and signature scents — with more comfort to come.</p>
        </div>
        <div class="eco-grid">
            <div class="eco-card"><span>01</span><h3>Nestora Living</h3><span>Furniture</span></div>
            <div class="eco-card"><span>02</span><h3>Nestora Scent</h3><span>Home Aroma</span></div>
            <div class="eco-card soon"><span>03</span><h3>Nestora Air</h3><span>Coming Soon</span></div>
            <div class="eco-card soon"><span>04</span><h3>Nestora Care</h3><span>Coming Soon</span></div>
            <div class="eco-card"><span>05</span><h3>Nestora AI</h3><span>Comfort Advisor</span></div>
        </div>
    </div>
</section>

<!-- 3. Featured furniture -->
<section class="band-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Nestora Living</span>
            <h2>Calming furniture, curated for comfort</h2>
        </div>
        <?php if ($featuredFurniture): ?>
            <div class="product-grid">
                <?php foreach ($featuredFurniture as $p) product_card($p); ?>
            </div>
        <?php else: ?>
            <p class="muted" style="text-align:center">New comfort pieces are being curated. Please check back soon.</p>
        <?php endif; ?>
        <div style="text-align:center;margin-top:34px">
            <a class="btn btn-ghost" href="<?= base_url('/products.php?type=furniture') ?>">View all furniture</a>
        </div>
    </div>
</section>

<!-- 4. Featured essential oils -->
<section>
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Nestora Scent</span>
            <h2>Signature scents for scent memory</h2>
            <p>A home that smells like comfort — clean, calm and warm.</p>
        </div>
        <?php if ($featuredOils): ?>
            <div class="product-grid">
                <?php foreach ($featuredOils as $p) product_card($p); ?>
            </div>
        <?php else: ?>
            <p class="muted" style="text-align:center">Our launch scent collection is arriving soon.</p>
        <?php endif; ?>
        <div style="text-align:center;margin-top:34px">
            <a class="btn btn-ghost" href="<?= base_url('/products.php?type=essential_oil') ?>">View all scents</a>
        </div>
    </div>
</section>

<!-- 5. Comfort quiz CTA -->
<section class="band-soft">
    <div class="container">
        <div class="quiz-cta">
            <div>
                <h2>Not sure where to start?</h2>
                <p>Take the Comfort Quiz and let our Nestora AI Comfort Advisor curate a home feeling just for you.</p>
            </div>
            <a class="btn btn-primary btn-lg" href="<?= base_url('/comfort_quiz.php') ?>">Start the Comfort Quiz</a>
        </div>
    </div>
</section>

<!-- 6. Installment highlight -->
<section class="band">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="color:#e9c4ad">Monthly Comfort Plan</span>
            <h2>Up to 24 months installment</h2>
            <p><?= e(get_setting('installment_public_text', 'Bring comfort home today, pay comfortably over time.')) ?></p>
        </div>
        <div style="text-align:center">
            <a class="btn btn-soft btn-lg" href="<?= base_url('/installment.php') ?>">See how it works</a>
        </div>
    </div>
</section>

<!-- 7. Brand ambassador -->
<section>
    <div class="container ambassador">
        <div class="a-photo"></div>
        <div class="a-body">
            <span class="eyebrow" style="color:var(--terracotta);letter-spacing:.22em;text-transform:uppercase;font-size:.72rem">Brand Ambassador</span>
            <h2 style="margin:12px 0"><?= e(get_setting('ambassador_name', 'Nestora Comfort Ambassador')) ?></h2>
            <p class="muted"><?= e(get_setting('ambassador_text', 'Curated with people who believe a home should feel like care.')) ?></p>
        </div>
    </div>
</section>

<!-- 8. Customer trust -->
<section class="band-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Loved at Home</span>
            <h2>Comfort, in their words</h2>
        </div>
        <div class="testi-grid">
            <?php foreach ($testimonials as $t): ?>
                <div class="testi">
                    <div class="stars"><?= str_repeat('&#9733;', max(1, min(5, (int)$t['rating']))) ?></div>
                    <p>&ldquo;<?= e($t['message']) ?>&rdquo;</p>
                    <div class="who"><?= e($t['customer_name']) ?></div>
                    <div class="where"><?= e($t['location']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 9. WhatsApp AI assistant CTA -->
<section>
    <div class="container">
        <div class="quiz-cta">
            <div>
                <h2>Meet your Nestora AI Comfort Advisor</h2>
                <p>Warm, calm and helpful. Tell us how you want your home to feel and we&rsquo;ll guide you to the right comfort.</p>
            </div>
            <a class="btn btn-primary btn-lg" href="<?= whatsapp_url() ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
