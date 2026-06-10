<?php
/**
 * NESTORA.my - Customer order-status notifications.
 *
 * When an order moves into a customer-facing status, email the customer a
 * friendly update with a tracking link. Each status is emailed at most
 * once (guarded by orders.last_status_notified).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/mailer.php';

/** Customer-facing statuses and their email copy. Others are not emailed. */
function order_status_messages(): array
{
    return [
        'pending_confirmation' => ['Your order is confirmed',
            'Your order has been confirmed and our team is preparing the next steps.'],
        'preparing_delivery'   => ['We are preparing your delivery',
            'Good news — we are preparing your order for delivery.'],
        'delivered'            => ['Your order has been delivered',
            'Your order has been delivered. We hope it brings comfort to your home.'],
        'completed'            => ['Your order is complete',
            'Your order is complete. Thank you for choosing Nestora.'],
        'cancelled'            => ['Your order has been cancelled',
            'Your order has been cancelled. If this is unexpected, please contact us.'],
        'refunded'             => ['Your refund has been processed',
            'Your refund has been processed. Please allow a few days for it to appear.'],
    ];
}

/** Friendly label for any order status (for the tracking page). */
function order_status_label(string $status): string
{
    return label($status);
}

/**
 * Email the customer when the order enters a notify-able status (once).
 * Safe to call after every order update.
 */
function notify_order_status(PDO $pdo, array $order): void
{
    $map    = order_status_messages();
    $status = (string) $order['order_status'];
    if (!isset($map[$status])) {
        return;
    }
    if ((string) ($order['last_status_notified'] ?? '') === $status) {
        return;
    }
    // Mark notified first (guarded) so a double submit can't double send.
    $upd = $pdo->prepare(
        'UPDATE orders SET last_status_notified = :s
         WHERE id = :id AND (last_status_notified IS NULL OR last_status_notified <> :s2)'
    );
    $upd->execute([':s' => $status, ':s2' => $status, ':id' => (int) $order['id']]);
    if ($upd->rowCount() === 0) {
        return;
    }

    if (empty($order['email'])) {
        return;
    }
    [$title, $msg] = $map[$status];
    $link = site_origin() . base_url('/track.php?order=' . urlencode((string) $order['order_number']));
    send_mail((string) $order['email'],
        $title . ' — order ' . $order['order_number'],
        mail_template($title,
            '<p>Hi ' . e((string) $order['customer_name']) . ',</p>'
            . '<p>' . e($msg) . '</p>'
            . '<p>Order: <strong>' . e((string) $order['order_number']) . '</strong><br>'
            . '<a href="' . e($link) . '">Track your order</a></p>'));
}
