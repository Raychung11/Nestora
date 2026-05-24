<?php
$pageTitle = 'Payments';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/documents.php';
require_once __DIR__ . '/../inc/mailer.php';
require_once __DIR__ . '/../inc/inventory.php';
$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id     = (int) input('id');
    $action = input('action');

    $row = $pdo->prepare('SELECT * FROM payment_proofs WHERE id = :id');
    $row->execute([':id' => $id]);
    $proof = $row->fetch();

    if ($proof && in_array($action, ['verify', 'reject'], true)) {
        $newStatus = $action === 'verify' ? 'verified' : 'rejected';
        $pdo->prepare('UPDATE payment_proofs SET status=:s, reviewed_by=:rb WHERE id=:id')
            ->execute([':s' => $newStatus, ':rb' => $admin['id'], ':id' => $id]);

        if ($action === 'verify') {
            $pdo->prepare(
                "UPDATE orders
                 SET payment_status='paid',
                     order_status = IF(order_status IN ('new','pending_confirmation','payment_pending'), 'paid', order_status)
                 WHERE id=:oid"
            )->execute([':oid' => $proof['order_id']]);

            $oid = (int) $proof['order_id'];
            ensure_invoice($pdo, $oid);   // safety: backfill if it was missing
            ensure_receipt($pdo, $oid);
            inventory_decrement_for_order($pdo, $oid);

            $oStmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
            $oStmt->execute([':id' => $oid]);
            $ord = $oStmt->fetch();
            if ($ord && !empty($ord['email'])) {
                $receiptLink = document_link((string) $ord['order_number'], 'receipt');
                send_mail((string) $ord['email'],
                    'Payment received — receipt for ' . $ord['order_number'],
                    mail_template('Payment received',
                        '<p>Hi ' . e((string) $ord['customer_name']) . ', thank you. '
                        . 'We have verified your payment for order <strong>'
                        . e((string) $ord['order_number']) . '</strong>.</p>'
                        . '<p><strong>Receipt:</strong> ' . e((string) $ord['receipt_number'])
                        . '<br><a href="' . e($receiptLink) . '">View &amp; print your receipt</a></p>'
                        . '<p>Total paid: <strong>' . money((float) $ord['total_amount']) . '</strong></p>'));
            }

            set_flash('success', 'Payment verified, receipt issued, order marked as paid.');
        } else {
            $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=:oid")
                ->execute([':oid' => $proof['order_id']]);
            set_flash('info', 'Payment proof rejected.');
        }
    }
    redirect(base_url('/admin/payments.php'));
}

$filter = input('status');
$sql = "SELECT pp.*, o.order_number, o.customer_name, o.phone, o.total_amount
        FROM payment_proofs pp
        JOIN orders o ON o.id = pp.order_id";
$params = [];
if (in_array($filter, ['submitted', 'verified', 'rejected'], true)) {
    $sql .= " WHERE pp.status = :s";
    $params[':s'] = $filter;
}
$sql .= " ORDER BY pp.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proofs = $stmt->fetchAll();

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Payment proofs</h2>
        <form method="get">
            <select name="status" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (['submitted','verified','rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter===$s?'selected':'' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if (!$proofs): ?>
        <p class="muted">No payment proofs yet.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Order</th><th>Customer</th><th>Method</th><th>Amount</th><th>Ref</th><th>Proof</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($proofs as $p): ?>
            <tr>
                <td>
                    <strong><?= e($p['order_number']) ?></strong><br>
                    <a class="muted" href="<?= base_url('/admin/order_view.php?id=' . (int)$p['order_id']) ?>" style="font-size:.8rem">Open order</a>
                </td>
                <td><?= e($p['customer_name']) ?><br><span class="muted"><?= e($p['phone']) ?></span></td>
                <td><?= e(label($p['method'])) ?></td>
                <td><?= money((float)$p['amount']) ?><br><span class="muted" style="font-size:.78rem">order: <?= money((float)$p['total_amount']) ?></span></td>
                <td class="muted"><?= e((string)$p['reference']) ?></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/' . ltrim($p['file_path'], '/')) ?>" target="_blank" rel="noopener">View</a></td>
                <td><span class="badge badge-<?= $p['status']==='verified'?'paid':($p['status']==='rejected'?'unavailable':'pending') ?>"><?= e(label($p['status'])) ?></span></td>
                <td class="muted"><?= e(date('d M Y H:i', strtotime($p['created_at']))) ?></td>
                <td>
                    <?php if ($p['status'] === 'submitted'): ?>
                        <div class="actions-inline">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-primary btn-sm" type="submit">Verify</button>
                            </form>
                            <form method="post" data-confirm="Reject this payment proof?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span class="muted">&mdash;</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php admin_layout_end(); ?>
