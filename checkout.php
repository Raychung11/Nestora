<?php
require_once __DIR__ . '/inc/customer_auth.php';
require_once __DIR__ . '/inc/mailer.php';
require_once __DIR__ . '/inc/documents.php';
require_once __DIR__ . '/inc/hitpay.php';
require_once __DIR__ . '/inc/vouchers.php';
require_once __DIR__ . '/inc/inventory.php';

$pageTitle = 'Checkout';

if (empty($_SESSION['cart'])) {
    set_flash('info', 'Your cart is empty.');
    redirect(base_url('/cart.php'));
}

// Prefill from the signed-in customer's record, if any.
$acct = current_customer();
$acctRow = null;
if ($acct) {
    $aStmt = db()->prepare('SELECT name, phone, email, address FROM customers WHERE id = :id LIMIT 1');
    $aStmt->execute([':id' => $acct['id']]);
    $acctRow = $aStmt->fetch() ?: null;
}
$prefill = static function (string $key) use ($acctRow): string {
    $v = input($key);
    if ($v !== '') {
        return e($v);
    }
    return e((string) ($acctRow[$key] ?? ''));
};

// Load cart items
$ids   = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
$rows  = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();
$items = [];
$total = 0.0;
$anyInstallment = false;
$maxMonthsAllowed = 0;

foreach ($rows as $r) {
    $qty   = (int) $_SESSION['cart'][$r['id']];
    $price = effective_price($r);
    $total += $price * $qty;
    if (!empty($r['installment_eligible'])) {
        $anyInstallment = true;
        $maxMonthsAllowed = max($maxMonthsAllowed, (int) $r['max_installment_months']);
    }
    $items[] = ['p' => $r, 'qty' => $qty, 'price' => $price];
}

