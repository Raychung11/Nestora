<?php
/**
 * NESTORA.my - Landing page after returning from HitPay checkout.
 * The webhook is the source of truth; this page reflects the order's
 * current payment status and offers a manual fallback if still pending.
 */

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/documents.php';

$pageTitle   = 'Payment';
$orderNumber = input('order');

$order = null;
if ($orderNumber !== '') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute([':n' => $orderNumber]);
    $order = $stmt->fetch() ?: null;
}

$paid = $order && $order['payment_status'] === 'paid';

require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container">
        <div class="form-card" style="text-align:center">
            <?php if (!$order): ?>
                <h2 style="margin:14px 0">We couldn&rsquo;t find that order</h2>
                <p class="muted">Please contact us and we&rsquo;ll help right away.</p>
                <a class="btn btn-primary btn-lg" href="<?= whatsapp_url() ?>" target="_blank" rel="noopener">Chat with us</a>
            <?php elseif ($paid): ?>
                <span class="eyebrow" style="color:var(--terracotta);letter-spacing:.22em;text-transform:uppercase;font-size:.72rem">Payment received</span>
                <h2 style="margin:14px 0">Thank you &mdash; your payment is confirmed</h2>
                <p class="muted">Order reference: <strong><?= e($order['order_number']) ?></strong></p>
                <div class="pd-inst" style="text-align:left;margin:22px 0">
                    <strong><?= e($order['customer_name']) ?></strong><br>
                    Total paid: <strong><?= money((float)$order['total_amount']) ?></strong><br>
                    <span class="muted"><?= e(get_setting('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.')) ?></span>
                </div>
                <a class="btn btn-primary btn-lg" href="<?= e(base_url('/document.php?order=' . urlencode($order['order_number']) . '&type=receipt&k=' . document_token($order['order_number']))) ?>" target="_blank" rel="noopener">View receipt</a>
                <a class="btn btn-soft btn-lg" href="<?= e(base_url('/document.php?order=' . urlencode($order['order_number']) . '&type=invoice&k=' . document_token($order['order_number']))) ?>" target="_blank" rel="noopener">View invoice</a>
            <?php else: ?>
                <span class="eyebrow" style="letter-spacing:.22em;text-transform:uppercase;font-size:.72rem">Almost there</span>
                <h2 style="margin:14px 0">We&rsquo;re confirming your payment</h2>
                <p class="muted">Order reference: <strong><?= e($order['order_number']) ?></strong></p>
                <p class="muted">If you completed payment, confirmation usually arrives within a
                    minute. You can refresh this page shortly. If you didn&rsquo;t finish paying,
                    you can try again or pay by bank transfer.</p>
                <div style="margin-top:18px">
                    <a class="btn btn-primary btn-lg" href="<?= e(base_url('/payment_return.php?order=' . urlencode($order['order_number']))) ?>">Refresh status</a>
                    <a class="btn btn-soft btn-lg" href="<?= base_url('/payment.php?order=' . urlencode($order['order_number'])) ?>">Pay by bank transfer</a>
                </div>
                <div style="margin-top:14px">
                    <a class="muted" href="<?= whatsapp_url('Hi Nestora, I just paid for order ' . $order['order_number'] . ' and would like to confirm.') ?>" target="_blank" rel="noopener">Confirm on WhatsApp</a>
                </div>
            <?php endif; ?>
            <div style="margin-top:18px"><a href="<?= base_url('/index.php') ?>" class="muted">Back to home</a></div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
