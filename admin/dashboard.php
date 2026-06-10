<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/inventory.php';
$admin = require_admin();
$pdo = db();

/* ---------- Small helpers --------------------------------------------- */
$scalar = static function (string $sql, array $params = []) use ($pdo) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? 0 : $v;
    } catch (Throwable $e) {
        return 0; // Tolerate missing tables on partially-migrated installs.
    }
};
$all = static function (string $sql, array $params = []) use ($pdo) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
};

/* ---------- Today / yesterday / month --------------------------------- */
$today      = date('Y-m-d');
$yesterday  = date('Y-m-d', strtotime('-1 day'));

$todayPaid     = (float) $scalar("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' AND DATE(created_at)=:d", [':d'=>$today]);
$yesterdayPaid = (float) $scalar("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' AND DATE(created_at)=:d", [':d'=>$yesterday]);
$todayOrders     = (int) $scalar("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=:d", [':d'=>$today]);
$yesterdayOrders = (int) $scalar("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=:d", [':d'=>$yesterday]);
$todayCustomers  = (int) $scalar("SELECT COUNT(*) FROM customers WHERE DATE(created_at)=:d", [':d'=>$today]);

$monthRevenue = (float) $scalar("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' AND MONTH(created_at)=MONTH(CURRENT_DATE()) AND YEAR(created_at)=YEAR(CURRENT_DATE())");
$monthOrders  = (int)   $scalar("SELECT COUNT(*) FROM orders WHERE payment_status='paid' AND MONTH(created_at)=MONTH(CURRENT_DATE()) AND YEAR(created_at)=YEAR(CURRENT_DATE())");

/* ---------- Subscriptions / MRR --------------------------------------- */
$activeSubs  = (int) $scalar("SELECT COUNT(*) FROM subscriptions WHERE status='active'");
$mrrRows     = $all("SELECT frequency, COALESCE(SUM(unit_price*quantity),0) AS amt FROM subscriptions WHERE status='active' GROUP BY frequency");
$monthsPer   = ['monthly'=>1,'bimonthly'=>2,'quarterly'=>3];
$mrr = 0.0;
foreach ($mrrRows as $r) { $mrr += (float)$r['amt'] / ($monthsPer[$r['frequency']] ?? 1); }
$dueSoon = (int) $scalar("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND next_renewal_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)");

/* ---------- Action queue --------------------------------------------- */
$paymentsToVerify = (int) $scalar("SELECT COUNT(*) FROM payment_proofs WHERE status='submitted'");
$newInquiries     = (int) $scalar("SELECT COUNT(*) FROM orders WHERE order_status IN ('new','pending_confirmation')");
$pendingSupp      = (int) $scalar("SELECT COUNT(*) FROM orders WHERE supplier_status IN ('not_started','checking')");
$quizLeads        = (int) $scalar("SELECT COUNT(*) FROM comfort_quiz_leads WHERE status='new'");
$instRequests     = (int) $scalar("SELECT COUNT(*) FROM installment_requests WHERE status IN ('new','reviewing')");
$waLeads          = (int) $scalar("SELECT COUNT(*) FROM whatsapp_leads WHERE status='new'");
$openPOs          = (int) $scalar("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('ordered','partial')");
$partnerApps      = (int) $scalar("SELECT COUNT(*) FROM partner_applications WHERE status='new'");

try { $lowStock = inventory_low_stock($pdo); } catch (Throwable $e) { $lowStock = []; }

/* ---------- 7-day series --------------------------------------------- */
$series = $all(
    "SELECT DATE(created_at) AS d,
            COUNT(*) AS n,
            COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END), 0) AS rev
     FROM orders
     WHERE DATE(created_at) >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)"
);
$byDay = [];
foreach ($series as $s) { $byDay[$s['d']] = $s; }
$days7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days7[] = [
        'd'   => $d,
        'n'   => (int)   ($byDay[$d]['n']   ?? 0),
        'rev' => (float) ($byDay[$d]['rev'] ?? 0),
    ];
}
$maxRev = max(1.0, max(array_column($days7, 'rev')));
$maxOrd = max(1,   max(array_column($days7, 'n')));

/* ---------- Listings ------------------------------------------------- */
$recentOrders = $all("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");

$topProducts = $all(
    "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.payment_status='paid'
       AND MONTH(o.created_at)=MONTH(CURRENT_DATE())
       AND YEAR(o.created_at)=YEAR(CURRENT_DATE())
     GROUP BY oi.product_name
     ORDER BY revenue DESC LIMIT 5"
);

$dueSubs = $all(
    "SELECT customer_name, product_name, next_renewal_date, frequency
     FROM subscriptions
     WHERE status='active'
       AND next_renewal_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
     ORDER BY next_renewal_date ASC LIMIT 8"
);

require_once __DIR__ . '/../inc/admin_layout.php';

