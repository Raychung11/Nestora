<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

/* ---------- Date range -------------------------------------------------- */
$today = new DateTimeImmutable('today');
$preset = input('preset');
$presets = [
    'today'      => [$today, $today],
    '7d'         => [$today->modify('-6 days'), $today],
    '30d'        => [$today->modify('-29 days'), $today],
    '90d'        => [$today->modify('-89 days'), $today],
    'month'      => [$today->modify('first day of this month'), $today],
    'last_month' => [
        (new DateTimeImmutable('first day of last month'))->setTime(0, 0),
        (new DateTimeImmutable('last day of last month'))->setTime(0, 0),
    ],
    'ytd'        => [new DateTimeImmutable('first day of January ' . $today->format('Y')), $today],
];
if ($preset && isset($presets[$preset])) {
    [$fromDt, $toDt] = $presets[$preset];
} else {
    $fromIn = input('from');
    $toIn   = input('to');
    try { $fromDt = $fromIn ? new DateTimeImmutable($fromIn) : $today->modify('-29 days'); }
    catch (Throwable $e) { $fromDt = $today->modify('-29 days'); }
    try { $toDt = $toIn ? new DateTimeImmutable($toIn) : $today; }
    catch (Throwable $e) { $toDt = $today; }
}
if ($fromDt > $toDt) { [$fromDt, $toDt] = [$toDt, $fromDt]; }
// Cap to a year to keep queries fast.
$span = (int) $fromDt->diff($toDt)->format('%a');
if ($span > 365) { $fromDt = $toDt->modify('-365 days'); }
$fromS = $fromDt->format('Y-m-d');
$toS   = $toDt->format('Y-m-d');

/* ---------- CSV exports ------------------------------------------------- */
$export = input('export');
if ($export !== '') {
    $send = static function (string $filename, array $headers, iterable $rows): void {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) { fputcsv($out, $r); }
        fclose($out);
        exit;
    };

    if ($export === 'orders') {
        $stmt = $pdo->prepare(
            'SELECT order_number, created_at, customer_name, phone, email,
                    payment_method, order_status, payment_status, total_amount,
                    discount_amount, voucher_code, referral_code, is_wholesale
             FROM orders
             WHERE DATE(created_at) BETWEEN :f AND :t
             ORDER BY created_at DESC'
        );
        $stmt->execute([':f' => $fromS, ':t' => $toS]);
        $send("orders_{$fromS}_{$toS}.csv",
            ['Order #','Created','Customer','Phone','Email','Payment method',
             'Order status','Payment status','Total','Discount','Voucher','Referral','Wholesale'],
            (function () use ($stmt) {
                while ($r = $stmt->fetch()) { yield array_values($r); }
            })());
    }
    if ($export === 'products') {
        $stmt = $pdo->prepare(
            "SELECT oi.product_name, oi.sku, SUM(oi.quantity) AS qty,
                    SUM(oi.line_total) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.payment_status = 'paid' AND DATE(o.created_at) BETWEEN :f AND :t
             GROUP BY oi.product_id, oi.product_name, oi.sku
             ORDER BY revenue DESC"
        );
        $stmt->execute([':f' => $fromS, ':t' => $toS]);
        $send("sales_by_product_{$fromS}_{$toS}.csv",
            ['Product','SKU','Units sold','Revenue (paid orders)'],
            (function () use ($stmt) {
                while ($r = $stmt->fetch()) { yield array_values($r); }
            })());
    }
    if ($export === 'commissions') {
        $stmt = $pdo->prepare(
            'SELECT pc.created_at, p.name AS partner, p.referral_code, o.order_number,
                    pc.base_amount, pc.rate, pc.commission_amount, pc.status
             FROM partner_commissions pc
             LEFT JOIN partners p ON p.id = pc.partner_id
             LEFT JOIN orders   o ON o.id = pc.order_id
             WHERE DATE(pc.created_at) BETWEEN :f AND :t
             ORDER BY pc.created_at DESC'
        );
        $stmt->execute([':f' => $fromS, ':t' => $toS]);
        $send("commissions_{$fromS}_{$toS}.csv",
            ['Date','Partner','Code','Order','Base','Rate %','Commission','Status'],
            (function () use ($stmt) {
                while ($r = $stmt->fetch()) { yield array_values($r); }
            })());
    }
    if ($export === 'subscriptions') {
        $stmt = $pdo->query(
            "SELECT id, customer_name, email, product_name, quantity, frequency,
                    unit_price, status, next_renewal_date, created_at
             FROM subscriptions ORDER BY created_at DESC"
        );
        $send("subscriptions_{$fromS}_{$toS}.csv",
            ['ID','Customer','Email','Product','Qty','Frequency','Unit price','Status','Next renewal','Created'],
            (function () use ($stmt) {
                while ($r = $stmt->fetch()) { yield array_values($r); }
            })());
    }
    redirect(base_url('/admin/analytics.php'));
}

