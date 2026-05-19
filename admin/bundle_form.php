<?php
/**
 * NESTORA.my - Bundle builder (mix & match).
 *
 * A bundle is stored as a product (product_type='bundle'); its component
 * products + quantities live in bundle_items. Saving auto-computes the
 * "worth" (base_price = sum of component selling prices) and the bundle
 * cost (cost_price = sum of component costs) for margin reporting.
 * Active bundles appear automatically in /products.php?type=bundle.
 */

$pageTitle = 'Bundle';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

$id = (int) input('id');
$bundle = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND product_type = 'bundle'");
    $stmt->execute([':id' => $id]);
    $bundle = $stmt->fetch() ?: null;
    if (!$bundle) { set_flash('error', 'Bundle not found.'); redirect(base_url('/admin/bundles.php')); }
}

// Candidate component products (everything that is not itself a bundle).
$candidates = $pdo->query(
    "SELECT id, name, sku, product_type, price, promo_price, cost_price, status
     FROM products
     WHERE product_type <> 'bundle'
     ORDER BY product_type, name"
)->fetchAll();

// Existing component quantities (for edit prefill).
$selected = [];
if ($id) {
    foreach (bundle_components($id) as $c) {
        $selected[(int) $c['id']] = (int) $c['quantity'];
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (input('action') === 'delete_image') {
        $imgId = (int) input('image_id');
        $row = $pdo->prepare('SELECT file_path FROM product_images WHERE id=:i');
        $row->execute([':i' => $imgId]);
        if ($fp = $row->fetchColumn()) {
            $abs = APP_ROOT . '/' . ltrim((string)$fp, '/');
            if (is_file($abs)) { @unlink($abs); }
        }
        $pdo->prepare('DELETE FROM product_images WHERE id=:i')->execute([':i' => $imgId]);
        set_flash('success', 'Image removed.');
        redirect(base_url('/admin/bundle_form.php?id=' . $id));
    }

    $name  = input('name');
    $sku   = input('sku');
    $price = (float) input('price');

    // Parse component quantities: qty[productId] = n
    $qtyInput = $_POST['qty'] ?? [];
    $components = [];
    if (is_array($qtyInput)) {
        foreach ($qtyInput as $pid => $q) {
            $pid = (int) $pid;
            $q   = (int) $q;
            if ($pid > 0 && $q > 0) {
                $components[$pid] = min($q, 99);
            }
        }
    }
    $selected = $components; // reflect submission on re-render

    if ($name === '')  { $errors[] = 'Bundle name is required.'; }
    if ($sku === '')   { $errors[] = 'SKU is required.'; }
    if ($price <= 0)   { $errors[] = 'Bundle price must be greater than zero.'; }
    if (count($components) < 2) { $errors[] = 'Pick at least 2 products for a bundle.'; }

    if (!$errors) {
        // Compute "worth" and cost from chosen components.
        $worth = 0.0;
        $cost  = 0.0;
        $cmap  = [];
        foreach ($candidates as $c) { $cmap[(int) $c['id']] = $c; }
        foreach ($components as $pid => $q) {
            if (!isset($cmap[$pid])) { continue; }
            $worth += effective_price($cmap[$pid]) * $q;
            $cost  += (float) ($cmap[$pid]['cost_price'] ?? 0) * $q;
        }

        $slug = slugify($name);
        $chk = $pdo->prepare('SELECT id FROM products WHERE slug = :s AND id <> :id');
        $chk->execute([':s' => $slug, ':id' => $id]);
        if ($chk->fetchColumn()) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

        $data = [
            'name'                 => $name,
            'sku'                  => $sku,
            'product_type'         => 'bundle',
            'short_description'    => input('short_description'),
            'long_description'     => input('long_description'),
            'feeling_tags'         => input('feeling_tags'),
            'price'                => $price,
            'base_price'           => $worth > 0 ? $worth : null,
            'cost_price'           => $cost > 0 ? $cost : null,
            'installment_eligible' => isset($_POST['installment_eligible']) ? 1 : 0,
            'max_installment_months' => in_array(input('max_installment_months'), ['6','12','24'], true) ? input('max_installment_months') : '24',
            'stock_status'         => 'available',
            'is_featured'          => isset($_POST['is_featured']) ? 1 : 0,
            'status'               => in_array(input('status'), ['draft','active','hidden'], true) ? input('status') : 'draft',
            'slug'                 => $slug,
        ];

        try {
            $pdo->beginTransaction();
            if ($id) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $data['id'] = $id;
                $pdo->prepare("UPDATE products SET $set WHERE id = :id")->execute($data);
                unset($data['id']);
            } else {
                $cols = implode(', ', array_keys($data));
                $ph   = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
                $pdo->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute($data);
                $id = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM bundle_items WHERE bundle_id = :b')->execute([':b' => $id]);
            $bi = $pdo->prepare(
                'INSERT INTO bundle_items (bundle_id, product_id, quantity) VALUES (:b,:p,:q)'
            );
            foreach ($components as $pid => $q) {
                $bi->execute([':b' => $id, ':p' => $pid, ':q' => $q]);
            }
            $pdo->commit();

            if (!empty($_FILES['image']['name'])) {
                $path = handle_image_upload($_FILES['image'], UPLOAD_PRODUCTS_DIR, 'bundle');
                if ($path) {
                    $hasPrimary = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id=:p AND is_primary=1');
                    $hasPrimary->execute([':p' => $id]);
                    $isPrimary = $hasPrimary->fetchColumn() ? 0 : 1;
                    $pdo->prepare(
                        'INSERT INTO product_images (product_id, file_path, alt_text, is_primary)
                         VALUES (:p,:f,:a,:pr)'
                    )->execute([':p' => $id, ':f' => $path, ':a' => $name, ':pr' => $isPrimary]);
                }
            }

            set_flash('success', 'Bundle saved.');
            redirect(base_url('/admin/bundle_form.php?id=' . $id));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'Could not save: ' . (APP_ENV === 'development' ? $ex->getMessage() : 'please check inputs (SKU may already exist).');
        }
    }
}

$v = function (string $key, $default = '') use ($bundle) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { return input($key, $default); }
    return $bundle[$key] ?? $default;
};

