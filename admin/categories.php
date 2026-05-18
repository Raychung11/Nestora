<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    if ($action === 'create') {
        $name = input('name');
        $type = in_array(input('type'), ['furniture','essential_oil','diffuser','bundle','general'], true) ? input('type') : 'general';
        if ($name !== '') {
            $slug = slugify($name);
            $chk = $pdo->prepare('SELECT id FROM categories WHERE slug=:s');
            $chk->execute([':s' => $slug]);
            if ($chk->fetchColumn()) { $slug .= '-' . substr(bin2hex(random_bytes(2)),0,3); }
            $pdo->prepare('INSERT INTO categories (name, slug, type, sort_order, status) VALUES (:n,:s,:t,:o,\'active\')')
                ->execute([':n'=>$name, ':s'=>$slug, ':t'=>$type, ':o'=>(int)input('sort_order',0)]);
            set_flash('success', 'Category created.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id=:id')->execute([':id'=>(int)input('id')]);
        set_flash('success', 'Category deleted.');
    }
    redirect(base_url('/admin/categories.php'));
}

$cats = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
                     FROM categories c ORDER BY sort_order, name")->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;align-items:start">
    <div class="panel">
        <h2>Categories</h2>
        <?php if (!$cats): ?><p class="muted">No categories yet.</p><?php else: ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Type</th><th>Slug</th><th>Products</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td><?= e(label($c['type'])) ?></td>
                    <td class="muted"><?= e($c['slug']) ?></td>
                    <td><?= (int)$c['product_count'] ?></td>
                    <td>
                        <form method="post" data-confirm="Delete this category?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <form class="panel" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <h2>New category</h2>
        <div class="field"><label>Name</label><input type="text" name="name" required></div>
        <div class="field">
            <label>Type</label>
            <select name="type">
                <?php foreach (['furniture','essential_oil','diffuser','bundle','general'] as $t): ?>
                    <option value="<?= $t ?>"><?= e(label($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
        <button class="btn btn-primary btn-block" type="submit">Create</button>
    </form>
</div>
<?php admin_layout_end(); ?>
