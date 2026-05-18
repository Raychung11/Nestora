<?php
$pageTitle = 'Testimonials';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    if ($action === 'create') {
        if (input('customer_name') !== '' && input('message') !== '') {
            $pdo->prepare(
                'INSERT INTO testimonials (customer_name, location, message, rating, sort_order, status)
                 VALUES (:n,:l,:m,:r,:o,:s)'
            )->execute([
                ':n'=>input('customer_name'), ':l'=>input('location'), ':m'=>input('message'),
                ':r'=>max(1, min(5, (int)input('rating',5))), ':o'=>(int)input('sort_order',0),
                ':s'=>in_array(input('status'),['active','hidden'],true)?input('status'):'active',
            ]);
            set_flash('success', 'Testimonial added.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM testimonials WHERE id=:id')->execute([':id'=>(int)input('id')]);
        set_flash('success', 'Testimonial deleted.');
    }
    redirect(base_url('/admin/testimonials.php'));
}

$rows = $pdo->query("SELECT * FROM testimonials ORDER BY sort_order, id")->fetchAll();
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>Testimonials</h2>
    <?php if (!$rows): ?><p class="muted">No testimonials yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Customer</th><th>Location</th><th>Message</th><th>Rating</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
            <tr>
                <td><strong><?= e($t['customer_name']) ?></strong></td>
                <td><?= e((string)$t['location']) ?></td>
                <td class="muted"><?= e(mb_strimwidth($t['message'], 0, 80, '…')) ?></td>
                <td><?= str_repeat('★', max(1,min(5,(int)$t['rating']))) ?></td>
                <td><span class="tag"><?= e(label($t['status'])) ?></span></td>
                <td>
                    <form method="post" data-confirm="Delete this testimonial?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<form class="panel" method="post" style="max-width:680px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2>New testimonial</h2>
    <div class="form-row">
        <div class="field"><label>Customer name *</label><input type="text" name="customer_name" required></div>
        <div class="field"><label>Location</label><input type="text" name="location"></div>
    </div>
    <div class="field"><label>Message *</label><textarea name="message" required></textarea></div>
    <div class="form-row">
        <div class="field"><label>Rating (1-5)</label><input type="number" name="rating" value="5" min="1" max="5"></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Add testimonial</button>
</form>
<?php admin_layout_end(); ?>
