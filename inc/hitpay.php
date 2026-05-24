<?php
/**
 * NESTORA.my - HitPay online payment gateway (https://hitpayapp.com/my/).
 *
 * Uses the HitPay v1 Payment Request API. The customer is redirected to
 * HitPay's hosted checkout (cards / FPX / e-wallets); a server-to-server
 * webhook confirms the payment, which marks the order paid and issues the
 * receipt. Credentials & mode are configured in Admin -> Settings.
 *
 * Endpoints (per HitPay docs):
 *   live    : https://api.hit-pay.com/v1
 *   sandbox : https://api.sandbox.hit-pay.com/v1
 * Auth header: X-BUSINESS-API-KEY. Webhook is signed with the account
 * Salt using HMAC-SHA256 over the sorted "key+value" concatenation.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function hitpay_mode(): string
{
    return get_setting('hitpay_mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
}

function hitpay_api_base(): string
{
    return hitpay_mode() === 'live'
        ? 'https://api.hit-pay.com/v1'
        : 'https://api.sandbox.hit-pay.com/v1';
}

function hitpay_api_key(): string
{
    return trim((string) get_setting('hitpay_api_key', ''));
}

function hitpay_salt(): string
{
    return (string) get_setting('hitpay_salt', '');
}

function hitpay_currency(): string
{
    $c = strtoupper(trim((string) get_setting('hitpay_currency', 'MYR')));
    return $c !== '' ? $c : 'MYR';
}

/** HitPay is usable only when enabled AND credentials are present. */
function hitpay_enabled(): bool
{
    return get_setting('hitpay_enabled', '0') === '1'
        && hitpay_api_key() !== ''
        && hitpay_salt() !== '';
}

/**
 * Low-level signed request to the HitPay API.
 * Throws RuntimeException on transport or API error.
 */
function hitpay_request(string $method, string $path, array $params = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for HitPay.');
    }
    $url = hitpay_api_base() . $path;
    $ch  = curl_init();
    $headers = [
        'X-BUSINESS-API-KEY: ' . hitpay_api_key(),
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json',
    ];

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } else {
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $body   = curl_exec($ch);
    $errNo  = curl_errno($ch);
    $errMsg = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo !== 0 || $body === false) {
        throw new RuntimeException('Could not reach HitPay (' . $errMsg . ').');
    }
    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unexpected HitPay response.');
    }
    if ($status < 200 || $status >= 300) {
        $msg = $data['message'] ?? ($data['error'] ?? 'HitPay error (HTTP ' . $status . ').');
        throw new RuntimeException(is_string($msg) ? $msg : 'HitPay error.');
    }
    return $data;
}

/** Create a payment request; returns the decoded HitPay response. */
function hitpay_create_payment_request(array $fields): array
{
    return hitpay_request('POST', '/payment-requests', $fields);
}

/** Fetch a payment request for server-side status confirmation. */
function hitpay_get_payment_request(string $id): ?array
{
    try {
        return hitpay_request('GET', '/payment-requests/' . rawurlencode($id));
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Verify a webhook callback. HitPay signs the v1 payment-request webhook
 * by HMAC-SHA256 over every posted field except `hmac`, sorted by key and
 * concatenated as key1value1key2value2..., using the account Salt.
 */
function hitpay_verify_webhook(array $post): bool
{
    $sig = (string) ($post['hmac'] ?? '');
    if ($sig === '' || hitpay_salt() === '') {
        return false;
    }
    unset($post['hmac']);
    ksort($post);
    $concat = '';
    foreach ($post as $k => $v) {
        $concat .= $k . (is_array($v) ? json_encode($v) : (string) $v);
    }
    $calc = hash_hmac('sha256', $concat, hitpay_salt());
    return hash_equals($calc, $sig);
}

/**
 * Create (or recreate) a HitPay payment request for an existing order and
 * persist the gateway reference. Returns the hosted checkout URL.
 */
function hitpay_start_for_order(PDO $pdo, array $order): string
{
    $orderNumber = (string) $order['order_number'];
    $fields = [
        'amount'           => number_format((float) $order['total_amount'], 2, '.', ''),
        'currency'         => hitpay_currency(),
        'name'             => (string) $order['customer_name'],
        'purpose'          => 'Nestora order ' . $orderNumber,
        'reference_number' => $orderNumber,
        'redirect_url'     => site_origin() . base_url('/payment_return.php?order=' . urlencode($orderNumber)),
        'webhook'          => site_origin() . base_url('/hitpay_webhook.php'),
    ];
    if (!empty($order['email'])) {
        $fields['email'] = (string) $order['email'];
    }
    if (!empty($order['phone'])) {
        $fields['phone'] = (string) $order['phone'];
    }
    $resp = hitpay_create_payment_request($fields);

    $url = (string) ($resp['url'] ?? '');
    $id  = (string) ($resp['id'] ?? '');
    if ($url === '') {
        throw new RuntimeException('HitPay did not return a checkout URL.');
    }

    $pdo->prepare(
        "UPDATE orders SET payment_gateway = 'hitpay', payment_ref = :ref WHERE id = :id"
    )->execute([':ref' => $id, ':id' => (int) $order['id']]);

    return $url;
}
