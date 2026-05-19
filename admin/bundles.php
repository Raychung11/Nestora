<?php
$pageTitle = 'Bundle Packages';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (input('action') === 'delete') {
        $id = (int) input('id');
        // bundle_items rows cascade via FK; the bundle is a product row.
        $pdo->prepare("DELETE FROM products WHERE id = :id AND product_type = 'bundle'")
            ->execute([':id' => $id]);
        set_flash('success', 'Bundle deleted.');
    }
    redirect(base_url('/admin/bundles.php'));
}

$bundles = $pdo->query(
    "SELECT p.*,
            (SELECT COUNT(*) FROM bundle_items bi WHERE bi.bundle_id = p.id) AS item_count
     FROM products p
     WHERE p.product_type = 'bundle'
     ORDER BY p.updated_at DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Bundle packages</h2>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/bundle_form.php') ?>">+ New bundle</a>
    </div>
    <p class="muted" style="margin-top:-6px">
        Mix &amp; match products into a bundle and set one price. Active bundles
        appear automatically in the <a href="<?= base_url('/products.php?type=bundle') ?>" target="_blank" rel="noopener">Comfort Bundles</a> catalog.
    </p>
    <?php if (!$bundles): ?>
        <p class="muted">No bundles yet. Create your first comfort bundle.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Bundle</th><th>SKU</th><th>Items</th><th>Worth</th><th>Bundle price</th><th>Saving</th><th>Margin</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bundles as $b):
            $worth   = (float) ($b['base_price'] ?? 0);
            $price   = (float) $b['price'];
            $cost    = (float) ($b['cost_price'] ?? 0);
            $saving  = $worth > $price ? $worth - $price : 0;
            $margin  = $price - $cost; ?>
            <tr>
                <td><strong><?= e($b['name']) ?></strong><?= $b['is_featured'] ? ' <span class="tag">Featured</span>' : '' ?></td>
                <td class="muted"><?= e($b['sku']) ?></td>
                <td><?= (int) $b['item_count'] ?></td>
                <td class="muted"><?= $worth > 0 ? money($worth) : '—' ?></td>
                <td><strong><?= money($price) ?></strong></td>
                <td><?= $saving > 0 ? '<span class="tag">Save ' . money($saving) . '</span>' : '—' ?></td>
                <td class="muted"><?= $cost > 0 ? money($margin) : '—' ?></td>
                <td><span class="tag"><?= e(label($b['status'])) ?></span></td>
                <td>
                    <div class="actions-inline">
                        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/bundle_form.php?id=' . (int)$b['id']) ?>">Edit</a>
                        <form method="post" data-confirm="Delete this bundle permanently?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
