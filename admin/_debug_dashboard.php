<?php
/**
 * NESTORA — one-off diagnostics for /admin/dashboard.php 500 errors.
 *
 * Drop this file at /admin/_debug_dashboard.php and hit:
 *   https://your-site/admin/_debug_dashboard.php?confirm=yes
 *
 * It runs every step the real dashboard runs, but each step is wrapped
 * in its own try/catch so the first failure does NOT halt the page.
 * Every row shows OK / FAIL and the exact error message + line number.
 *
 *  >>> DELETE THIS FILE FROM THE SERVER ONCE YOU HAVE THE ANSWER. <<<
 *  It exposes database state and internal paths.
 */

// Force all errors visible for this request only.
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Capture fatal/parse errors that would otherwise produce a blank 500.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        echo '<div style="background:#b00020;color:#fff;padding:14px;margin:18px 0;border-radius:8px">'
            . '<strong>Fatal:</strong> ' . htmlspecialchars($err['message'])
            . '<br><small>' . htmlspecialchars($err['file']) . ' line ' . (int) $err['line'] . '</small>'
            . '</div>';
    }
});

if (($_GET['confirm'] ?? '') !== 'yes') {
    http_response_code(403);
    exit('Add ?confirm=yes to the URL to run diagnostics. Delete this file from the server when finished.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Nestora — Dashboard Debug</title>
<style>
    body{font:14px/1.5 system-ui,sans-serif;background:#f7f3ee;color:#222;padding:24px;max-width:1000px;margin:auto}
    h1{margin:0 0 4px}
    h2{margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid #ddd}
    table{width:100%;border-collapse:collapse;background:#fff;margin:8px 0 20px}
    th,td{padding:8px 10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
    th{background:#efe7dc}
    .ok{color:#1f6f33;font-weight:600}
    .fail{color:#b00020;font-weight:600}
    .warn{color:#a16800;font-weight:600}
    pre{background:#1d1d1d;color:#eee;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-word;font-size:12px}
    code{background:#eee;padding:1px 5px;border-radius:3px}
    .banner{background:#b00020;color:#fff;padding:14px;border-radius:8px;margin-bottom:16px}
</style></head><body>

<div class="banner"><strong>Diagnostic file — delete after use.</strong> It reveals server paths, table state and PHP configuration.</div>

<h1>Nestora dashboard diagnostics</h1>
<p>Generated <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></p>

<?php
function row(string $check, bool $ok, string $detail = '', bool $warnOnly = false): void {
    $cls = $ok ? 'ok' : ($warnOnly ? 'warn' : 'fail');
    $lbl = $ok ? 'OK' : ($warnOnly ? 'WARN' : 'FAIL');
    echo '<tr><td>' . htmlspecialchars($check) . '</td><td class="' . $cls . '">' . $lbl
        . '</td><td>' . ($detail !== '' ? '<code>' . htmlspecialchars($detail) . '</code>' : '') . '</td></tr>';
}

/* ------------------------------------------------------------------ */
/* 1. Environment                                                      */
/* ------------------------------------------------------------------ */
echo '<h2>1. PHP environment</h2><table><tr><th>Check</th><th>Status</th><th>Detail</th></tr>';
row('PHP version', PHP_VERSION_ID >= 80000, PHP_VERSION);
foreach (['PDO', 'pdo_mysql', 'mbstring', 'fileinfo', 'session', 'openssl', 'gd'] as $ext) {
    row("Extension: $ext", extension_loaded($ext), '');
}
row('upload_max_filesize',  true, (string) ini_get('upload_max_filesize'), true);
row('post_max_size',        true, (string) ini_get('post_max_size'), true);
row('memory_limit',         true, (string) ini_get('memory_limit'), true);
row('max_execution_time',   true, (string) ini_get('max_execution_time'), true);
row('error_log path',       true, (string) ini_get('error_log'), true);
echo '</table>';

/* ------------------------------------------------------------------ */
/* 2. File presence                                                    */
/* ------------------------------------------------------------------ */
echo '<h2>2. Files dashboard.php depends on</h2><table><tr><th>File</th><th>Status</th><th>Detail</th></tr>';
$projectRoot = dirname(__DIR__);
$files = [
    'config/db_config.php',
    'inc/functions.php',
    'inc/auth.php',
    'inc/inventory.php',
    'inc/admin_layout.php',
    '.user.ini',
    '.htaccess',
];
foreach ($files as $f) {
    $abs = $projectRoot . '/' . $f;
    row($f, is_file($abs) && is_readable($abs), is_file($abs) ? ('size ' . filesize($abs) . ' bytes') : 'missing');
}
echo '</table>';

/* ------------------------------------------------------------------ */
/* 3. Load functions.php + DB connection                               */
/* ------------------------------------------------------------------ */
echo '<h2>3. Bootstrap + database connection</h2><table><tr><th>Check</th><th>Status</th><th>Detail</th></tr>';
$pdo = null;
try {
    require_once $projectRoot . '/inc/functions.php';
    row('require inc/functions.php', true);
} catch (Throwable $e) {
    row('require inc/functions.php', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    echo '</table>'; finish();
}
try {
    $pdo = db();
    row('db() returns PDO', $pdo instanceof PDO, get_class($pdo));
} catch (Throwable $e) {
    row('db() connection', false, $e->getMessage());
    echo '</table>'; finish();
}
try {
    $info = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    row('Server version', true, (string) $info, true);
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    row('Connected database', !empty($dbName), (string) $dbName);
} catch (Throwable $e) {
    row('Server info', false, $e->getMessage());
}
echo '</table>';

/* ------------------------------------------------------------------ */
/* 4. Required tables                                                  */
/* ------------------------------------------------------------------ */
echo '<h2>4. Required database tables</h2><table><tr><th>Table</th><th>Status</th><th>Detail</th></tr>';
$tables = [
    'admin_users', 'customers', 'orders', 'order_items', 'products',
    'product_images', 'categories', 'suppliers',
    'comfort_quiz_leads', 'installment_requests', 'whatsapp_leads',
    'payment_proofs', 'purchase_orders',
    'partners', 'partner_commissions', 'partner_payouts', 'partner_applications',
    'subscriptions', 'vouchers',
    'homepage_banners', 'testimonials', 'site_settings',
];
foreach ($tables as $t) {
    try {
        $exists = (bool) $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
        if ($exists) {
            $n = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            row($t, true, "$n row" . ($n === 1 ? '' : 's'));
        } else {
            row($t, false, 'MISSING — run the matching migration on this database');
        }
    } catch (Throwable $e) {
        row($t, false, $e->getMessage());
    }
}
echo '</table>';

/* ------------------------------------------------------------------ */
/* 5. Run each dashboard query in isolation                            */
/* ------------------------------------------------------------------ */
echo '<h2>5. Dashboard queries</h2><table><tr><th>Query</th><th>Status</th><th>Detail</th></tr>';
$queries = [
    'totalOrders'      => "SELECT COUNT(*) FROM orders",
    'newInquiries'     => "SELECT COUNT(*) FROM orders WHERE order_status IN ('new','pending_confirmation')",
    'pendingSupplier'  => "SELECT COUNT(*) FROM orders WHERE supplier_status IN ('not_started','checking')",
    'paidOrders'       => "SELECT COUNT(*) FROM orders WHERE payment_status='paid'",
    'monthlySales'     => "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' AND MONTH(created_at)=MONTH(CURRENT_DATE()) AND YEAR(created_at)=YEAR(CURRENT_DATE())",
    'quizLeads'        => "SELECT COUNT(*) FROM comfort_quiz_leads WHERE status='new'",
    'instRequests'     => "SELECT COUNT(*) FROM installment_requests WHERE status IN ('new','reviewing')",
    'waLeads'          => "SELECT COUNT(*) FROM whatsapp_leads WHERE status='new'",
    'paymentsToVerify' => "SELECT COUNT(*) FROM payment_proofs WHERE status='submitted'",
    'openPOs'          => "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('ordered','partial')",
    'topProducts'      => "SELECT product_name, SUM(quantity) AS qty, SUM(line_total) AS revenue FROM order_items GROUP BY product_name ORDER BY qty DESC LIMIT 5",
    'repeatOil'        => "SELECT o.customer_name, o.phone, COUNT(*) AS orders_count FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON p.id=oi.product_id WHERE p.product_type='essential_oil' GROUP BY o.phone, o.customer_name HAVING orders_count > 1 ORDER BY orders_count DESC LIMIT 5",
    'recentOrders'     => "SELECT * FROM orders ORDER BY created_at DESC LIMIT 8",
];
foreach ($queries as $label => $sql) {
    try {
        $r = $pdo->query($sql);
        $sample = $r->fetch();
        row($label, true, is_array($sample) ? ('first row: ' . substr(json_encode($sample), 0, 120)) : 'no rows');
    } catch (Throwable $e) {
        row($label, false, $e->getMessage());
    }
}
echo '</table>';

/* ------------------------------------------------------------------ */
/* 6. Auth + layout includes                                           */
/* ------------------------------------------------------------------ */
echo '<h2>6. Layout + helpers</h2><table><tr><th>Check</th><th>Status</th><th>Detail</th></tr>';
foreach (['inc/auth.php', 'inc/inventory.php'] as $inc) {
    try {
        require_once $projectRoot . '/' . $inc;
        row("require $inc", true);
    } catch (Throwable $e) {
        row("require $inc", false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
foreach (['db','e','money','label','base_url','current_admin','require_admin','inventory_low_stock'] as $fn) {
    row("function $fn() exists", function_exists($fn));
}
try {
    if (function_exists('inventory_low_stock')) {
        $low = inventory_low_stock($pdo);
        row('inventory_low_stock($pdo) runs', true, count((array)$low) . ' row(s)');
    }
} catch (Throwable $e) {
    row('inventory_low_stock($pdo)', false, $e->getMessage());
}
echo '</table>';

/* ------------------------------------------------------------------ */
/* 7. Last lines of error_log if reachable                             */
/* ------------------------------------------------------------------ */
echo '<h2>7. Last 30 lines of PHP error log (if readable)</h2>';
$candidates = array_filter([
    ini_get('error_log'),
    $projectRoot . '/error_log',
    $projectRoot . '/php_errorlog',
    dirname($projectRoot) . '/logs/error_log',
]);
$found = false;
foreach ($candidates as $log) {
    if ($log && is_file($log) && is_readable($log)) {
        $lines = @file($log);
        if ($lines) {
            $tail = array_slice($lines, -30);
            echo '<p><code>' . htmlspecialchars($log) . '</code></p>';
            echo '<pre>' . htmlspecialchars(implode('', $tail)) . '</pre>';
            $found = true;
            break;
        }
    }
}
if (!$found) {
    echo '<p>No readable error log at the standard locations. Check your hosting control panel (cPanel &rarr; Errors, Plesk &rarr; Logs).</p>';
}

function finish(): never {
    echo '<hr><p><strong>Diagnostics stopped early because a fatal step failed above.</strong> Delete this file when finished.</p></body></html>';
    exit;
}

echo '<hr><p><strong>Done.</strong> Send the output above to your developer. Then delete <code>/admin/_debug_dashboard.php</code> from the server.</p>';
?>
</body></html>