// Apply a discount code if one is held in the session and still valid.
$voucher     = voucher_current($total);
$discount    = $voucher['discount'] ?? 0.0;
$voucherCode = $voucher['code'] ?? null;
$grandTotal  = max(0, round($total - $discount, 2));

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name    = input('name');
    $phone   = input('phone');
    $email   = input('email');
    $address = input('address');
    $payment = input('payment_method', 'bank_transfer');
    $months  = (int) input('installment_months', 0);

    $validPay = ['bank_transfer', 'fpx', 'installment', 'cash_deposit', 'hitpay'];
    if (!in_array($payment, $validPay, true)) { $payment = 'bank_transfer'; }
    if ($payment === 'hitpay' && !hitpay_enabled()) { $payment = 'bank_transfer'; }
    if (!in_array($months, [0, 6, 12, 24], true)) { $months = 0; }
    if ($payment !== 'installment') { $months = 0; }
    if ($payment === 'installment' && $months === 0) { $months = $maxMonthsAllowed ?: 24; }

    if ($name === '')    { $errors[] = 'Please enter your name.'; }
    if ($phone === '')   { $errors[] = 'Please enter a contact number.'; }
    if ($address === '') { $errors[] = 'Please enter a delivery address.'; }

    foreach ($items as $it) {
        if (!inventory_in_stock($it['p'], $it['qty'])) {
            $errors[] = '"' . $it['p']['name'] . '" no longer has enough stock. Please adjust your cart.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($acct) {
                // Signed-in customer: attach order to their account and
                // keep their saved details current.
                $customerId = (int) $acct['id'];
                $pdo->prepare(
                    'UPDATE customers SET name = :n, phone = :p, email = :e, address = :a WHERE id = :id'
                )->execute([':n' => $name, ':p' => $phone, ':e' => $email ?: null,
                            ':a' => $address, ':id' => $customerId]);
            } else {
                // Guest: reuse a customer by phone or create a new one.
                $cStmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
                $cStmt->execute([':phone' => $phone]);
                $customerId = $cStmt->fetchColumn();

                if (!$customerId) {
                    $cIns = $pdo->prepare(
                        'INSERT INTO customers (name, phone, email, address) VALUES (:n,:p,:e,:a)'
                    );
                    $cIns->execute([':n' => $name, ':p' => $phone, ':e' => $email ?: null, ':a' => $address]);
                    $customerId = (int) $pdo->lastInsertId();
                }
            }

            $orderNumber = generate_order_number();
            $oIns = $pdo->prepare(
                "INSERT INTO orders
                 (order_number, customer_id, customer_name, phone, email, address,
                  total_amount, subtotal_amount, discount_amount, voucher_code,
                  payment_method, installment_months, order_status, payment_status)
                 VALUES (:onum,:cid,:n,:p,:e,:a,:tot,:sub,:disc,:vcode,:pay,:months,'new','unpaid')"
            );
            $oIns->execute([
                ':onum' => $orderNumber, ':cid' => $customerId, ':n' => $name,
                ':p' => $phone, ':e' => $email ?: null, ':a' => $address,
                ':tot' => $grandTotal, ':sub' => $total, ':disc' => $discount,
                ':vcode' => $voucherCode, ':pay' => $payment, ':months' => (string) $months,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $iIns = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, product_id, product_name, sku, unit_price, quantity, line_total)
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

            if ($payment === 'installment' && $months > 0) {
                $monthly = monthly_payment($grandTotal, $months);
                $rIns = $pdo->prepare(
                    "INSERT INTO installment_requests
                     (order_id, customer_name, phone, email, product_summary,
                      total_amount, months, monthly_payment, status)
                     VALUES (:oid,:n,:p,:e,:sum,:tot,:m,:mp,'new')"
                );
                $rIns->execute([
                    ':oid' => $orderId, ':n' => $name, ':p' => $phone, ':e' => $email ?: null,
                    ':sum' => count($items) . ' item(s)', ':tot' => $grandTotal,
                    ':m' => (string) $months, ':mp' => $monthly,
                ]);
            }

            if ($voucherCode) {
                voucher_mark_used($voucherCode);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            voucher_clear();

            ensure_invoice($pdo, $orderId);
            $invStmt = $pdo->prepare('SELECT invoice_number FROM orders WHERE id = :id');
            $invStmt->execute([':id' => $orderId]);
            $invoiceNo   = (string) $invStmt->fetchColumn();
            $invoiceLink = document_link($orderNumber, 'invoice');
            $docHtml = '<p><strong>Invoice:</strong> ' . e($invoiceNo)
                . '<br><a href="' . e($invoiceLink) . '">View &amp; print your invoice</a></p>';

            $itemsHtml = '';
            foreach ($items as $it) {
                $itemsHtml .= '<tr><td style="padding:6px 0">' . e($it['p']['name'])
                    . ' &times; ' . (int) $it['qty'] . '</td><td style="padding:6px 0;text-align:right">'
                    . money($it['price'] * $it['qty']) . '</td></tr>';
            }
            $discountRow = $discount > 0
                ? '<tr><td style="padding:4px 0">Subtotal</td><td style="padding:4px 0;text-align:right">'
                    . money($total) . '</td></tr>'
                  . '<tr><td style="padding:4px 0">Discount' . ($voucherCode ? ' (' . e($voucherCode) . ')' : '')
                    . '</td><td style="padding:4px 0;text-align:right">&minus;' . money($discount) . '</td></tr>'
                : '';
            $summary = '<p><strong>Order:</strong> ' . e($orderNumber) . '<br>'
                . '<strong>Name:</strong> ' . e($name) . '<br>'
                . '<strong>Phone:</strong> ' . e($phone)
                . ($email ? '<br><strong>Email:</strong> ' . e($email) : '')
                . '<br><strong>Address:</strong> ' . nl2br(e($address)) . '</p>'
                . '<table style="width:100%;border-collapse:collapse">' . $itemsHtml . $discountRow
                . '<tr><td style="padding:8px 0;border-top:1px solid #e3d6c4"><strong>Total</strong></td>'
                . '<td style="padding:8px 0;border-top:1px solid #e3d6c4;text-align:right"><strong>'
                . money($grandTotal) . '</strong></td></tr></table>';

            notify_admin(
                'New order ' . $orderNumber,
                mail_template('New order received', $summary . $docHtml),
                $email ?: null
            );
            if ($email) {
                send_mail($email, 'We have received your Nestora order ' . $orderNumber,
                    mail_template('Thank you for your order',
                        '<p>Hi ' . e($name) . ', we have received your order and our Nestora '
                        . 'team will confirm the next steps with you personally.</p>' . $summary . $docHtml
                        . '<p style="color:#8a7d6e;font-size:13px">'
                        . e(get_setting('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.'))
                        . '</p>'));
            }

            if ($payment === 'hitpay' && hitpay_enabled()) {
                try {
                    $payUrl = hitpay_start_for_order($pdo, [
                        'id' => $orderId, 'order_number' => $orderNumber,
                        'total_amount' => $grandTotal, 'customer_name' => $name,
                        'email' => $email, 'phone' => $phone,
                    ]);
                    redirect($payUrl);
                } catch (Throwable $hx) {
                    set_flash('info', 'Your order is saved. Online payment could not be '
                        . 'started just now — you can pay by bank transfer below.');
                    redirect(base_url('/order_success.php?order=' . urlencode($orderNumber)));
                }
            }

            set_flash('success', 'Thank you. Your order inquiry has been received.');
            redirect(base_url('/order_success.php?order=' . urlencode($orderNumber)));
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'We could not process your order right now. Please try again or contact us.';
        }
    }
}

