<?php
/**
 * NESTORA.my - Start a scent refill subscription.
 * Requires a signed-in customer. Creates the subscription and generates
 * the first refill order immediately so the customer can pay for it.
 */

require_once __DIR__ . '/inc/customer_auth.php';
require_once __DIR__ . '/inc/subscriptions.php';

if (!subscriptions_enabled()) {
    set_flash('info', 'Subscriptions are not available right now.');
    redirect(base_url('/products.php?type=essential_oil'));
}

$session = require_customer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('/account.php'));
}

require_csrf();

$pdo       = db();
$productId = (int) input('product_id');
$frequency = input('frequency');
$qty       = max(1, min(12, (int) input('quantity', 1)));

if (!array_key_exists($frequency, subscription_frequencies())) {
    $frequency = 'monthly';
}

$pStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND status = 'active' LIMIT 1");
$pStmt->execute([':id' => $productId]);
$product = $pStmt->fetch();

if (!$product || $product['product_type'] !== 'essential_oil') {
    set_flash('error', 'That scent is not available for subscription.');
    redirect(base_url('/products.php?type=essential_oil'));
}

$cStmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
$cStmt->execute([':id' => $session['id']]);
$customer = $cStmt->fetch();
if (!$customer) {
    customer_logout();
    redirect(base_url('/login.php'));
}

$unitPrice = subscriber_price($product);

$pdo->prepare(
    'INSERT INTO subscriptions
     (customer_id, product_id, product_name, sku, quantity, frequency, unit_price,
      customer_name, phone, email, address, status, next_renewal_date)
     VALUES (:cid,:pid,:pname,:sku,:qty,:freq,:price,:cn,:ph,:em,:addr,\'active\',CURDATE())'
)->execute([
    ':cid' => (int) $customer['id'], ':pid' => (int) $product['id'],
    ':pname' => $product['name'], ':sku' => $product['sku'], ':qty' => $qty,
    ':freq' => $frequency, ':price' => $unitPrice,
    ':cn' => $customer['name'], ':ph' => $customer['phone'],
    ':em' => $customer['email'] ?: null, ':addr' => $customer['address'] ?: null,
]);
$subId = (int) $pdo->lastInsertId();

$sStmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = :id');
$sStmt->execute([':id' => $subId]);
$sub = $sStmt->fetch();

$orderId = $sub ? subscription_generate_order($pdo, $sub) : null;

if ($orderId) {
    $oStmt = $pdo->prepare('SELECT order_number FROM orders WHERE id = :id');
    $oStmt->execute([':id' => $orderId]);
    $orderNumber = (string) $oStmt->fetchColumn();
    set_flash('success', 'Your scent subscription is active. Here is your first refill order — complete payment to confirm it.');
    redirect(base_url('/order_success.php?order=' . urlencode($orderNumber)));
}

set_flash('success', 'Your scent subscription is active. We will prepare your first refill shortly.');
redirect(base_url('/account.php'));
