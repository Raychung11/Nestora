<?php
require_once __DIR__ . '/../inc/partner_auth.php';
require_once __DIR__ . '/../inc/partners.php';

$session = require_partner();
$partner = current_partner_row();
if (!$partner) { partner_logout(); redirect(base_url('/partner/login.php')); }

$type = input('type', 'furniture');
if (!in_array($type, ['furniture','essential_oil','diffuser','bundle'], true)) {
    $type = 'furniture';
}

$stmt = db()->prepare(
    "SELECT * FROM products WHERE status='active' AND product_type = :t ORDER BY name"
);
$stmt->execute([':t' => $type]);
$products = $stmt->fetchAll();

$rate = (float) $partner['wholesale_discount_rate'];
$pageTitle = 'Wholesale Shop';
require_once __DIR__ . '/../inc/header.php';
?>
<section class="band-soft" style="padding:46px 0 18px">
    <div class="container">
        <span class="eyebrow">Partner Portal &middot; Wholesale</span>
        <h2 style="margin:4px 0 6px">Wholesale shop</h2>
        <p class="muted">Your wholesale discount: <strong><?= e((string)$rate) ?>% off retail</strong>.
            <a href="<?= base_url('/partner/dashboard.php') ?>" style="color:var(--terracotta)">&larr; Dashboard</a>
            &middot; <a href="<?= base_url('/partner/cart.php') ?>" style="color:var(--terracotta)">View wholesale cart &rarr;</a></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
            <?php foreach (['furniture'=>'Furniture','essential_oil'=>'Essential Oils','diffuser'=>'Diffusers','bundle'=>'Bundles'] as $k => $lbl): ?>
                <a class="btn btn-sm <?= $type===$k?'btn-primary':'btn-soft' ?>" href="<?= base_url('/partner/shop.php?type=' . urlencode($k)) ?>"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section>
    <div class="container">
        <?php if (!$products): ?>
            <p class="muted" style="text-align:center">No products in this collection yet.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Product</th><th>Retail</th><th>Wholesale</th><th>SKU</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p):
                $retail = effective_price($p);
                $wholesale = partner_wholesale_price($p, $rate); ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e((string)$p['short_description']) ?></span></td>
                    <td class="muted"><?= money($retail) ?></td>
                    <td><strong><?= money($wholesale) ?></strong></td>
                    <td class="muted"><?= e($p['sku']) ?></td>
                    <td>
                        <form method="post" action="<?= base_url('/partner/cart.php') ?>" style="display:flex;gap:6px;align-items:center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <input type="number" name="qty" min="1" max="999" value="1" style="width:70px">
                            <button class="btn btn-primary btn-sm" type="submit">Add</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