/* ---------- Helpers ----------------------------------------------------- */
$bind = [':f' => $fromS, ':t' => $toS];
$one = static function (string $sql, array $p = []) use ($pdo) {
    $st = $pdo->prepare($sql); $st->execute($p); return $st->fetch();
};
$all = static function (string $sql, array $p = []) use ($pdo) {
    $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll();
};

/* ---------- KPIs -------------------------------------------------------- */
$kpiPaid = $one(
    "SELECT COUNT(*) AS n, COALESCE(SUM(total_amount),0) AS rev,
            COALESCE(AVG(total_amount),0) AS aov
     FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN :f AND :t",
    $bind
);
$ordersTotal = (int) $one(
    'SELECT COUNT(*) AS n FROM orders WHERE DATE(created_at) BETWEEN :f AND :t', $bind
)['n'];
$newCustomers = (int) $one(
    'SELECT COUNT(*) AS n FROM customers WHERE DATE(created_at) BETWEEN :f AND :t', $bind
)['n'];

/* Approximate MRR: active subscriptions normalised to monthly. */
$mrrRows = $all(
    "SELECT frequency, COALESCE(SUM(unit_price * quantity),0) AS amt
     FROM subscriptions WHERE status = 'active' GROUP BY frequency"
);
$monthsPer = ['monthly' => 1, 'bimonthly' => 2, 'quarterly' => 3];
$mrr = 0.0;
foreach ($mrrRows as $r) {
    $mrr += (float) $r['amt'] / ($monthsPer[$r['frequency']] ?? 1);
}
$activeSubs = (int) $one("SELECT COUNT(*) AS n FROM subscriptions WHERE status='active'")['n'];

$commEarned = (float) $one(
    "SELECT COALESCE(SUM(commission_amount),0) AS a
     FROM partner_commissions
     WHERE status IN ('approved','paid') AND DATE(created_at) BETWEEN :f AND :t",
    $bind
)['a'];

/* ---------- Time series (revenue + orders per day) --------------------- */
$series = $all(
    "SELECT DATE(created_at) AS d,
            COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) AS rev,
            COUNT(*) AS n,
            SUM(CASE WHEN payment_status='paid' THEN 1 ELSE 0 END) AS np
     FROM orders WHERE DATE(created_at) BETWEEN :f AND :t
     GROUP BY DATE(created_at) ORDER BY d",
    $bind
);
$byDay = [];
foreach ($series as $s) { $byDay[$s['d']] = $s; }
$days = [];
for ($d = $fromDt; $d <= $toDt; $d = $d->modify('+1 day')) {
    $key = $d->format('Y-m-d');
    $days[] = [
        'd'   => $key,
        'rev' => (float) ($byDay[$key]['rev'] ?? 0),
        'n'   => (int)   ($byDay[$key]['n']   ?? 0),
        'np'  => (int)   ($byDay[$key]['np']  ?? 0),
    ];
}
$maxRev = max(1.0, max(array_column($days, 'rev')));
$maxOrd = max(1,   max(array_column($days, 'n')));

