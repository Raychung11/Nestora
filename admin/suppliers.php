<?php
$pageTitle = 'Suppliers';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    if ($action === 'create') {
        if (input('company_name') !== '') {
            $pdo->prepare(
                'INSERT INTO suppliers (company_name, contact_person, phone, email, product_categories, address, payment_terms, notes, status)
                 VALUES (:c,:cp,:p,:e,:pc,:a,:pt,:n,:st)'
            )->execute([
                ':c'=>input('company_name'), ':cp'=>input('contact_person'), ':p'=>input('phone'),
                ':e'=>input('email'), ':pc'=>input('product_categories'), ':a'=>input('address'),
                ':pt'=>input('payment_terms'), ':n'=>input('notes'),
                ':st'=>in_array(input('status'),['active','inactive'],true)?input('status'):'active',
            ]);
            set_flash('success', 'Supplier added.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM suppliers WHERE id=:id')->execute([':id'=>(int)input('id')]);
        set_flash('success', 'Supplier removed.');
    }
    redirect(base_url('/admin/suppliers.php'));
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY company_name")->fetchAll();
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>Suppliers <span class="muted" style="font-size:.8rem">(internal only — never shown publicly)</span></h2>
    <?php if (!$suppliers): ?><p class="muted">No suppliers yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Company</th><th>Contact</th><th>Phone</th><th>Email</th><th>Categories</th><th>Terms</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $s): ?>
            <tr>
                <td><strong><?= e($s['company_name']) ?></strong></td>
                <td><?= e((string)$s['contact_person']) ?></td>
                <td><?= e((string)$s['phone']) ?></td>
                <td class="muted"><?= e((string)$s['email']) ?></td>
                <td><?= e((string)$s['product_categories']) ?></td>
                <td><?= e((string)$s['payment_terms']) ?></td>
                <td><span class="tag"><?= e(label($s['status'])) ?></span></td>
                <td>
                    <form method="post" data-confirm="Delete this supplier?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<form class="panel" method="post" style="max-width:760px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2>Add supplier</h2>
    <div class="form-row">
        <div class="field"><label>Company name *</label><input type="text" name="company_name" required></div>
        <div class="field"><label>Contact person</label><input type="text" name="contact_person"></div>
    </div>
    <div class="form-row">
        <div class="field"><label>Phone</label><input type="text" name="phone"></div>
        <div class="field"><label>Email</label><input type="email" name="email"></div>
    </div>
    <div class="field"><label>Product categories</label><input type="text" name="product_categories" placeholder="furniture, essential_oil"></div>
    <div class="field"><label>Address</label><textarea name="address"></textarea></div>
    <div class="form-row">
        <div class="field"><label>Payment terms</label><input type="text" name="payment_terms"></div>
        <div class="field">
            <label>Status</label>
            <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
    </div>
    <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
    <button class="btn btn-primary btn-block" type="submit">Add supplier</button>
</form>
<?php admin_layout_end(); ?>
