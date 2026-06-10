<?php
/**
 * NESTORA.my - Start (or retry) a HitPay online payment for an order.
 * Token-gated so only someone with the signed order link can trigger it.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/documents.php';
require_once __DIR__ . '/inc/hitpay.php';

$orderNumber = input('order');
$token       = input('k');

if ($orderNumber === '' || !verify_document_token($orderNumber, $token)) {
    http_response_code(403);
    exit('Invalid payment link.');
}

$pdo  = db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
$stmt->execute([':n' => $orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'We could not find that order.');
    redirect(base_url('/index.php'));
}

if ($order['payment_status'] === 'paid') {
    redirect(base_url('/payment_return.php?order=' . urlencode($orderNumber)));
}

if (!hitpay_enabled()) {
    set_flash('info', 'Online payment is unavailable. Please pay by bank transfer.');
    redirect(base_url('/payment.php?order=' . urlencode($orderNumber)));
}

try {
    $payUrl = hitpay_start_for_order($pdo, $order);
    redirect($payUrl);
} catch (Throwable $e) {
    set_flash('info', 'Online payment could not be started just now. '
        . 'Please pay by bank transfer.');
    redirect(base_url('/payment.php?order=' . urlencode($orderNumber)));
}
