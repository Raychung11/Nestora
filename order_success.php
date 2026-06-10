<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/documents.php';
require_once __DIR__ . '/inc/hitpay.php';

$pageTitle = 'Order Received';
$orderNumber = input('order');

$order = null;
if ($orderNumber !== '') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute([':n' => $orderNumber]);
    $order = $stmt->fetch() ?: null;
}

require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container">
        <div class="form-card" style="text-align:center">
            <span class="eyebrow" style="color:var(--terracotta);letter-spacing:.22em;text-transform:uppercase;font-size:.72rem">Thank you</span>
            <h2 style="margin:14px 0">Your comfort is on its way home</h2>
            <?php if ($order): ?>
                <p class="muted">Order reference: <strong><?= e($order['order_number']) ?></strong></p>
                <div class="pd-inst" style="text-align:left;margin:22px 0">
                    <strong><?= e($order['customer_name']) ?></strong><br>
                    Total: <strong><?= money((float)$order['total_amount']) ?></strong><br>
                    <?php if ((int)$order['installment_months'] > 0): ?>
                        Monthly comfort plan: <?= (int)$order['installment_months'] ?> months
                        (<?= money(monthly_payment((float)$order['total_amount'], (int)$order['installment_months'])) ?>/month)<br>
                    <?php endif; ?>
                    <span class="muted"><?= e(get_setting('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.')) ?></span>
                </div>
            <?php else: ?>
                <p class="muted">Your order inquiry has been received. Our Nestora team will reach out shortly.</p>
            <?php endif; ?>
            <?php if ($order): ?>
                <?php if (hitpay_enabled() && $order['payment_status'] !== 'paid'): ?>
                    <a class="btn btn-primary btn-lg" href="<?= e(base_url('/hitpay_pay.php?order=' . urlencode($order['order_number']) . '&k=' . document_token($order['order_number']))) ?>">Pay online now</a>
                    <a class="btn btn-soft btn-lg" href="<?= base_url('/payment.php?order=' . urlencode($order['order_number'])) ?>">Upload payment proof</a>
                <?php else: ?>
                    <a class="btn btn-primary btn-lg" href="<?= base_url('/payment.php?order=' . urlencode($order['order_number'])) ?>">Upload payment proof</a>
                <?php endif; ?>
                <a class="btn btn-soft btn-lg" href="<?= e(base_url('/document.php?order=' . urlencode($order['order_number']) . '&type=invoice&k=' . document_token($order['order_number']))) ?>" target="_blank" rel="noopener">View invoice</a>
                <?php if (!empty($order['receipt_number'])): ?>
                    <a class="btn btn-soft btn-lg" href="<?= e(base_url('/document.php?order=' . urlencode($order['order_number']) . '&type=receipt&k=' . document_token($order['order_number']))) ?>" target="_blank" rel="noopener">View receipt</a>
                <?php endif; ?>
                <a class="btn btn-soft btn-lg" href="<?= whatsapp_url('Hi Nestora, I just placed order ' . $order['order_number'] . '. I would like to confirm the next steps.') ?>" target="_blank" rel="noopener">Confirm on WhatsApp</a>
            <?php else: ?>
                <a class="btn btn-primary btn-lg" href="<?= whatsapp_url('Hi Nestora, I just placed an order. I would like to confirm the next steps.') ?>" target="_blank" rel="noopener">Confirm on WhatsApp</a>
            <?php endif; ?>
            <div style="margin-top:18px"><a href="<?= base_url('/index.php') ?>" class="muted">Back to home</a></div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
