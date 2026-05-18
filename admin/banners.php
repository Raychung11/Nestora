<?php
$pageTitle = 'Banners';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    if ($action === 'create') {
        $imgPath = null;
        try {
            if (!empty($_FILES['image']['name'])) {
                $imgPath = handle_image_upload($_FILES['image'], UPLOAD_BANNERS_DIR, 'banner');
            }
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect(base_url('/admin/banners.php'));
        }
        $pdo->prepare(
            'INSERT INTO homepage_banners (title, subtitle, image_path, link_url, cta_label, sort_order, status)
             VALUES (:t,:s,:i,:l,:c,:o,:st)'
        )->execute([
            ':t'=>input('title'), ':s'=>input('subtitle'), ':i'=>$imgPath,
            ':l'=>input('link_url'), ':c'=>input('cta_label'), ':o'=>(int)input('sort_order',0),
            ':st'=>in_array(input('status'),['active','hidden'],true)?input('status'):'active',
        ]);
        set_flash('success', 'Banner created.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM homepage_banners WHERE id=:id')->execute([':id'=>(int)input('id')]);
        set_flash('success', 'Banner deleted.');
    }
    redirect(base_url('/admin/banners.php'));
}

$banners = $pdo->query("SELECT * FROM homepage_banners ORDER BY sort_order, id")->fetchAll();
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>Homepage banners</h2>
    <?php if (!$banners): ?><p class="muted">No banners yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Title</th><th>Subtitle</th><th>CTA</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($banners as $b): ?>
            <tr>
                <td><strong><?= e((string)$b['title']) ?></strong></td>
                <td class="muted"><?= e((string)$b['subtitle']) ?></td>
                <td><?= e((string)$b['cta_label']) ?></td>
                <td><span class="tag"><?= e(label($b['status'])) ?></span></td>
                <td>
                    <form method="post" data-confirm="Delete this banner?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<form class="panel" method="post" enctype="multipart/form-data" style="max-width:680px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2>New banner</h2>
    <div class="field"><label>Title</label><input type="text" name="title"></div>
    <div class="field"><label>Subtitle</label><input type="text" name="subtitle"></div>
    <div class="form-row">
        <div class="field"><label>Link URL</label><input type="text" name="link_url"></div>
        <div class="field"><label>CTA label</label><input type="text" name="cta_label"></div>
    </div>
    <div class="form-row">
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
        <div class="field"><label>Status</label><select name="status"><option value="active">Active</option><option value="hidden">Hidden</option></select></div>
    </div>
    <div class="field"><label>Image (JPG, PNG, WEBP)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
    <button class="btn btn-primary btn-block" type="submit">Create banner</button>
</form>
<?php admin_layout_end(); ?>