/* ---------- Top products / categories ---------------------------------- */
$topProducts = $all(
    "SELECT oi.product_id, oi.product_name,
            SUM(oi.quantity) AS qty, SUM(oi.line_total) AS rev
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.payment_status='paid' AND DATE(o.created_at) BETWEEN :f AND :t
     GROUP BY oi.product_id, oi.product_name
     ORDER BY rev DESC LIMIT 10",
    $bind
);
$topCategories = $all(
    "SELECT COALESCE(c.name,'Uncategorised') AS name,
            SUM(oi.quantity) AS qty, SUM(oi.line_total) AS rev
     FROM order_items oi
     JOIN orders o    ON o.id = oi.order_id
     JOIN products p  ON p.id = oi.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE o.payment_status='paid' AND DATE(o.created_at) BETWEEN :f AND :t
     GROUP BY c.id, name
     ORDER BY rev DESC LIMIT 8",
    $bind
);

/* ---------- Payment mix + status breakdown ----------------------------- */
$payMix = $all(
    "SELECT COALESCE(NULLIF(payment_method,''),'unspecified') AS m,
            COUNT(*) AS n, COALESCE(SUM(total_amount),0) AS rev
     FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN :f AND :t
     GROUP BY m ORDER BY rev DESC",
    $bind
);
$payTotal = array_sum(array_column($payMix, 'rev')) ?: 1.0;

$orderStatus   = $all('SELECT order_status   AS s, COUNT(*) AS n FROM orders WHERE DATE(created_at) BETWEEN :f AND :t GROUP BY order_status',   $bind);
$paymentStatus = $all('SELECT payment_status AS s, COUNT(*) AS n FROM orders WHERE DATE(created_at) BETWEEN :f AND :t GROUP BY payment_status', $bind);

/* ---------- Top partners ----------------------------------------------- */
$topPartners = $all(
    "SELECT p.id, p.name, p.referral_code, p.tier,
            COUNT(pc.id) AS refs,
            COALESCE(SUM(pc.base_amount),0) AS sales,
            COALESCE(SUM(pc.commission_amount),0) AS comm
     FROM partners p
     JOIN partner_commissions pc ON pc.partner_id = p.id
        AND DATE(pc.created_at) BETWEEN :f AND :t
     GROUP BY p.id, p.name, p.referral_code, p.tier
     ORDER BY comm DESC LIMIT 5",
    $bind
);

/* ---------- Vouchers --------------------------------------------------- */
$vouchers = $all(
    "SELECT voucher_code AS code, COUNT(*) AS uses,
            COALESCE(SUM(discount_amount),0) AS discount
     FROM orders
     WHERE voucher_code IS NOT NULL AND voucher_code <> ''
       AND DATE(created_at) BETWEEN :f AND :t
     GROUP BY voucher_code
     ORDER BY uses DESC LIMIT 10",
    $bind
);

/* ---------- Low stock -------------------------------------------------- */
$lowStock = $all(
    "SELECT id, name, sku, stock_quantity, low_stock_threshold
     FROM products
     WHERE track_inventory = 1 AND status = 'active'
       AND stock_quantity <= low_stock_threshold
     ORDER BY stock_quantity ASC LIMIT 15"
);

require_once __DIR__ . '/../inc/admin_layout.php';

/* SVG bar chart for time-series. */
$renderBars = static function (array $days, string $valueKey, float $max, string $color) {
    $w = 760; $h = 140; $pad = 8;
    $n = max(1, count($days));
    $barW = max(2.0, ($w - $pad * 2) / $n - 2);
    $gap = 2.0;
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" style="width:100%;height:140px;display:block">';
    $svg .= '<line x1="0" y1="' . ($h - 1) . '" x2="' . $w . '" y2="' . ($h - 1) . '" stroke="#e5d8c9"/>';
    foreach ($days as $i => $d) {
        $v = (float) $d[$valueKey];
        $bh = $max > 0 ? ($v / $max) * ($h - 14) : 0;
        $x = $pad + $i * ($barW + $gap);
        $y = $h - 2 - $bh;
        $title = $d['d'] . ': ' . ($valueKey === 'rev' ? 'RM ' . number_format($v, 2) : (string) (int) $v);
        $svg .= '<rect x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($barW, 2) . '" height="' . round($bh, 2) . '" fill="' . $color . '" rx="1"><title>' . htmlspecialchars($title, ENT_QUOTES) . '</title></rect>';
    }
    $svg .= '</svg>';
    return $svg;
};
?>
<div class="panel-head">
    <h2>Analytics</h2>
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="date" name="from" value="<?= e($fromS) ?>">
        <span class="muted">to</span>
        <input type="date" name="to" value="<?= e($toS) ?>">
        <button class="btn btn-soft btn-sm" type="submit">Apply</button>
        <span class="muted" style="margin-left:8px">Presets:</span>
        <?php foreach (['7d'=>'7d','30d'=>'30d','90d'=>'90d','month'=>'MTD','last_month'=>'Last month','ytd'=>'YTD'] as $k => $lbl): ?>
            <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/analytics.php?preset=' . $k) ?>"><?= e($lbl) ?></a>
        <?php endforeach; ?>
    </form>
