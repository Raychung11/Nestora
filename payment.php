<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/mailer.php';

$pageTitle = 'Upload Payment';
$pageDesc  = 'Upload your payment proof so our Nestora team can confirm your order.';

$orderNumber = input('order');
$order   = null;
$done    = false;
$errors  = [];

function load_order(string $number): ?array
{
    if ($number === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute([':n' => $number]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $orderNumber = input('order');
    $order = load_order($orderNumber);

    $method    = input('method', 'bank_transfer');
    $reference = input('reference');
    $amount    = (float) input('amount');
    $note      = input('note');
    $validMethods = ['bank_transfer', 'cash_deposit', 'fpx', 'other'];

    if (!$order) {
        $errors[] = 'We could not find that order number. Please check and try again.';
    }
    if (!in_array($method, $validMethods, true)) {
        $method = 'bank_transfer';
    }
    if ($amount <= 0) {
        $errors[] = 'Please enter the amount you transferred.';
    }
    if (empty($_FILES['proof']['name'])) {
        $errors[] = 'Please attach your payment proof (image or PDF).';
    }

    if (!$errors && $order) {
        try {
            $path = handle_proof_upload($_FILES['proof'], UPLOAD_PAYMENTS_DIR, 'pay');
            if (!$path) {
                throw new RuntimeException('Please attach a valid file.');
            }
            $pdo = db();
            $pdo->prepare(
                "INSERT INTO payment_proofs (order_id, method, amount, reference, file_path, note, status)
                 VALUES (:oid,:m,:amt,:ref,:fp,:note,'submitted')"
            )->execute([
                ':oid' => $order['id'], ':m' => $method, ':amt' => $amount,
                ':ref' => $reference ?: null, ':fp' => $path, ':note' => $note ?: null,
            ]);
            // Mark payment as pending review; order moves to payment_pending unless already further along.
            $pdo->prepare(
                "UPDATE orders
                 SET payment_status = 'pending',
                     order_status = IF(order_status IN ('new','pending_confirmation'), 'payment_pending', order_status)
                 WHERE id = :id"
            )->execute([':id' => $order['id']]);

            $proofHtml = '<p><strong>Order:</strong> ' . e($order['order_number']) . '<br>'
                . '<strong>Customer:</strong> ' . e($order['customer_name']) . '<br>'
                . '<strong>Method:</strong> ' . e(label($method)) . '<br>'
                . '<strong>Amount:</strong> ' . money($amount)
                . ($reference ? '<br><strong>Reference:</strong> ' . e($reference) : '') . '</p>';
            notify_admin('Payment proof for ' . $order['order_number'],
                mail_template('Payment proof submitted', $proofHtml
                    . '<p>Review and verify it in Admin &rarr; Payments.</p>'),
                $order['email'] ?: null);
            if (!empty($order['email'])) {
                send_mail($order['email'], 'We have received your payment proof (' . $order['order_number'] . ')',
                    mail_template('Payment proof received',
                        '<p>Hi ' . e($order['customer_name']) . ', thank you. We have received your '
                        . 'payment proof and our team will verify it and confirm your order shortly.</p>'
                        . $proofHtml));
            }

            $done = true;
        } catch (Throwable $ex) {
            $errors[] = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'We could not save your payment proof. Please try again.';
        }
    }
} else {
    $order = load_order($orderNumber);
}

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Secure your comfort</span>
        <h2>Upload payment proof</h2>
        <p>Once your transfer is made, share the proof and our Nestora team will confirm everything personally.</p>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($done): ?>
            <div class="form-card" style="text-align:center">
                <h2 style="margin-bottom:12px">Thank you</h2>
                <p class="muted">We&rsquo;ve received your payment proof for order
                    <strong><?= e($order['order_number']) ?></strong>. Our team will verify it and
                    confirm your order shortly.</p>
                <a class="btn btn-primary btn-lg mt" href="<?= whatsapp_url('Hi Nestora, I have uploaded payment proof for order ' . $order['order_number'] . '.') ?>" target="_blank" rel="noopener">Notify us on WhatsApp</a>
                <div style="margin-top:18px"><a href="<?= base_url('/index.php') ?>" class="muted">Back to home</a></div>
            </div>
        <?php else: ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:start">
                <form class="form-card" method="post" enctype="multipart/form-data" style="margin:0;max-width:none">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Order number</label>
                        <input type="text" name="order" value="<?= e($orderNumber) ?>" placeholder="NST-XXXXXX-XXXXXX" required>
                    </div>
                    <?php if ($order): ?>
                        <div class="pd-inst" style="margin:0 0 18px">
                            <strong><?= e($order['customer_name']) ?></strong> &middot;
                            Total <strong><?= money((float)$order['total_amount']) ?></strong><br>
                            <span class="muted">Payment status: <?= e(label($order['payment_status'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="field">
                            <label>Payment method</label>
                            <select name="method">
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="cash_deposit">Cash deposit</option>
                                <option value="fpx">FPX / online banking</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Amount transferred (RM)</label>
                            <input type="number" step="0.01" name="amount"
                                   value="<?= e($order ? (string)$order['total_amount'] : input('amount')) ?>" required>
                        </div>
                    </div>
                    <div class="field"><label>Reference / transaction ID (optional)</label><input type="text" name="reference" value="<?= e(input('reference')) ?>"></div>
                    <div class="field"><label>Payment proof (JPG, PNG, WEBP or PDF)</label><input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></div>
                    <div class="field"><label>Note (optional)</label><textarea name="note"><?= e(input('note')) ?></textarea></div>
                    <button class="btn btn-primary btn-lg btn-block" type="submit">Submit payment proof</button>
                </form>

                <div class="form-card" style="margin:0;max-width:none">
                    <h3 style="margin-bottom:14px">Bank transfer details</h3>
                    <p class="muted">Bank<br><strong><?= e(get_setting('bank_name', 'Maybank')) ?></strong></p>
                    <p class="muted" style="margin-top:12px">Account name<br><strong><?= e(get_setting('bank_account_name', 'NESTORA ENTERPRISE')) ?></strong></p>
                    <p class="muted" style="margin-top:12px">Account number<br><strong><?= e(get_setting('bank_account_number', '5123 4567 8901')) ?></strong></p>
                    <div class="disclaimer" style="margin-top:18px"><?= e(get_setting('payment_instructions', 'Transfer the exact order total, then upload your payment proof.')) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