$images = [];
if ($id && $bundle) {
    $imgStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id=:p ORDER BY is_primary DESC, id');
    $imgStmt->execute([':p' => $id]);
    $images = $imgStmt->fetchAll();
}

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel" style="max-width:980px">
    <div class="panel-head">
        <h2><?= $bundle ? 'Edit bundle' : 'New bundle' ?></h2>
        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/bundles.php') ?>">&larr; Back</a>
    </div>

    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="form-row">
            <div class="field"><label>Bundle name *</label><input type="text" name="name" value="<?= e((string)$v('name')) ?>" required></div>
            <div class="field"><label>SKU *</label><input type="text" name="sku" value="<?= e((string)$v('sku')) ?>" placeholder="e.g. BNDL-CALM-01" required></div>
        </div>
        <div class="field"><label>Short description</label><input type="text" name="short_description" value="<?= e((string)$v('short_description')) ?>"></div>
        <div class="field"><label>Long / emotional description</label><textarea name="long_description"><?= e((string)$v('long_description')) ?></textarea></div>
        <div class="field"><label>Feeling tags (comma separated)</label><input type="text" name="feeling_tags" value="<?= e((string)$v('feeling_tags')) ?>" placeholder="calm,cozy,warm"></div>

        <h3 style="margin:22px 0 12px">Mix &amp; match — choose products</h3>
        <p class="muted" style="margin-top:-6px">Set a quantity (1+) for each product to include. Leave at 0 to exclude. Pick at least 2.</p>
        <table class="table">
            <thead><tr><th>Product</th><th>Type</th><th>SKU</th><th>Selling price</th><th>Qty in bundle</th></tr></thead>
            <tbody>
            <?php foreach ($candidates as $c):
                $cur = $selected[(int)$c['id']] ?? 0; ?>
                <tr>
                    <td><?= e($c['name']) ?>
                        <?= $c['status'] !== 'active' ? ' <span class="tag">' . e(label($c['status'])) . '</span>' : '' ?>
                    </td>
                    <td class="muted"><?= e(label($c['product_type'])) ?></td>
                    <td class="muted"><?= e($c['sku']) ?></td>
                    <td><?= money(effective_price($c)) ?></td>
                    <td><input type="number" name="qty[<?= (int)$c['id'] ?>]" value="<?= (int)$cur ?>" min="0" max="99" style="width:80px"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3 style="margin:22px 0 12px">Bundle pricing</h3>
        <div class="form-row">
            <div class="field"><label>Bundle price (RM) *</label><input type="number" step="0.01" name="price" value="<?= e((string)$v('price','0')) ?>" required></div>
            <div class="field">
                <p class="muted" style="font-size:.82rem;margin:0">
                    "Worth" (sum of selling prices) and bundle cost are calculated
                    automatically from the products you pick, so the catalog can show
                    the customer's saving and you can see the margin.
                </p>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label><input type="checkbox" name="installment_eligible" value="1" <?= $v('installment_eligible')?'checked':'' ?>> Installment eligible</label>
            </div>
            <div class="field">
                <label>Max installment months</label>
                <select name="max_installment_months">
                    <?php foreach (['6','12','24'] as $m): ?>
                        <option value="<?= $m ?>" <?= (string)$v('max_installment_months','24')===$m?'selected':'' ?>><?= $m ?> months</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h3 style="margin:22px 0 12px">Publish</h3>
        <div class="form-row">
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft','active','hidden'] as $st): ?>
                        <option value="<?= $st ?>" <?= $v('status','draft')===$st?'selected':'' ?>><?= e(label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label><input type="checkbox" name="is_featured" value="1" <?= $v('is_featured')?'checked':'' ?>> Show on homepage (featured)</label></div>
        </div>
        <div class="field"><label>Bundle image (JPG, PNG, WEBP)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>

        <button class="btn btn-primary btn-lg" type="submit"><?= $bundle ? 'Save bundle' : 'Create bundle' ?></button>
    </form>

    <?php if ($images): ?>
        <h3 style="margin:26px 0 12px">Images</h3>
        <div style="display:flex;gap:14px;flex-wrap:wrap">
            <?php foreach ($images as $im): ?>
                <div style="text-align:center">
                    <img src="<?= e(product_image_url($im['file_path'])) ?>" style="width:130px;height:130px;object-fit:cover;border-radius:12px;border:1px solid var(--line)">
                    <?php if ($im['is_primary']): ?><div class="tag">Primary</div><?php endif; ?>
                    <form method="post" data-confirm="Delete this image?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_image">
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>">
                        <button class="btn btn-danger btn-sm mt" type="submit">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
