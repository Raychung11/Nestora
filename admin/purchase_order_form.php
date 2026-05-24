<?php
$pageTitle = 'Purchase Order';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/purchasing.php';
$admin = require_admin();
$pdo = db();

$id = (int) input('id');
$po = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM purchase_orders WHERE id = :id');
    $st->execute([':id' => $id]);
    $po = $st->fetch() ?: null;
    if (!$po) { set_flash('error', 'Purchase order not found.'); redirect(base_url('/admin/purchase_orders.php')); }
}

$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status='active' ORDER BY company_name")->fetchAll();
$products  = $pdo->query("SELECT id, name, sku, supplier_cost FROM products ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    $isDraft = !$po || $po['status'] === 'draft';

    if ($action === 'save') {
        $supplierId   = (int) input('supplier_id') ?: null;
        $expectedDate = input('expected_date') !== '' ? input('expected_date') : null;
        $notes        = input('notes');

        if (!$id) {
            $pdo->prepare(
                "INSERT INTO purchase_orders (po_number, supplier_id, status, expected_date, notes, created_by)
                 VALUES (:num,:sid,'draft',:exp,:notes,:by)"
            )->execute([
                ':num' => generate_po_number(), ':sid' => $supplierId,
                ':exp' => $expectedDate, ':notes' => $notes ?: null, ':by' => (int) $admin['id'],
            ]);
            $id = (int) $pdo->lastInsertId();
        } elseif ($isDraft) {
            $pdo->prepare('UPDATE purchase_orders SET supplier_id=:sid, expected_date=:exp, notes=:notes WHERE id=:id')
                ->execute([':sid' => $supplierId, ':exp' => $expectedDate, ':notes' => $notes ?: null, ':id' => $id]);
        }

        // Replace line items (draft only).
        if ($isDraft) {
            $pdo->prepare('DELETE FROM purchase_order_items WHERE po_id = :id')->execute([':id' => $id]);
            $pids  = $_POST['product_id'] ?? [];
            $qtys  = $_POST['quantity'] ?? [];
            $costs = $_POST['unit_cost'] ?? [];
            $ins = $pdo->prepare(
                'INSERT INTO purchase_order_items (po_id, product_id, product_name, sku, quantity_ordered, unit_cost, line_total)
                 VALUES (:po,:pid,:pname,:sku,:qty,:cost,:line)'
            );
            $pmap = [];
            foreach ($products as $p) { $pmap[(int) $p['id']] = $p; }
            foreach ((array) $pids as $i => $pid) {
                $pid  = (int) $pid;
                $qty  = max(0, (int) ($qtys[$i] ?? 0));
                $cost = max(0, (float) ($costs[$i] ?? 0));
                if ($pid > 0 && isset($pmap[$pid]) && $qty > 0) {
                    $ins->execute([
                        ':po' => $id, ':pid' => $pid, ':pname' => $pmap[$pid]['name'],
                        ':sku' => $pmap[$pid]['sku'], ':qty' => $qty, ':cost' => $cost,
                        ':line' => round($qty * $cost, 2),
                    ]);
                }
            }
            po_recalc_totals($pdo, $id);
        }
        set_flash('success', 'Purchase order saved.');
        redirect(base_url('/admin/purchase_order_form.php?id=' . $id));
    }

    if ($action === 'order' && $po && $po['status'] === 'draft') {
        $has = (int) $pdo->query('SELECT COUNT(*) FROM purchase_order_items WHERE po_id=' . (int)$id)->fetchColumn();
        if ($has > 0) {
            $pdo->prepare("UPDATE purchase_orders SET status='ordered', order_date=COALESCE(order_date,CURDATE()) WHERE id=:id")
                ->execute([':id' => $id]);
            set_flash('success', 'Purchase order marked as ordered.');
        } else {
            set_flash('error', 'Add at least one item before ordering.');
        }
        redirect(base_url('/admin/purchase_order_form.php?id=' . $id));
    }

    if ($action === 'receive' && $po && in_array($po['status'], ['ordered','partial'], true)) {
        $map = [];
        foreach ((array) ($_POST['receive'] ?? []) as $itemId => $qty) {
            $map[(int) $itemId] = (int) $qty;
        }
        try {
            po_receive($pdo, $id, $map);
            set_flash('success', 'Stock received and inventory updated.');
        } catch (Throwable $e) {
            set_flash('error', 'Could not receive stock. Please try again.');
        }
        redirect(base_url('/admin/purchase_order_form.php?id=' . $id));
    }

    if ($action === 'pay' && $po) {
        $amt = round((float) input('amount'), 2);
        if ($amt > 0) {
            $paid = round((float) $po['amount_paid'] + $amt, 2);
            $pdo->prepare('UPDATE purchase_orders SET amount_paid=:p, payment_status=:ps WHERE id=:id')
                ->execute([':p' => $paid, ':ps' => po_payment_status((float) $po['total'], $paid), ':id' => $id]);
            set_flash('success', 'Payment recorded.');
        }
        redirect(base_url('/admin/purchase_order_form.php?id=' . $id));
    }

    if ($action === 'cancel' && $po && $po['status'] !== 'received') {
        $pdo->prepare("UPDATE purchase_orders SET status='cancelled' WHERE id=:id")->execute([':id' => $id]);
        set_flash('info', 'Purchase order cancelled.');
        redirect(base_url('/admin/purchase_order_form.php?id=' . $id));
    }
}

