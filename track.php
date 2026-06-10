<?php
/**
 * NESTORA.my - Public order tracking by order number.
 */

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/notifications.php';

$pageTitle   = 'Track Order';
$orderNumber = input('order');

$order = null;
$items = [];
if ($orderNumber !== '') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute([':n' => $orderNumber]);
    $order = $stmt->fetch() ?: null;
    if ($order) {
        $iStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id');
        $iStmt->execute([':id' => $order['id']]);
        $items = $iStmt->fetchAll();
    }
}

// Milestones shown on the timeline (skips internal supplier states).
$milestones = [
    'new'                  => 'Order received',
    'pending_confirmation' => 'Confirmed',
    'paid'                 => 'Payment received',
    'preparing_delivery'   => 'Preparing delivery',
    'delivered'            => 'Delivered',
    'completed'            => 'Complete',
];
$order_keys = array_keys($milestones);
$currentIdx = $order ? array_search($order['order_status'], $order_keys, true) : false;
$isCancelled = $order && in_array($order['order_status'], ['cancelled', 'refunded'], true);

require_once __DIR__ . '/inc/header.php';
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Order tracking</span>
        <h2>Where is my comfort?</h2>
        <p>Enter your order number to see the latest status.</p>
    </div>
</section>

<section>
    <div class="container">
        <form class="form-card" method="get" style="max-width:520px;margin-bottom:30px">
            <div class="field">
                <label>Order number</label>
                <input type="text" name="order" value="<?= e($orderNumber) ?>" placeholder="NST-XXXXXX-XXXXXX" required>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Track order</button>
        </form>

        <?php if ($orderNumber !== '' && !$order): ?>
            <p class="muted" style="text-align:center">We couldn&rsquo;t find that order number. Please check and try again, or
                <a href="<?= whatsapp_url() ?>" target="_blank" rel="noopener" style="color:var(--terracotta)">chat with us</a>.</p>
        <?php elseif ($order): ?>
            <div class="form-card" style="max-width:680px">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <div>
                        <div class="muted">Order</div>
                        <strong style="font-size:1.1rem"><?= e($order['order_number']) ?></strong>
                    </div>
                    <div style="text-align:right">
                        <div class="muted">Status</div>
                        <span class="tag"><?= e(order_status_label($order['order_status'])) ?></span>
                    </div>
                </div>

                <?php if ($isCancelled): ?>
                    <div class="flash flash-info" style="margin-top:20px">
                        This order is <?= e(label($order['order_status'])) ?>. If this is unexpected, please contact us.
                    </div>
                <?php else: ?>
                    <div style="margin:24px 0">
                        <?php foreach ($milestones as $key => $labelText):
                            $done = $currentIdx !== false && array_search($key, $order_keys, true) <= $currentIdx; ?>
                            <div style="display:flex;align-items:center;gap:12px;padding:8px 0">
                                <span style="display:inline-flex;width:22px;height:22px;border-radius:50%;align-items:center;justify-content:center;
                                    background:<?= $done ? 'var(--terracotta)' : 'var(--oat)' ?>;color:<?= $done ? '#fff' : 'var(--muted)' ?>;font-size:.7rem">
                                    <?= $done ? '&#10003;' : '' ?>
                                </span>
                                <span style="<?= $done ? 'color:var(--brown);font-weight:600' : 'color:var(--muted)' ?>"><?= e($labelText) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
                <div class="muted">Payment: <?= e(label($order['payment_status'])) ?></div>
                <?php foreach ($items as $it): ?>
                    <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.92rem">
                        <span><?= e($it['product_name']) ?> &times; <?= (int)$it['quantity'] ?></span>
                        <span><?= money((float)$it['line_total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div style="display:flex;justify-content:space-between;margin-top:12px;font-family:var(--font-serif);font-size:1.2rem;color:var(--brown)">
                    <span>Total</span><span><?= money((float)$order['total_amount']) ?></span>
                </div>

                <p class="muted" style="margin-top:18px;font-size:.86rem">
                    <?= e(get_setting('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.')) ?>
                </p>
                <a class="btn btn-soft btn-block mt" href="<?= whatsapp_url('Hi Nestora, I would like an update on order ' . $order['order_number'] . '.') ?>" target="_blank" rel="noopener">Ask about this order on WhatsApp</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
