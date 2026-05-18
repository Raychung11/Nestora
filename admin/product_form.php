<?php
$pageTitle = 'Product';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

$id = (int) input('id');
$product = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch() ?: null;
    if (!$product) { set_flash('error', 'Product not found.'); redirect(base_url('/admin/products.php')); }
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order, name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, company_name FROM suppliers ORDER BY company_name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Image delete
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
        redirect(base_url('/admin/product_form.php?id=' . $id));
    }

    $data = [
        'name'                 => input('name'),
        'sku'                  => input('sku'),
        'category_id'          => (int) input('category_id') ?: null,
        'product_type'         => input('product_type', 'furniture'),
        'short_description'    => input('short_description'),
        'long_description'     => input('long_description'),
        'feeling_tags'         => input('feeling_tags'),
        'scent_profile'        => input('scent_profile'),
        'scent_mood'           => input('scent_mood'),
        'scent_notes'          => input('scent_notes'),
        'best_room_usage'      => input('best_room_usage'),
        'usage_instructions'   => input('usage_instructions'),
        'safety_disclaimer'    => input('safety_disclaimer'),
        'bottle_size'          => input('bottle_size'),
        'material'             => input('material'),
        'dimensions'           => input('dimensions'),
        'delivery_note'        => input('delivery_note'),
        'price'                => (float) input('price'),
        'promo_price'          => input('promo_price') !== '' ? (float) input('promo_price') : null,
        'installment_eligible' => isset($_POST['installment_eligible']) ? 1 : 0,
        'max_installment_months' => in_array(input('max_installment_months'), ['6','12','24'], true) ? input('max_installment_months') : '24',
        'supplier_cost'        => input('supplier_cost') !== '' ? (float) input('supplier_cost') : null,
        'supplier_id'          => (int) input('supplier_id') ?: null,
        'stock_status'         => in_array(input('stock_status'), ['available','preorder','checking','unavailable'], true) ? input('stock_status') : 'available',
        'is_featured'          => isset($_POST['is_featured']) ? 1 : 0,
        'status'               => in_array(input('status'), ['draft','active','hidden'], true) ? input('status') : 'draft',
    ];

    if ($data['name'] === '') { $errors[] = 'Product name is required.'; }
    if ($data['sku'] === '')  { $errors[] = 'SKU is required.'; }
    if (!in_array($data['product_type'], ['furniture','essential_oil','diffuser','bundle'], true)) {
        $data['product_type'] = 'furniture';
    }

    if (!$errors) {
        $slug = slugify($data['name']);
        // Ensure slug uniqueness.
        $chk = $pdo->prepare('SELECT id FROM products WHERE slug = :s AND id <> :id');
        $chk->execute([':s' => $slug, ':id' => $id]);
        if ($chk->fetchColumn()) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

        try {
            if ($id) {
                $data['slug'] = $slug;
                $data['id']   = $id;
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys(array_diff_key($data, ['id'=>1]))));
                $pdo->prepare("UPDATE products SET $set WHERE id = :id")->execute($data);
                set_flash('success', 'Product updated.');
            } else {
                $data['slug'] = $slug;
                $cols = implode(', ', array_keys($data));
                $ph   = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
                $pdo->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute($data);
                $id = (int) $pdo->lastInsertId();
                set_flash('success', 'Product created.');
            }

            // Optional image upload
            if (!empty($_FILES['image']['name'])) {
                $path = handle_image_upload($_FILES['image'], UPLOAD_PRODUCTS_DIR, 'prod');
                if ($path) {
                    $hasPrimary = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id=:p AND is_primary=1');
                    $hasPrimary->execute([':p' => $id]);
                    $isPrimary = $hasPrimary->fetchColumn() ? 0 : 1;
                    $pdo->prepare(
                        'INSERT INTO product_images (product_id, file_path, alt_text, is_primary)
                         VALUES (:p,:f,:a,:pr)'
                    )->execute([':p' => $id, ':f' => $path, ':a' => $data['name'], ':pr' => $isPrimary]);
                }
            }

            redirect(base_url('/admin/product_form.php?id=' . $id));
        } catch (Throwable $ex) {
            $errors[] = 'Could not save: ' . (APP_ENV === 'development' ? $ex->getMessage() : 'please check your inputs (SKU may already exist).');
        }
    }
}

// For re-rendering form values
$v = function (string $key, $default = '') use ($product) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { return input($key, $default); }
    return $product[$key] ?? $default;
};

