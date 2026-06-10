<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    $id = (int) input('id');
    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
        set_flash('success', 'Product deleted.');
    } elseif ($action === 'publish' && $id) {
        $pdo->prepare("UPDATE products SET status='active' WHERE id = :id")->execute([':id' => $id]);
        set_flash('success', 'Product is now live on the website.');
    } elseif ($action === 'feature' && $id) {
        $pdo->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = :id')->execute([':id' => $id]);
        $stmt = $pdo->prepare('SELECT is_featured FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $now = (int) $stmt->fetchColumn();
        set_flash('success', $now ? 'Featured on the homepage.' : 'Removed from the homepage.');
    }
    redirect(base_url('/admin/products.php?' . http_build_query(array_filter([
        'type'   => input('return_type'),
        'status' => input('return_status'),
        'q'      => input('return_q'),
    ]))));
}

/* ---------- Filters ---------------------------------------------------- */
$validTypes   = ['furniture','essential_oil','diffuser','bundle'];
$validStatus  = ['active','draft','hidden'];
$type   = input('type');   if (!in_array($type, $validTypes, true))     { $type   = ''; }
$status = input('status'); if (!in_array($status, $validStatus, true))  { $status = ''; }
$q      = trim((string) input('q'));

/* ---------- Counts per tab (one query) -------------------------------- */
$rows = $pdo->query("SELECT product_type, COUNT(*) AS n FROM products GROUP BY product_type")->fetchAll();
$counts = ['all' => 0];
foreach ($validTypes as $t) { $counts[$t] = 0; }
foreach ($rows as $r) {
    $counts[$r['product_type']] = (int) $r['n'];
    $counts['all'] += (int) $r['n'];
}
$statusCounts = $pdo->query("SELECT status, COUNT(*) AS n FROM products GROUP BY status")->fetchAll();
$byStatus = ['active' => 0, 'draft' => 0, 'hidden' => 0];
foreach ($statusCounts as $r) { $byStatus[$r['status']] = (int) $r['n']; }
$featuredCount = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE is_featured = 1")->fetchColumn();

