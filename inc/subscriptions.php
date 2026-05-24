<?php
/**
 * NESTORA.my - Scent refill subscriptions.
 *
 * A subscription is a schedule, not a stored card. Each cycle it
 * generates a normal order + invoice and emails the customer a pay link,
 * so it works with the existing HitPay / bank-transfer payment flows.
 * Cycles are advanced by an admin action or the cron_subscriptions.php
 * script. Customers can pause / resume / cancel at any time.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/mailer.php';
require_once dirname(__DIR__) . '/inc/documents.php';

function subscription_frequencies(): array
{
    return [
        'monthly'    => ['label' => 'Every month',     'months' => 1],
        'bimonthly'  => ['label' => 'Every 2 months',  'months' => 2],
        'quarterly'  => ['label' => 'Every 3 months',  'months' => 3],
    ];
}

function subscription_frequency_months(string $frequency): int
{
    return subscription_frequencies()[$frequency]['months'] ?? 1;
}

function subscription_frequency_label(string $frequency): string
{
    return subscription_frequencies()[$frequency]['label'] ?? label($frequency);
}

function subscriptions_enabled(): bool
{
    return get_setting('subscriptions_enabled', '0') === '1';
}

function subscription_discount_percent(): float
{
    return max(0.0, min(90.0, (float) get_setting('subscription_discount_percent', '10')));
}

/** Subscriber (discounted) price per refill for a product. */
function subscriber_price(array $product): float
{
    $base = effective_price($product);
    $pct  = subscription_discount_percent();
    return round($base * (1 - $pct / 100), 2);
}

/** Next renewal date = $from (or today) + the frequency interval. */
function subscription_next_date(string $frequency, ?string $from = null): string
{
    $base   = $from ? strtotime($from) : time();
    $months = subscription_frequency_months($frequency);
    return date('Y-m-d', strtotime('+' . $months . ' month', $base));
}

/** Active subscriptions whose next renewal date has arrived. */
function subscriptions_due(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM subscriptions
         WHERE status = 'active' AND next_renewal_date IS NOT NULL
           AND next_renewal_date <= CURDATE()
         ORDER BY next_renewal_date ASC, id ASC"
    )->fetchAll();
}

/**
 * Generate one refill order for a subscription, email the customer a pay
 * link, and advance the schedule. Returns the new order id (or null).
 */
function subscription_generate_order(PDO $pdo, array $sub): ?int
{
    $qty   = max(1, (int) $sub['quantity']);
    $price = (float) $sub['unit_price'];
    $total = round($price * $qty, 2);

    try {
        $pdo->beginTransaction();

        $orderNumber = generate_order_number();
        $pdo->prepare(
            "INSERT INTO orders
             (order_number, customer_id, customer_name, phone, email, address,
              total_amount, subtotal_amount, discount_amount, subscription_id,
              payment_method, installment_months, order_status, payment_status)
             VALUES (:onum,:cid,:n,:p,:e,:a,:tot,:sub,0,:subid,'bank_transfer','0','new','unpaid')"
        )->execute([
            ':onum' => $orderNumber, ':cid' => (int) $sub['customer_id'],
            ':n' => $sub['customer_name'], ':p' => $sub['phone'], ':e' => $sub['email'] ?: null,
            ':a' => $sub['address'], ':tot' => $total, ':sub' => $total, ':subid' => (int) $sub['id'],
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO order_items
             (order_id, product_id, product_name, sku, unit_price, quantity, line_total)
             VALUES (:oid,:pid,:pname,:sku,:price,:qty,:line)'
        )->execute([
            ':oid' => $orderId, ':pid' => $sub['product_id'] ?: null,
            ':pname' => $sub['product_name'], ':sku' => $sub['sku'],
            ':price' => $price, ':qty' => $qty, ':line' => $total,
        ]);

        $pdo->prepare(
            "UPDATE subscriptions
             SET last_order_id = :oid, next_renewal_date = :next
             WHERE id = :id"
        )->execute([
            ':oid' => $orderId,
            ':next' => subscription_next_date((string) $sub['frequency']),
            ':id' => (int) $sub['id'],
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return null;
    }

    ensure_invoice($pdo, $orderId);

    $payLink = site_origin() . base_url('/order_success.php?order=' . urlencode($orderNumber));
    $summary = '<p><strong>' . e((string) $sub['product_name']) . '</strong> &times; ' . $qty
        . '<br>Refill total: <strong>' . money($total) . '</strong></p>'
        . '<p>Order: <strong>' . e($orderNumber) . '</strong></p>';

    if (!empty($sub['email'])) {
        send_mail((string) $sub['email'],
            'Your Nestora scent refill is on the way (' . $orderNumber . ')',
            mail_template('Time for your refill',
                '<p>Hi ' . e((string) $sub['customer_name']) . ', your '
                . e(subscription_frequency_label((string) $sub['frequency'])) . ' scent refill is ready.</p>'
                . $summary
                . '<p><a href="' . e($payLink) . '">Review &amp; pay for this refill</a></p>'
                . '<p style="color:#8a7d6e;font-size:13px">You can pause or cancel anytime from your account.</p>'));
    }
    notify_admin('Subscription refill order ' . $orderNumber,
        mail_template('Subscription refill generated', $summary),
        $sub['email'] ?: null);

    return $orderId;
}
