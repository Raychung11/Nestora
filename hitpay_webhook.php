<?php
/**
 * NESTORA.my - HitPay server-to-server payment webhook.
 *
 * HitPay POSTs here when a payment request changes state. We verify the
 * HMAC signature, re-confirm the status with HitPay, then mark the order
 * paid and issue/email the receipt. Idempotent and always replies 200 so
 * HitPay does not retry a request we have already handled.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/documents.php';
require_once __DIR__ . '/inc/mailer.php';
require_once __DIR__ . '/inc/hitpay.php';
require_once __DIR__ . '/inc/inventory.php';

// Never leak errors into the webhook response.
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'ok';
    exit;
}

$post = $_POST;

if (!hitpay_verify_webhook($post)) {
    // Bad signature: acknowledge but do nothing.
    echo 'ok';
    exit;
}

$orderNumber = (string) ($post['reference_number'] ?? '');
$status      = strtolower((string) ($post['status'] ?? ''));
$requestId   = (string) ($post['payment_request_id'] ?? ($post['payment_id'] ?? ''));

if ($orderNumber === '') {
    echo 'ok';
    exit;
}

try {
    $pdo  = db();
    $oS   = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $oS->execute([':n' => $orderNumber]);
    $order = $oS->fetch();

    if (!$order) { echo 'ok'; exit; }

    // Already settled - nothing to do (idempotent).
    if ($order['payment_status'] === 'paid') { echo 'ok'; exit; }

    // Defense in depth: re-confirm with HitPay rather than trusting the
    // POST body alone. The stored payment_ref is the payment-request id.
    $confirmed = $status === 'completed';
    $refId = $order['payment_ref'] ?: $requestId;
    if ($refId !== '') {
        $pr = hitpay_get_payment_request((string) $refId);
        if (is_array($pr) && isset($pr['status'])) {
            $confirmed = strtolower((string) $pr['status']) === 'completed';
        }
    }

    if (!$confirmed) {
        // Pending or failed - record a failed state only if explicitly failed.
        if ($status === 'failed') {
            $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=:id AND payment_status<>'paid'")
                ->execute([':id' => $order['id']]);
        }
        echo 'ok';
        exit;
    }

    // Optional sanity check: amount should match the order total.
    if (isset($post['amount'])
        && abs((float) $post['amount'] - (float) $order['total_amount']) > 0.01) {
        echo 'ok';
        exit;
    }

    $pdo->prepare(
        "UPDATE orders
         SET payment_status='paid',
             payment_gateway='hitpay',
             payment_ref=:ref,
             order_status = IF(order_status IN ('new','pending_confirmation','payment_pending'), 'paid', order_status)
         WHERE id=:id"
    )->execute([':ref' => (string) $refId, ':id' => $order['id']]);

    ensure_invoice($pdo, (int) $order['id']);
    ensure_receipt($pdo, (int) $order['id']);
    inventory_decrement_for_order($pdo, (int) $order['id']);

    $row = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $row->execute([':id' => $order['id']]);
    $ord = $row->fetch();

    if ($ord && !empty($ord['email'])) {
        $receiptLink = document_link((string) $ord['order_number'], 'receipt');
        send_mail((string) $ord['email'],
            'Payment received - receipt for ' . $ord['order_number'],
            mail_template('Payment received',
                '<p>Hi ' . e((string) $ord['customer_name']) . ', thank you. Your online '
                . 'payment for order <strong>' . e((string) $ord['order_number'])
                . '</strong> was successful.</p>'
                . '<p><strong>Receipt:</strong> ' . e((string) $ord['receipt_number'])
                . '<br><a href="' . e($receiptLink) . '">View &amp; print your receipt</a></p>'
                . '<p>Total paid: <strong>' . money((float) $ord['total_amount']) . '</strong></p>'));
    }
    if ($ord) {
        notify_admin('Payment received - ' . $ord['order_number'],
            mail_template('Online payment received',
                '<p>Order <strong>' . e((string) $ord['order_number']) . '</strong> was paid '
                . 'online via HitPay.</p><p>Total: <strong>'
                . money((float) $ord['total_amount']) . '</strong></p>'),
            $ord['email'] ?: null);
    }
} catch (Throwable $e) {
    // Swallow: HitPay only needs a 200. The payment can still be verified
    // manually in Admin -> Orders if anything here failed.
}

echo 'ok';