/* ---------- Filtered list --------------------------------------------- */
$where = [];
$params = [];
if ($type)   { $where[] = 'p.product_type = :type';      $params[':type']   = $type; }
if ($status) { $where[] = 'p.status = :status';          $params[':status'] = $status; }
if ($q !== '') {
    $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.short_description LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$sql = 'SELECT p.*, c.name AS category_name
        FROM products p LEFT JOIN categories c ON c.id = p.category_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY p.is_featured DESC, p.updated_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';

$qs = static function (array $overrides) use ($type, $status, $q): string {
    $params = array_filter(array_merge(
        ['type' => $type, 'status' => $status, 'q' => $q],
        $overrides
    ), static fn($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
};
$tabs = [
    ''              => 'All products',
    'furniture'     => 'Furniture',
    'essential_oil' => 'Essential Oils',
    'diffuser'      => 'Diffusers',
    'bundle'        => 'Bundles',
];
?>
<div class="panel-head">
    <h2>Products</h2>
    <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/product_form.php' . ($type ? '?type=' . urlencode($type) : '')) ?>">+ New product</a>
</div>

<div class="stat-grid" style="margin-bottom:14px">
    <div class="stat"><div class="num"><?= (int) $counts['all'] ?></div><div class="lbl">Total products</div></div>
    <div class="stat"><div class="num" style="color:#2f6a3a"><?= $byStatus['active'] ?></div><div class="lbl">Live</div></div>
    <div class="stat"><div class="num" style="color:#b97a1a"><?= $byStatus['draft'] ?></div><div class="lbl">Drafts</div></div>
    <div class="stat"><div class="num" style="color:#7a3a3a"><?= $byStatus['hidden'] ?></div><div class="lbl">Hidden</div></div>
    <div class="stat"><div class="num"><?= $featuredCount ?></div><div class="lbl">Featured on homepage</div></div>
</div>

<div class="panel">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <?php foreach ($tabs as $key => $label):
            $active = $type === $key;
            $count  = $key === '' ? $counts['all'] : ($counts[$key] ?? 0); ?>
            <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-soft' ?>"
               href="<?= base_url('/admin/products.php' . $qs(['type' => $key])) ?>">
                <?= e($label) ?> <span class="muted" style="margin-left:6px;font-weight:400">(<?= $count ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, SKU or description…" style="flex:1;min-width:240px">
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Live (active)</option>
            <option value="draft"  <?= $status==='draft' ?'selected':'' ?>>Drafts</option>
            <option value="hidden" <?= $status==='hidden'?'selected':'' ?>>Hidden</option>
        </select>
        <button class="btn btn-soft btn-sm" type="submit">Filter</button>
        <?php if ($q !== '' || $status !== ''): ?>
            <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/products.php' . ($type ? '?type=' . urlencode($type) : '')) ?>">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (!$products): ?>
        <p class="muted">
            <?php if ($q !== '' || $status !== '' || $type !== ''): ?>
                No products match the current filter.
            <?php else: ?>
                No products yet. Create your first comfort piece.
            <?php endif; ?>
        </p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th></th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p):
            $thumb = product_image_url(product_primary_image((int)$p['id']));
            $st = (string) $p['status']; ?>
            <tr>
                <td style="width:62px">
                    <img src="<?= e($thumb) ?>" alt="" loading="lazy"
                         style="width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:#faf6f0">
                </td>
                <td>
                    <strong><?= e($p['name']) ?></strong>
                    <?php if ($p['is_featured']): ?> <span class="tag" style="background:#c46a4a;color:#fff">★ Featured</span><?php endif; ?>
                    <br><span class="muted" style="font-size:.8rem"><?= e(label($p['product_type'])) ?></span>
                </td>
                <td class="muted"><?= e($p['sku']) ?></td>
                <td><?= e($p['category_name'] ?? '-') ?></td>
                <td><?= money(effective_price($p)) ?></td>
                <td>
                    <span class="badge badge-<?= e($p['stock_status']) ?>"><?= e(label($p['stock_status'])) ?></span>
                    <?php if (!empty($p['track_inventory'])): ?>
                        <br><span class="muted" style="font-size:.78rem">qty <?= (int) $p['stock_quantity'] ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="tag" style="background:<?= $st==='active'?'#2f6a3a':($st==='draft'?'#b97a1a':'#7a3a3a') ?>;color:#fff">
                        <?= e(label($st)) ?><?= $st==='active' ? ' · live' : ' · not on site' ?>
                    </span>
                </td>
                <td>
                    <div class="actions-inline">
                        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/product_form.php?id=' . (int)$p['id']) ?>">Edit</a>
                        <?php if ($st !== 'active'): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="publish">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="return_type"   value="<?= e($type) ?>">
                            <input type="hidden" name="return_status" value="<?= e($status) ?>">
                            <input type="hidden" name="return_q"      value="<?= e($q) ?>">
                            <button class="btn btn-primary btn-sm" type="submit">Publish</button>
                        </form>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="feature">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="return_type"   value="<?= e($type) ?>">
                            <input type="hidden" name="return_status" value="<?= e($status) ?>">
                            <input type="hidden" name="return_q"      value="<?= e($q) ?>">
                            <button class="btn btn-soft btn-sm" type="submit"
                                    title="<?= $p['is_featured'] ? 'Hide from homepage' : 'Show on homepage' ?>">
                                <?= $p['is_featured'] ? 'Unfeature' : 'Feature' ?>
                            </button>
                        </form>
                        <form method="post" data-confirm="Delete this product permanently?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="return_type"   value="<?= e($type) ?>">
                            <input type="hidden" name="return_status" value="<?= e($status) ?>">
                            <input type="hidden" name="return_q"      value="<?= e($q) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted" style="font-size:.85rem;margin-top:14px">
        Showing <strong><?= count($products) ?></strong> of <?= (int) $counts['all'] ?> total.
        Click <strong>Feature</strong> to add to the homepage (up to 3 per category appear there).
    </p>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
