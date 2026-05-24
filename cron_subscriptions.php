<?php
/**
 * NESTORA.my - Daily subscription runner.
 *
 * Generates a refill order for every active subscription whose next
 * renewal date has arrived, emailing each customer a pay link.
 *
 * Run once a day. Two ways:
 *   CLI : php /path/to/cron_subscriptions.php
 *   URL : https://yoursite/cron_subscriptions.php?key=YOUR_CRON_KEY
 *
 * The key (Admin -> Settings) protects the URL form from public access.
 * CLI runs are always allowed.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/subscriptions.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key      = (string) ($_GET['key'] ?? '');
    $expected = (string) get_setting('cron_key', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

if (!subscriptions_enabled()) {
    echo "Subscriptions are disabled.\n";
    exit;
}

$pdo  = db();
$due  = subscriptions_due($pdo);
$made = 0;

foreach ($due as $sub) {
    if (subscription_generate_order($pdo, $sub)) {
        $made++;
    }
}

echo 'Processed ' . count($due) . ' due subscription(s); generated ' . $made . " order(s).\n";