/* ---------- Render helpers ------------------------------------------ */
$delta = static function (float $cur, float $prev): array {
    if ($prev == 0.0 && $cur == 0.0) { return ['', '#888']; }
    if ($prev == 0.0) { return ['new', '#2f6a3a']; }
    $diff = $cur - $prev;
    $pct  = (int) round(($diff / $prev) * 100);
    $col  = $diff > 0 ? '#2f6a3a' : ($diff < 0 ? '#b00020' : '#888');
    $arr  = $diff > 0 ? '&uarr;' : ($diff < 0 ? '&darr;' : '·');
    return [$arr . ' ' . abs($pct) . '%', $col];
};
[$revDelta, $revColor] = $delta($todayPaid, $yesterdayPaid);
[$ordDelta, $ordColor] = $delta((float)$todayOrders, (float)$yesterdayOrders);

$svgBars = static function (array $days, string $key, float $max, string $color): string {
    $w = 360; $h = 80; $n = count($days);
    if ($n === 0) { return ''; }
    $barW = ($w - 8) / $n - 2;
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" style="width:100%;height:80px;display:block">';
    $svg .= '<line x1="0" y1="' . ($h - 1) . '" x2="' . $w . '" y2="' . ($h - 1) . '" stroke="#e5d8c9"/>';
    foreach ($days as $i => $d) {
        $v  = (float) $d[$key];
        $bh = $max > 0 ? ($v / $max) * ($h - 12) : 0;
        $x  = 4 + $i * ($barW + 2);
        $y  = $h - 2 - $bh;
        $t  = date('D d M', strtotime($d['d'])) . ': '
            . ($key === 'rev' ? 'RM ' . number_format($v, 0) : (string) (int) $v);
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1)
              . '" width="' . round($barW, 1) . '" height="' . round($bh, 1)
              . '" fill="' . $color . '" rx="2">'
              . '<title>' . htmlspecialchars($t, ENT_QUOTES) . '</title></rect>';
    }
    return $svg . '</svg>';
};

$queue = [
    ['n'=>$paymentsToVerify, 'lbl'=>'payment proof'    .($paymentsToVerify===1?'':'s').' to verify',  'href'=>'/admin/payments.php?status=submitted',  'sev'=>'high'],
    ['n'=>$newInquiries,     'lbl'=>'new order inquir'.($newInquiries===1?'y':'ies'),                'href'=>'/admin/orders.php',                     'sev'=>'high'],
    ['n'=>$waLeads,          'lbl'=>'new WhatsApp lead'.($waLeads===1?'':'s'),                       'href'=>'/admin/whatsapp_leads.php',             'sev'=>'mid'],
    ['n'=>$quizLeads,        'lbl'=>'new quiz lead'    .($quizLeads===1?'':'s'),                     'href'=>'/admin/quiz_leads.php',                 'sev'=>'mid'],
    ['n'=>$instRequests,     'lbl'=>'installment request'.($instRequests===1?'':'s'),                'href'=>'/admin/installment_requests.php',       'sev'=>'mid'],
    ['n'=>$partnerApps,      'lbl'=>'partner application'.($partnerApps===1?'':'s'),                 'href'=>'/admin/partner_applications.php',       'sev'=>'mid'],
    ['n'=>count($lowStock),  'lbl'=>'low-stock product'.(count($lowStock)===1?'':'s'),               'href'=>'/admin/products.php',                   'sev'=>'mid'],
    ['n'=>$pendingSupp,      'lbl'=>'order'.($pendingSupp===1?'':'s').' awaiting supplier',          'href'=>'/admin/orders.php',                     'sev'=>'low'],
    ['n'=>$openPOs,          'lbl'=>'open purchase order'.($openPOs===1?'':'s'),                     'href'=>'/admin/purchase_orders.php',            'sev'=>'low'],
    ['n'=>$dueSoon,          'lbl'=>'subscription'.($dueSoon===1?'':'s').' due in 7 days',           'href'=>'/admin/subscriptions.php',              'sev'=>'low'],
];
$queue = array_values(array_filter($queue, static fn($q) => $q['n'] > 0));
$sevColor = ['high'=>'#b00020','mid'=>'#b97a1a','low'=>'#7a5a44'];
?>

<div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
    <div>
        <h2 style="margin:0">Welcome back, <?= e((string)($admin['name'] ?? 'Admin')) ?>.</h2>
        <p class="muted" style="margin:4px 0 0"><?= e(date('l, j F Y')) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/analytics.php') ?>">Analytics</a>
        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/orders.php') ?>">Orders</a>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/admin/product_form.php') ?>">+ Add product</a>
    </div>
</div>

