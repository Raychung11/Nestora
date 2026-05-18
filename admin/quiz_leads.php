<?php
$pageTitle = 'Comfort Quiz Leads';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = in_array(input('status'), ['new','contacted','converted','closed'], true) ? input('status') : 'new';
    $pdo->prepare('UPDATE comfort_quiz_leads SET status=:s WHERE id=:id')
        ->execute([':s'=>$status, ':id'=>(int)input('id')]);
    set_flash('success', 'Lead updated.');
    redirect(base_url('/admin/quiz_leads.php'));
}

$leads = $pdo->query("SELECT * FROM comfort_quiz_leads ORDER BY created_at DESC")->fetchAll();
require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <h2>Comfort Quiz Leads</h2>
    <?php if (!$leads): ?><p class="muted">No quiz leads yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Name</th><th>Contact</th><th>Feeling</th><th>Room</th><th>Concern</th><th>Prefers</th><th>Budget</th><th>Inst.</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): ?>
            <tr>
                <td><strong><?= e((string)$l['name']) ?></strong></td>
                <td><?= e((string)$l['phone']) ?><br><span class="muted"><?= e((string)$l['email']) ?></span></td>
                <td><?= e((string)$l['home_feeling']) ?></td>
                <td><?= e((string)$l['room']) ?></td>
                <td><?= e((string)$l['main_concern']) ?></td>
                <td><?= e((string)$l['preference']) ?></td>
                <td><?= e((string)$l['budget_range']) ?></td>
                <td><?= e((string)$l['installment_pref']) ?></td>
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
