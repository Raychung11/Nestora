<?php
$pageTitle = 'WhatsApp Leads';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = in_array(input('status'), ['new','contacted','converted','closed'], true) ? input('status') : 'new';
    $pdo->prepare('UPDATE whatsapp_leads SET status=:s WHERE id=:id')
        ->execute([':s' => $status, ':id' => (int) input('id')]);
    set_flash('success', 'Lead updated.');
    redirect(base_url('/admin/whatsapp_leads.php'));
}

$leads = $pdo->query("SELECT * FROM whatsapp_leads ORDER BY created_at DESC")->fetchAll();
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>WhatsApp &amp; Advisor Leads</h2>
    <?php if (!$leads): ?><p class="muted">No WhatsApp leads yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Name</th><th>Phone</th><th>Area</th><th>Interest</th><th>Detail</th><th>Source</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): ?>
            <tr>
                <td><strong><?= e((string)$l['name']) ?></strong></td>
                <td>
                    <?= e((string)$l['phone']) ?>
                    <?php if ($l['phone']): ?>
                        <br><a class="muted" style="font-size:.78rem" target="_blank" rel="noopener"
                           href="https://wa.me/<?= e(preg_replace('/\D+/', '', (string)$l['phone'])) ?>">Open chat</a>
                    <?php endif; ?>
                </td>
                <td><?= e((string)$l['delivery_area']) ?></td>
                <td><?= e((string)$l['interest']) ?></td>
                <td class="muted" style="max-width:280px"><?= e((string)$l['message']) ?></td>
                <td><span class="tag"><?= e(label((string)$l['source'])) ?></span></td>
                <td>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['new','contacted','converted','closed'] as $s): ?>
                                <option value="<?= $s ?>" <?= $l['status']===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td class="muted"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
