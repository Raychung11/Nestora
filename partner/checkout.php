<?php
require_once __DIR__ . '/../inc/partner_auth.php';
require_once __DIR__ . '/../inc/partners.php';
require_once __DIR__ . '/../inc/inventory.php';
require_once __DIR__ . '/../inc/documents.php';
require_once __DIR__ . '/../inc/mailer.php';
require_once __DIR__ . '/../inc/hitpay.php';

$session = require_partner();
$partner = current_partner_row();
if (!$partner) { partner_logout(); redirect(base_url('/partner/login.php')); }

if (empty($_SESSION['partner_cart'])) {
    set_flash('info', 'Your wholesale cart is empty.');
    redirect(base_url('/partner/cart.php'));
}

$ids = implode(',', array_map('intval', array_keys($_SESSION['partner_cart'])));
$rows = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();
$items = [];
$total = 0.0;
$rate  = (float) $partner['wholesale_discount_rate'];
foreach ($rows as $r) {
    $qty   = (int) $_SESSION['partner_cart'][$r['id']];
    $price = partner_wholesale_price($r, $rate);
    $total += $price * $qty;
    $items[] = ['p' => $r, 'qty' => $qty, 'price' => $price];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name    = input('name')    ?: $partner['name'];
    $phone   = input('phone')   ?: $partner['phone'];
    $address = input('address') ?: $partner['address'];
    $payment = input('payment_method', 'bank_transfer');
    $validPay = ['bank_transfer', 'hitpay'];
    if (!in_array($payment, $validPay, true)) { $payment = 'bank_transfer'; }
    if ($payment === 'hitpay' && !hitpay_enabled())  { $payment = 'bank_transfer'; }

    if ($name === '')    { $errors[] = 'Please enter your name.'; }
    if ($phone === '')   { $errors[] = 'Please enter a contact number.'; }
    if ($address === '') { $errors[] = 'Please enter a delivery address.'; }

    foreach ($items as $it) {
        if (!inventory_in_stock($it['p'], $it['qty'])) {
            $errors[] = '"' . $it['p']['name'] . '" no longer has enough stock.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderNumber = generate_order_number();
            $pdo->prepare(
                "INSERT INTO orders
                 (order_number, customer_name, phone, email, address,
                  total_amount, subtotal_amount, discount_amount,
                  partner_id, is_wholesale,
                  payment_method, installment_months, order_status, payment_status)
                 VALUES (:onum,:n,:p,:e,:a,:tot,:sub,0,:pid,1,:pay,'0','new','unpaid')"
            )->execute([
                ':onum' => $orderNumber, ':n' => $name, ':p' => $phone,
                ':e' => $partner['email'] ?: null, ':a' => $address,
                ':tot' => $total, ':sub' => $total,
                ':pid' => (int) $partner['id'], ':pay' => $payment,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $iIns = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, sku, unit_price, quantity, line_total)
                 VALUES (:oid,:pid,:pname,:sku,:price,:qty,:line)'
            );
            foreach ($items as $it) {
                $iIns->execute([
                    ':oid'   => $orderId,
                    ':pid'   => $it['p']['id'],
                    ':pname' => $it['p']['name'],
                    ':sku'   => $it['p']['sku'],
                    ':price' => $it['price'],
                    ':qty'   => $it['qty'],
                    ':line'  => $it['price'] * $it['qty'],
                ]);
            }

            $pdo->commit();
            $_SESSION['partner_cart'] = [];

            ensure_invoice($pdo, $orderId);
            $payLink = site_origin() . base_url('/order_success.php?order=' . urlencode($orderNumber));

            send_mail((string) $partner['email'],
                'Your wholesale order ' . $orderNumber . ' is received',
                mail_template('Wholesale order received',
                    '<p>Hi ' . e((string) $partner['name']) . ', we have received your wholesale order.</p>'
                    . '<p>Order: <strong>' . e($orderNumber) . '</strong><br>'
                    . 'Total: <strong>' . money($total) . '</strong></p>'
                    . '<p><a href="' . e($payLink) . '">Complete payment for this order</a></p>'));
            notify_admin('Wholesale order ' . $orderNumber,
                mail_template('Wholesale order placed',
                    '<p>Partner: <strong>' . e((string) $partner['name']) . '</strong>'
                    . ' (' . e($partner['referral_code']) . ')<br>'
                    . 'Total: <strong>' . money($total) . '</strong></p>'),
                $partner['email'] ?: null);

            if ($payment === 'hitpay' && hitpay_enabled()) {
                try {
                    $payUrl = hitpay_start_for_order($pdo, [
                        'id' => $orderId, 'order_number' => $orderNumber,
                        'total_amount' => $total, 'customer_name' => $name,
                        'email' => $partner['email'], 'phone' => $phone,
                    ]);
                    redirect($payUrl);
                } catch (Throwable $hx) {
                    set_flash('info', 'Your order is saved. Pay by bank transfer below.');
                }
            }
            set_flash('success', 'Wholesale order placed.');
            redirect(base_url('/order_success.php?order=' . urlencode($orderNumber)));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'We could not place the order. Please try again.';
        }
    }
}

$pageTitle = 'Wholesale Checkout';
require_once __DIR__ . '/../inc/header.php';
?>
<section class="band-soft" style="padding:46px 0 18px">
    <div class="container">
        <span class="eyebrow">Partner Portal &middot; Wholesale</span>
        <h2 style="margin:4px 0 6px">Wholesale checkout</h2>
        <p class="muted">Your wholesale discount: <strong><?= e((string)$rate) ?>%</strong></p>
    </div>
</section>
<section>
    <div class="container">
        <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
        <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:30px;align-items:start">
            <form class="form-card" method="post" style="margin:0;max-width:none">
                <?= csrf_field() ?>
                <div class="field"><label>Name</label><input type="text" name="name" value="<?= e((string)$partner['name']) ?>" required></div>
                <div class="form-row">
                    <div class="field"><label>Phone</label><input type="text" name="phone" value="<?= e((string)$partner['phone']) ?>" required></div>
                    <div class="field"><label>Email</label><input type="email" value="<?= e((string)$partner['email']) ?>" disabled></div>
                </div>
                <div class="field"><label>Delivery address</label><textarea name="address" required><?= e((string)$partner['address']) ?></textarea></div>
                <div class="field">
                    <label>Payment method</label>
                    <select name="payment_method">
                        <?php if (hitpay_enabled()): ?>
                            <option value="hitpay">Pay online (card / FPX / e-wallet)</option>
                        <?php endif; ?>
                        <option value="bank_transfer">Bank transfer (manual)</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-lg btn-block mt" type="submit">Place wholesale order</button>
            </form>
            <div class="form-card" style="margin:0;max-width:none">
                <h3 style="margin-bottom:14px">Order summary</h3>
                <?php foreach ($items as $it): ?>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.92rem">
                        <span><?= e($it['p']['name']) ?> &times; <?= $it['qty'] ?></span>
                        <span><?= money($it['price'] * $it['qty']) ?></span>
                    </div>
                <?php endforeach; ?>
                <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
                <div style="display:flex;justify-content:space-between;font-family:var(--font-serif);font-size:1.3rem;color:var(--brown)">
                    <span>Total</span><span><?= money($total) ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
