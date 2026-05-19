<?php
require_once __DIR__ . '/inc/functions.php';

$type = input('type', 'furniture');
$validTypes = ['furniture', 'essential_oil', 'diffuser', 'bundle'];
if (!in_array($type, $validTypes, true)) {
    $type = 'furniture';
}

$titles = [
    'furniture'     => 'Nestora Living — Furniture',
    'essential_oil' => 'Nestora Scent — Essential Oils',
    'diffuser'      => 'Nestora Scent — Diffusers',
    'bundle'        => 'Comfort Bundles',
];
$pageTitle = $titles[$type];

$stmt = db()->prepare(
    "SELECT * FROM products
     WHERE status='active' AND product_type = :t
     ORDER BY is_featured DESC, updated_at DESC"
);
$stmt->execute([':t' => $type]);
$products = $stmt->fetchAll();

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:60px 0 36px">
    <div class="container">
        <div class="section-head" style="margin-bottom:0">
            <span class="eyebrow"><?= $type === 'essential_oil' ? 'Nestora Scent' : ($type === 'furniture' ? 'Nestora Living' : 'Nestora') ?></span>
            <h2><?= e($pageTitle) ?></h2>
            <p>Thoughtfully selected for comfort living. Curated for how you want your home to feel.</p>
        </div>
        <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:24px">
            <a class="btn btn-sm <?= $type==='furniture'?'btn-primary':'btn-soft' ?>" href="<?= base_url('/products.php?type=furniture') ?>">Furniture</a>
            <a class="btn btn-sm <?= $type==='essential_oil'?'btn-primary':'btn-soft' ?>" href="<?= base_url('/products.php?type=essential_oil') ?>">Essential Oils</a>
            <a class="btn btn-sm <?= $type==='bundle'?'btn-primary':'btn-soft' ?>" href="<?= base_url('/products.php?type=bundle') ?>">Comfort Bundles</a>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <?php if (!$products): ?>
            <p class="muted" style="text-align:center">This collection is being curated. Please check back soon, or
                <a href="<?= whatsapp_url() ?>" target="_blank" rel="noopener" style="color:var(--terracotta)">chat with us</a>.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $p):
                    $img = product_image_url(product_primary_image((int)$p['id']));
                    $eff = effective_price($p);
                    $was = reference_price($p); ?>
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
                                <?php if ($was > $eff): ?><span class="was"><?= money($was) ?></span><?php endif; ?>
                            </div>
                            <?php if ($lbl = installment_label($p)): ?>
                                <span class="pc-inst"><?= e($lbl) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