// Reload for rendering.
$items = [];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM purchase_orders WHERE id = :id');
    $st->execute([':id' => $id]);
    $po = $st->fetch() ?: $po;
    $iStmt = $pdo->prepare('SELECT * FROM purchase_order_items WHERE po_id = :id ORDER BY id');
    $iStmt->execute([':id' => $id]);
    $items = $iStmt->fetchAll();
}
$isDraft = !$po || $po['status'] === 'draft';
$balance = $po ? max(0, (float) $po['total'] - (float) $po['amount_paid']) : 0;

require_once __DIR__ . '/../inc/admin_layout.php';
?>
<div class="panel-head">
    <h2><?= $po ? 'PO ' . e($po['po_number']) : 'New purchase order' ?>
        <?php if ($po): ?><span class="badge badge-<?= $po['status']==='received'?'paid':($po['status']==='cancelled'?'unavailable':'preorder') ?>" style="margin-left:8px"><?= e(label($po['status'])) ?></span><?php endif; ?>
    </h2>
    <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/purchase_orders.php') ?>">&larr; Back</a>
</div>

<?php if ($isDraft): ?>
    <form class="panel" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-row">
            <div class="field">
                <label>Supplier</label>
                <select name="supplier_id">
                    <option value="">— Select supplier —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ($po['supplier_id'] ?? 0)==$s['id']?'selected':'' ?>><?= e($s['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Expected delivery date</label><input type="date" name="expected_date" value="<?= e((string)($po['expected_date'] ?? '')) ?>"></div>
        </div>
        <div class="field"><label>Notes</label><textarea name="notes"><?= e((string)($po['notes'] ?? '')) ?></textarea></div>

        <h3 style="margin:18px 0 10px">Items</h3>
        <table class="table">
            <thead><tr><th>Product</th><th>Qty</th><th>Unit cost (RM)</th></tr></thead>
            <tbody>
            <?php
            $rows = $items;
            for ($b = 0; $b < 6; $b++) { $rows[] = ['product_id' => 0, 'quantity_ordered' => '', 'unit_cost' => '']; }
            foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <select name="product_id[]">
                            <option value="0">—</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (int)($row['product_id'] ?? 0)===(int)$p['id']?'selected':'' ?>>
                                    <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="quantity[]" min="0" value="<?= e((string)($row['quantity_ordered'] ?? '')) ?>" style="width:90px"></td>
                    <td><input type="number" step="0.01" name="unit_cost[]" min="0" value="<?= e((string)($row['unit_cost'] ?? '')) ?>" style="width:120px"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted" style="font-size:.82rem">Leave a row's product as "—" to skip it. Save to recalculate totals.</p>
        <button class="btn btn-primary btn-lg" type="submit"><?= $po ? 'Save draft' : 'Create purchase order' ?></button>
    </form>

    <?php if ($po && $items): ?>
    <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>Total: <strong><?= money((float)$po['total']) ?></strong></div>
            <div class="actions-inline">
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="order">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <button class="btn btn-primary" type="submit">Mark as ordered (send to supplier)</button>
                </form>
                <form method="post" data-confirm="Cancel this purchase order?"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <button class="btn btn-danger" type="submit">Cancel PO</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;align-items:start">
        <div class="panel">
            <h2>Items</h2>
            <?php $canReceive = in_array($po['status'], ['ordered','partial'], true); ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="receive">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <table class="table">
                    <thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Unit cost</th><th>Line total</th><?php if ($canReceive): ?><th>Receive now</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): $remaining = (int)$it['quantity_ordered'] - (int)$it['quantity_received']; ?>
                        <tr>
                            <td><?= e($it['product_name']) ?><br><span class="muted" style="font-size:.8rem"><?= e((string)$it['sku']) ?></span></td>
                            <td><?= (int)$it['quantity_ordered'] ?></td>
                            <td><?= (int)$it['quantity_received'] ?></td>
                            <td><?= money((float)$it['unit_cost']) ?></td>
                            <td><?= money((float)$it['line_total']) ?></td>
                            <?php if ($canReceive): ?>
                                <td><input type="number" name="receive[<?= (int)$it['id'] ?>]" min="0" max="<?= max(0,$remaining) ?>" value="0" style="width:90px" <?= $remaining<=0?'disabled':'' ?>></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align:right;font-family:var(--font-serif);font-size:1.2rem;color:var(--brown);margin-top:10px">Total: <?= money((float)$po['total']) ?></div>
                <?php if ($canReceive): ?>
                    <button class="btn btn-primary mt" type="submit">Receive entered quantities into stock</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <h2>Details</h2>
            <p class="muted">Order date: <?= $po['order_date'] ? e(date('d M Y', strtotime($po['order_date']))) : '—' ?><br>
                Expected: <?= $po['expected_date'] ? e(date('d M Y', strtotime($po['expected_date']))) : '—' ?><br>
                Received: <?= $po['received_date'] ? e(date('d M Y', strtotime($po['received_date']))) : '—' ?></p>
            <?php if (!empty($po['notes'])): ?><p class="muted"><?= nl2br(e((string)$po['notes'])) ?></p><?php endif; ?>

            <h3 style="margin-top:18px">Supplier payment</h3>
            <p>Total: <strong><?= money((float)$po['total']) ?></strong><br>
               Paid: <strong><?= money((float)$po['amount_paid']) ?></strong><br>
               Balance: <strong><?= money($balance) ?></strong>
               &middot; <span class="tag"><?= e(label($po['payment_status'])) ?></span></p>
            <?php if ($balance > 0 && $po['status'] !== 'cancelled'): ?>
                <form method="post" style="display:flex;gap:8px;align-items:end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <div class="field" style="margin:0"><label>Record a payment (RM)</label><input type="number" step="0.01" min="0" name="amount" value="<?= e((string)$balance) ?>"></div>
                    <button class="btn btn-soft" type="submit">Record</button>
                </form>
            <?php endif; ?>

            <?php if ($po['status'] !== 'received' && $po['status'] !== 'cancelled'): ?>
                <form method="post" data-confirm="Cancel this purchase order?" style="margin-top:18px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Cancel PO</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php admin_layout_end(); ?>