</div>
<p class="muted" style="margin-top:-6px">Range: <strong><?= e(date('d M Y', strtotime($fromS))) ?></strong> &rarr; <strong><?= e(date('d M Y', strtotime($toS))) ?></strong> (<?= $span + 1 ?> days)</p>

<div class="stat-grid">
    <div class="stat"><div class="num"><?= money((float)$kpiPaid['rev']) ?></div><div class="lbl">Revenue (paid)</div></div>
    <div class="stat"><div class="num"><?= (int)$kpiPaid['n'] ?></div><div class="lbl">Paid orders</div></div>
    <div class="stat"><div class="num"><?= money((float)$kpiPaid['aov']) ?></div><div class="lbl">Avg order value</div></div>
    <div class="stat"><div class="num"><?= $ordersTotal ?></div><div class="lbl">Total orders</div></div>
    <div class="stat"><div class="num"><?= $newCustomers ?></div><div class="lbl">New customers</div></div>
    <div class="stat"><div class="num"><?= money($mrr) ?></div><div class="lbl">MRR (<?= $activeSubs ?> active subs)</div></div>
    <div class="stat"><div class="num"><?= money($commEarned) ?></div><div class="lbl">Partner commissions</div></div>
</div>

<div class="panel" style="margin-top:18px">
    <h3 style="margin:0 0 10px">Daily revenue (RM, paid orders)</h3>
    <?= $renderBars($days, 'rev', $maxRev, '#c46a4a') ?>
    <h3 style="margin:20px 0 10px">Daily orders (all statuses)</h3>
    <?= $renderBars($days, 'n', (float)$maxOrd, '#7a5a44') ?>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-top:18px;align-items:start">
    <div class="panel">
        <div class="panel-head"><h3 style="margin:0">Top products</h3><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/analytics.php?export=products&from=' . $fromS . '&to=' . $toS) ?>">Export CSV</a></div>
        <?php if (!$topProducts): ?><p class="muted">No paid orders in this range.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topProducts as $p): ?>
                <tr>
                    <td><?= e((string)$p['product_name']) ?></td>
                    <td><?= (int)$p['qty'] ?></td>
                    <td><strong><?= money((float)$p['rev']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3 style="margin:0 0 10px">Top categories</h3>
        <?php if (!$topCategories): ?><p class="muted">—</p>
        <?php else: $catTotal = array_sum(array_column($topCategories, 'rev')) ?: 1.0; ?>
            <?php foreach ($topCategories as $c):
                $pct = round(((float)$c['rev'] / $catTotal) * 100); ?>
                <div style="margin-bottom:10px">
                    <div style="display:flex;justify-content:space-between;font-size:.9rem">
                        <span><?= e((string)$c['name']) ?></span>
                        <span class="muted"><?= money((float)$c['rev']) ?></span>
                    </div>
                    <div style="background:var(--cream);border-radius:3px;height:8px;overflow:hidden;margin-top:4px">
                        <div style="background:var(--terracotta);height:100%;width:<?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-top:18px;align-items:start">
    <div class="panel">
        <h3 style="margin:0 0 10px">Payment method mix</h3>
        <?php if (!$payMix): ?><p class="muted">—</p>
        <?php else: foreach ($payMix as $m):
            $pct = round(((float)$m['rev'] / $payTotal) * 100); ?>
            <div style="margin-bottom:10px">
                <div style="display:flex;justify-content:space-between;font-size:.9rem">
                    <span><?= e(label((string)$m['m'])) ?></span>
                    <span class="muted"><?= (int)$m['n'] ?> · <?= money((float)$m['rev']) ?></span>
                </div>
                <div style="background:var(--cream);border-radius:3px;height:8px;overflow:hidden;margin-top:4px">
                    <div style="background:var(--brown);height:100%;width:<?= $pct ?>%"></div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="panel">
        <h3 style="margin:0 0 10px">Order status</h3>
        <?php foreach ($orderStatus as $r): ?>
            <div style="display:flex;justify-content:space-between;margin:6px 0"><span><?= e(label((string)$r['s'])) ?></span><strong><?= (int)$r['n'] ?></strong></div>
        <?php endforeach; ?>
    </div>
    <div class="panel">
        <h3 style="margin:0 0 10px">Payment status</h3>
        <?php foreach ($paymentStatus as $r): ?>
            <div style="display:flex;justify-content:space-between;margin:6px 0"><span><?= e(label((string)$r['s'])) ?></span><strong><?= (int)$r['n'] ?></strong></div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-top:18px;align-items:start">
    <div class="panel">
        <div class="panel-head"><h3 style="margin:0">Top partners</h3><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/analytics.php?export=commissions&from=' . $fromS . '&to=' . $toS) ?>">Export commissions CSV</a></div>
        <?php if (!$topPartners): ?><p class="muted">No partner activity in this range.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Partner</th><th>Tier</th><th>Refs</th><th>Sales</th><th>Commission</th></tr></thead>
            <tbody>
            <?php foreach ($topPartners as $p): ?>
                <tr>
                    <td><a href="<?= base_url('/admin/partner_form.php?id=' . (int)$p['id']) ?>"><?= e((string)$p['name']) ?></a><br><code style="font-size:.8rem"><?= e((string)$p['referral_code']) ?></code></td>
                    <td><span class="tag"><?= e(label((string)$p['tier'])) ?></span></td>
                    <td><?= (int)$p['refs'] ?></td>
                    <td><?= money((float)$p['sales']) ?></td>
                    <td><strong><?= money((float)$p['comm']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3 style="margin:0 0 10px">Voucher usage</h3>
        <?php if (!$vouchers): ?><p class="muted">No vouchers redeemed in this range.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Code</th><th>Uses</th><th>Discount</th></tr></thead>
            <tbody>
            <?php foreach ($vouchers as $v): ?>
                <tr><td><code><?= e((string)$v['code']) ?></code></td><td><?= (int)$v['uses'] ?></td><td><?= money((float)$v['discount']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel" style="margin-top:18px">
    <div class="panel-head"><h3 style="margin:0">Inventory health (low stock)</h3>
        <span class="muted">Threshold-based, active &amp; tracked products</span></div>
    <?php if (!$lowStock): ?><p class="muted">Nothing below threshold — looking healthy.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Product</th><th>SKU</th><th>On hand</th><th>Threshold</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lowStock as $p): ?>
            <tr>
                <td><?= e($p['name']) ?></td>
                <td class="muted"><?= e($p['sku']) ?></td>
                <td><strong style="color:<?= (int)$p['stock_quantity'] <= 0 ? 'var(--terracotta)' : 'inherit' ?>"><?= (int)$p['stock_quantity'] ?></strong></td>
                <td class="muted"><?= (int)$p['low_stock_threshold'] ?></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/product_form.php?id=' . (int)$p['id']) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="panel" style="margin-top:18px">
    <div class="panel-head"><h3 style="margin:0">Exports</h3></div>
    <p class="muted">Downloads are scoped to the current date range, except subscriptions which include all records.</p>
    <p style="display:flex;gap:10px;flex-wrap:wrap;margin:0">
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/analytics.php?export=orders&from=' . $fromS . '&to=' . $toS) ?>">Orders CSV</a>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/analytics.php?export=products&from=' . $fromS . '&to=' . $toS) ?>">Sales by product CSV</a>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/analytics.php?export=commissions&from=' . $fromS . '&to=' . $toS) ?>">Commissions CSV</a>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/analytics.php?export=subscriptions') ?>">Subscriptions CSV</a>
    </p>
</div>
<?php admin_layout_end(); ?>
