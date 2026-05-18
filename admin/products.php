<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (input('action') === 'delete') {
        $id = (int) input('id');
        $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
        set_flash('success', 'Product deleted.');
    }
    redirect(base_url('/admin/products.php'));
}

$products = $pdo->query(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.updated_at DESC"
)->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>All products</h2>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/product_form.php') ?>">+ New product</a>
    </div>
    <?php if (!$products): ?>
        <p class="muted">No products yet. Create your first comfort piece.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Name</th><th>SKU</th><th>Type</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><strong><?= e($p['name']) ?></strong><?= $p['is_featured'] ? ' <span class="tag">Featured</span>' : '' ?></td>
                <td class="muted"><?= e($p['sku']) ?></td>
                <td><?= e(label($p['product_type'])) ?></td>
                <td><?= e($p['category_name'] ?? '-') ?></td>
                <td><?= money(effective_price($p)) ?></td>
                <td><span class="badge badge-<?= e($p['stock_status']) ?>"><?= e(label($p['stock_status'])) ?></span></td>
                <td><span class="tag"><?= e(label($p['status'])) ?></span></td>
                <td>
                    <div class="actions-inline">
                        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/product_form.php?id=' . (int)$p['id']) ?>">Edit</a>
                        <form method="post" data-confirm="Delete this product permanently?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
