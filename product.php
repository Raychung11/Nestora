<?php
require_once __DIR__ . '/inc/functions.php';

$slug = input('slug');
$stmt = db()->prepare("SELECT * FROM products WHERE slug = :s AND status='active' LIMIT 1");
$stmt->execute([':s' => $slug]);
$p = $stmt->fetch();

if (!$p) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/inc/header.php';
    echo '<section class="container"><div class="section-head"><h2>Product not found</h2>'
       . '<p class="muted">This piece may have moved. <a href="' . base_url('/products.php') . '">Browse the collection</a>.</p></div></section>';
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$imgStmt = db()->prepare(
    "SELECT * FROM product_images WHERE product_id = :pid ORDER BY is_primary DESC, sort_order ASC, id ASC"
);
$imgStmt->execute([':pid' => $p['id']]);
$images = $imgStmt->fetchAll();
$heroImg = product_image_url($images[0]['file_path'] ?? null);

$eff       = effective_price($p);
$isOil     = $p['product_type'] === 'essential_oil' || $p['product_type'] === 'diffuser';
$pageTitle = $p['name'];
$pageDesc  = $p['short_description'] ?? '';

$waMsg = 'Hi Nestora, I am interested in "' . $p['name'] . '" (' . $p['sku'] . '). Could you help me?';

require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container">
        <div class="pd-wrap">
            <div>
                <div class="pd-gallery"><img src="<?= e($heroImg) ?>" alt="<?= e($p['name']) ?>"></div>
            </div>
            <div class="pd-info">
                <?php if (!empty($p['feeling_tags'])): ?>
                    <div class="pd-feel"><?= e(str_replace(',', ' &middot; ', $p['feeling_tags'])) ?></div>
                <?php endif; ?>
                <h1><?= e($p['name']) ?></h1>
                <p class="muted"><?= e($p['short_description']) ?></p>

                <div class="pd-price">
                    <span class="now"><?= money($eff) ?></span>
                    <?php if ($eff < (float)$p['price']): ?><span class="was"><?= money((float)$p['price']) ?></span><?php endif; ?>
                </div>

                <div>
                    <span class="badge badge-<?= e($p['stock_status']) ?>">
                        <?php
                        echo match ($p['stock_status']) {
                            'available'   => 'Available now',
                            'preorder'    => 'Available on pre-order',
                            'checking'    => 'Checking availability',
                            default       => 'Currently unavailable',
                        };
                        ?>
                    </span>
                </div>

                <?php if (!empty($p['installment_eligible'])):
                    $m = (int)$p['max_installment_months'];
                    $mp = monthly_payment($eff, $m); ?>
                    <div class="pd-inst">
                        <strong>From <?= money($mp) ?>/month for <?= $m ?> months.</strong><br>
                        <span class="muted"><?= e(get_setting('installment_public_text', 'Bring comfort home today, pay comfortably over time.')) ?></span><br>
                        <a href="<?= base_url('/installment_apply.php?product=' . urlencode($p['slug'])) ?>" style="color:var(--terracotta);font-size:.9rem">Apply for a monthly comfort plan &rarr;</a>
                    </div>
                <?php endif; ?>

                <?php if ($isOil): ?>
                    <!-- Essential Oil details -->
                    <dl class="pd-meta">
                        <?php if ($p['scent_mood']): ?><div><dt>Scent mood</dt><dd><?= e($p['scent_mood']) ?></dd></div><?php endif; ?>
                        <?php if ($p['scent_notes']): ?><div><dt>Scent notes</dt><dd><?= e($p['scent_notes']) ?></dd></div><?php endif; ?>
                        <?php if ($p['scent_profile']): ?><div><dt>Scent profile</dt><dd><?= e($p['scent_profile']) ?></dd></div><?php endif; ?>
                        <?php if ($p['best_room_usage']): ?><div><dt>Best room usage</dt><dd><?= e($p['best_room_usage']) ?></dd></div><?php endif; ?>
                        <?php if ($p['feeling_tags']): ?><div><dt>Emotional feeling</dt><dd><?= e(label(str_replace(',', ', ', $p['feeling_tags']))) ?></dd></div><?php endif; ?>
                        <?php if ($p['bottle_size']): ?><div><dt>Bottle size</dt><dd><?= e($p['bottle_size']) ?></dd></div><?php endif; ?>
                    </dl>
                <?php else: ?>
                    <!-- Furniture details -->
                    <dl class="pd-meta">
                        <?php if ($p['material']): ?><div><dt>Material</dt><dd><?= e($p['material']) ?></dd></div><?php endif; ?>
                        <?php if ($p['dimensions']): ?><div><dt>Size</dt><dd><?= e($p['dimensions']) ?></dd></div><?php endif; ?>
                        <div><dt>Estimated delivery</dt><dd><?= e($p['delivery_note'] ?: get_setting('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.')) ?></dd></div>
                    </dl>
                <?php endif; ?>

                <div class="pd-actions">
                    <form method="post" action="<?= base_url('/cart.php') ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-primary btn-lg" type="submit">Add to cart</button>
                    </form>
                    <a class="btn btn-soft btn-lg" href="<?= whatsapp_url($waMsg) ?>" target="_blank" rel="noopener">WhatsApp inquiry</a>
                </div>
            </div>
        </div>

        <?php if (!empty($p['long_description'])): ?>
            <div class="pd-section">
                <h3>An emotional comfort note</h3>
                <p class="muted"><?= nl2br(e($p['long_description'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($isOil && $p['usage_instructions']): ?>
            <div class="pd-section">
                <h3>Usage instructions</h3>
                <p class="muted"><?= nl2br(e($p['usage_instructions'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($isOil && $p['safety_disclaimer']): ?>
            <div class="disclaimer"><strong>Safety:</strong> <?= e($p['safety_disclaimer']) ?></div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