require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container">
        <div class="section-head"><span class="eyebrow">Almost home</span><h2>Order details</h2>
            <p><?= e(get_setting('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.')) ?></p>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:30px;align-items:start">
            <form class="form-card" method="post" style="margin:0;max-width:none">
                <?= csrf_field() ?>
                <?php if ($acct): ?>
                    <div class="flash flash-info">Signed in as <?= e($acct['name']) ?>. This order will be saved to your account.</div>
                <?php else: ?>
                    <div class="flash flash-info" style="background:var(--oat)">
                        Have an account? <a href="<?= base_url('/login.php') ?>" style="color:var(--terracotta)">Sign in</a> to track your orders &mdash; or continue as a guest.
                    </div>
                <?php endif; ?>
                <div class="field"><label>Full name</label><input type="text" name="name" value="<?= $prefill('name') ?>" required></div>
                <div class="form-row">
                    <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" value="<?= $prefill('phone') ?>" required></div>
                    <div class="field"><label>Email (optional)</label><input type="email" name="email" value="<?= $prefill('email') ?>"></div>
                </div>
                <div class="field"><label>Delivery address</label><textarea name="address" required><?= $prefill('address') ?></textarea></div>
                <div class="field">
                    <label>Payment method</label>
                    <select name="payment_method" id="payMethod">
                        <?php if (hitpay_enabled()): ?>
                            <option value="hitpay">Pay online now &mdash; card / FPX / e-wallet (HitPay)</option>
                        <?php endif; ?>
                        <option value="bank_transfer">Bank transfer (manual)</option>
                        <option value="cash_deposit">Cash deposit</option>
                        <?php if ($anyInstallment): ?>
                            <option value="installment">Monthly comfort plan (installment)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php if ($anyInstallment): ?>
                    <div class="field" id="instWrap" style="display:none">
                        <label>Installment plan</label>
                        <select name="installment_months">
                            <?php foreach ([6, 12, 24] as $m): if ($m <= ($maxMonthsAllowed ?: 24)): ?>
                                <option value="<?= $m ?>"><?= $m ?> months &mdash; <?= money(monthly_payment($total, $m)) ?>/month</option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary btn-lg btn-block mt" type="submit">Place order inquiry</button>
                <p class="muted" style="font-size:.82rem;margin-top:14px">Our Nestora team will confirm your order and the next steps personally.</p>
            </form>

            <div class="form-card" style="margin:0;max-width:none">
                <h3 style="margin-bottom:16px">Order summary</h3>
                <?php foreach ($items as $it): ?>
                    <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:.92rem">
                        <span><?= e($it['p']['name']) ?> &times; <?= $it['qty'] ?></span>
                        <span><?= money($it['price'] * $it['qty']) ?></span>
                    </div>
                <?php endforeach; ?>
                <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
                <?php if ($discount > 0): ?>
                    <div style="display:flex;justify-content:space-between;font-size:.92rem;color:var(--muted)">
                        <span>Subtotal</span><span><?= money($total) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.92rem;color:var(--terracotta);margin-top:4px">
                        <span>Discount<?= $voucherCode ? ' (' . e($voucherCode) . ')' : '' ?></span><span>&minus;<?= money($discount) ?></span>
                    </div>
                    <hr style="border:none;border-top:1px solid var(--line);margin:12px 0">
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;font-family:var(--font-serif);font-size:1.3rem;color:var(--brown)">
                    <span>Total</span><span><?= money($grandTotal) ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
  var pm = document.getElementById('payMethod'),
      iw = document.getElementById('instWrap');
  if (pm && iw) {
    pm.addEventListener('change', function () {
      iw.style.display = pm.value === 'installment' ? 'block' : 'none';
    });
  }
</script>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