$images = [];
if ($id) {
    $imgStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id=:p ORDER BY is_primary DESC, id');
    $imgStmt->execute([':p' => $id]);
    $images = $imgStmt->fetchAll();
}

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel" style="max-width:920px">
    <div class="panel-head">
        <h2><?= $product ? 'Edit product' : 'New product' ?></h2>
        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/products.php') ?>">&larr; Back</a>
    </div>

    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="form-row">
            <div class="field"><label>Product name *</label><input type="text" name="name" value="<?= e((string)$v('name')) ?>" required></div>
            <div class="field"><label>SKU *</label><input type="text" name="sku" value="<?= e((string)$v('sku')) ?>" required></div>
        </div>
        <div class="form-row">
            <div class="field">
                <label>Product type</label>
                <select name="product_type">
                    <?php foreach (['furniture','essential_oil','diffuser','bundle'] as $t): ?>
                        <option value="<?= $t ?>" <?= $v('product_type','furniture')===$t?'selected':'' ?>><?= e(label($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Category</label>
                <select name="category_id">
                    <option value="">— None —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$v('category_id')===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field"><label>Short description</label><input type="text" name="short_description" value="<?= e((string)$v('short_description')) ?>"></div>
        <div class="field"><label>Long / emotional description</label><textarea name="long_description"><?= e((string)$v('long_description')) ?></textarea></div>
        <div class="field"><label>Feeling tags (comma separated, e.g. calm,cozy,warm)</label><input type="text" name="feeling_tags" value="<?= e((string)$v('feeling_tags')) ?>"></div>

        <h3 style="margin:22px 0 12px">Furniture details</h3>
        <div class="form-row">
            <div class="field"><label>Material</label><input type="text" name="material" value="<?= e((string)$v('material')) ?>"></div>
            <div class="field"><label>Dimensions</label><input type="text" name="dimensions" value="<?= e((string)$v('dimensions')) ?>"></div>
        </div>
        <div class="field"><label>Delivery note</label><input type="text" name="delivery_note" value="<?= e((string)$v('delivery_note')) ?>" placeholder="Delivery timeline will be confirmed by our Nestora team after order confirmation."></div>

        <h3 style="margin:22px 0 12px">Essential oil details</h3>
        <div class="form-row">
            <div class="field"><label>Scent mood</label><input type="text" name="scent_mood" value="<?= e((string)$v('scent_mood')) ?>"></div>
            <div class="field"><label>Scent notes</label><input type="text" name="scent_notes" value="<?= e((string)$v('scent_notes')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Scent profile</label><input type="text" name="scent_profile" value="<?= e((string)$v('scent_profile')) ?>"></div>
            <div class="field"><label>Best room usage</label><input type="text" name="best_room_usage" value="<?= e((string)$v('best_room_usage')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Bottle size</label><input type="text" name="bottle_size" value="<?= e((string)$v('bottle_size')) ?>"></div>
            <div class="field"><label>Usage instructions</label><input type="text" name="usage_instructions" value="<?= e((string)$v('usage_instructions')) ?>"></div>
        </div>
        <div class="field"><label>Safety disclaimer</label><textarea name="safety_disclaimer"><?= e((string)$v('safety_disclaimer')) ?></textarea></div>

        <h3 style="margin:22px 0 12px">Pricing &amp; installment</h3>
        <div class="form-row">
            <div class="field"><label>Price (RM) *</label><input type="number" step="0.01" name="price" value="<?= e((string)$v('price','0')) ?>" required></div>
            <div class="field"><label>Promo price (RM)</label><input type="number" step="0.01" name="promo_price" value="<?= e((string)($v('promo_price') ?? '')) ?>"></div>
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

        <h3 style="margin:22px 0 12px">Supplier (admin only — never shown publicly)</h3>
        <div class="form-row">
            <div class="field">
                <label>Supplier</label>
                <select name="supplier_id">
                    <option value="">— None —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$v('supplier_id')===(int)$s['id']?'selected':'' ?>><?= e($s['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Supplier cost (RM)</label><input type="number" step="0.01" name="supplier_cost" value="<?= e((string)($v('supplier_cost') ?? '')) ?>"></div>
        </div>

        <h3 style="margin:22px 0 12px">Status</h3>
        <div class="form-row">
            <div class="field">
                <label>Stock status</label>
                <select name="stock_status">
                    <?php foreach (['available','preorder','checking','unavailable'] as $st): ?>
                        <option value="<?= $st ?>" <?= $v('stock_status','available')===$st?'selected':'' ?>><?= e(label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Publish status</label>
                <select name="status">
                    <?php foreach (['draft','active','hidden'] as $st): ?>
                        <option value="<?= $st ?>" <?= $v('status','draft')===$st?'selected':'' ?>><?= e(label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field"><label><input type="checkbox" name="is_featured" value="1" <?= $v('is_featured')?'checked':'' ?>> Show on homepage (featured)</label></div>

        <div class="field"><label>Add product image (JPG, PNG, WEBP)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>

        <button class="btn btn-primary btn-lg" type="submit"><?= $product ? 'Save changes' : 'Create product' ?></button>
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