<div class="stat-grid" style="margin-bottom:18px">
    <div class="stat">
        <div class="num"><?= money($todayPaid) ?></div>
        <div class="lbl">Revenue today <span style="color:<?= $revColor ?>;font-weight:600;margin-left:4px"><?= $revDelta ?></span></div>
    </div>
    <div class="stat">
        <div class="num"><?= $todayOrders ?></div>
        <div class="lbl">Orders today <span style="color:<?= $ordColor ?>;font-weight:600;margin-left:4px"><?= $ordDelta ?></span></div>
    </div>
    <div class="stat">
        <div class="num"><?= money($monthRevenue) ?></div>
        <div class="lbl"><?= e(date('F')) ?> revenue · <?= $monthOrders ?> paid</div>
    </div>
    <div class="stat">
        <div class="num"><?= money($mrr) ?></div>
        <div class="lbl">MRR · <?= $activeSubs ?> active sub<?= $activeSubs===1?'':'s' ?></div>
    </div>
    <div class="stat">
        <div class="num"><?= $todayCustomers ?></div>
        <div class="lbl">New customers today</div>
    </div>
</div>

<?php if ($queue): ?>
<div class="panel" style="margin-bottom:18px">
    <h3 style="margin:0 0 12px">Needs your attention</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
        <?php foreach ($queue as $q):
            $color = $sevColor[$q['sev']] ?? '#333'; ?>
            <a href="<?= base_url($q['href']) ?>" style="display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid var(--line);border-left:4px solid <?= $color ?>;border-radius:10px;background:#fff;color:inherit;text-decoration:none">
                <span style="font-size:1.7rem;font-weight:700;color:<?= $color ?>;min-width:36px;text-align:center"><?= (int)$q['n'] ?></span>
                <span style="color:#444"><?= e($q['lbl']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="flash flash-success" style="margin-bottom:18px">All caught up — no pending actions right now.</div>
<?php endif; ?>

<div class="panel" style="margin-bottom:18px">
    <div class="panel-head" style="margin-bottom:12px">
        <h3 style="margin:0">Last 7 days</h3>
        <a class="btn btn-soft btn-sm" href="<?= base_url('/admin/analytics.php?preset=7d') ?>">Open analytics &rarr;</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div>
            <p class="muted" style="margin:0 0 6px;font-size:.85rem">Daily revenue (paid orders)</p>
            <?= $svgBars($days7, 'rev', $maxRev, '#c46a4a') ?>
        </div>
        <div>
            <p class="muted" style="margin:0 0 6px;font-size:.85rem">Daily orders</p>
            <?= $svgBars($days7, 'n', (float)$maxOrd, '#7a5a44') ?>
        </div>
    </div>
</div>

<div class="panel" style="margin-bottom:18px">
    <div class="panel-head"><h3 style="margin:0">Recent orders</h3><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/orders.php') ?>">View all</a></div>
    <?php if (!$recentOrders): ?><p class="muted">No orders yet.</p><?php else: ?>
    <table class="table">
        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
            <tr>
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= money((float)$o['total_amount']) ?></td>
                <td><span class="badge badge-new"><?= e(label($o['order_status'])) ?></span></td>
                <td><span class="badge badge-<?= $o['payment_status']==='paid'?'paid':'pending' ?>"><?= e(label($o['payment_status'])) ?></span></td>
                <td class="muted"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/order_view.php?id=' . (int)$o['id']) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:18px;margin-bottom:18px;align-items:start">
    <div class="panel">
        <h3 style="margin:0 0 10px">Top products · <?= e(date('F')) ?></h3>
        <?php if (!$topProducts): ?><p class="muted">No paid orders this month yet.</p><?php else: ?>
        <table class="table">
            <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topProducts as $tp): ?>
                <tr><td><?= e($tp['product_name']) ?></td><td><?= (int)$tp['qty'] ?></td><td><strong><?= money((float)$tp['revenue']) ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3 style="margin:0 0 10px">Subscriptions due · next 7 days</h3>
        <?php if (!$dueSubs): ?><p class="muted">No renewals due in the next week.</p><?php else: ?>
        <table class="table">
            <thead><tr><th>Customer</th><th>Scent</th><th>Due</th></tr></thead>
            <tbody>
            <?php foreach ($dueSubs as $s): ?>
                <tr>
                    <td><?= e($s['customer_name']) ?></td>
                    <td class="muted"><?= e($s['product_name']) ?></td>
                    <td><?= e(date('d M', strtotime($s['next_renewal_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($lowStock): ?>
<div class="panel">
    <div class="panel-head"><h3 style="margin:0">Low stock</h3><span class="tag" style="background:#b97a1a;color:#fff"><?= count($lowStock) ?> item<?= count($lowStock) === 1 ? '' : 's' ?></span></div>
    <table class="table">
        <thead><tr><th>Product</th><th>SKU</th><th>In stock</th><th>Alert at</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lowStock as $ls): ?>
            <tr>
                <td><strong><?= e($ls['name']) ?></strong></td>
                <td class="muted"><?= e($ls['sku']) ?></td>
                <td><span class="badge badge-<?= (int)$ls['stock_quantity'] <= 0 ? 'unavailable' : 'preorder' ?>"><?= (int)$ls['stock_quantity'] ?></span></td>
                <td class="muted"><?= (int)$ls['low_stock_threshold'] ?></td>
                <td><a class="btn btn-soft btn-sm" href="<?= base_url('/admin/product_form.php?id=' . (int)$ls['id']) ?>">Restock</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php admin_layout_end(); ?>
