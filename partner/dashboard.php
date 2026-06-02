<?php
require_once __DIR__ . '/../inc/partner_auth.php';
require_once __DIR__ . '/../inc/partners.php';

$session = require_partner();
$partner = current_partner_row();
if (!$partner) { partner_logout(); redirect(base_url('/partner/login.php')); }

$pdo = db();
$balance = partner_balance($pdo, (int) $partner['id']);
$pending = (float) $pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM partner_commissions WHERE partner_id=" . (int)$partner['id'] . " AND status='pending'")->fetchColumn();
$approved = (float) $pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM partner_commissions WHERE partner_id=" . (int)$partner['id'] . " AND status='approved'")->fetchColumn();
$referrals = (int) $pdo->query("SELECT COUNT(*) FROM partner_commissions WHERE partner_id=" . (int)$partner['id'])->fetchColumn();

$recentStmt = $pdo->prepare(
    'SELECT pc.*, o.order_number, o.customer_name, o.payment_status, o.order_status
     FROM partner_commissions pc
     JOIN orders o ON o.id = pc.order_id
     WHERE pc.partner_id = :pid
     ORDER BY pc.created_at DESC LIMIT 10'
);
$recentStmt->execute([':pid' => (int) $partner['id']]);
$recent = $recentStmt->fetchAll();

$refLink = site_origin() . base_url('/?ref=' . urlencode($partner['referral_code']));
$pageTitle = 'Partner Dashboard';
require_once __DIR__ . '/../inc/header.php';
?>
<section class="band-soft" style="padding:46px 0 24px">
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
                <span class="eyebrow">Partner Portal &middot; <?= e(label($partner['tier'])) ?> Partner</span>
                <h2 style="margin:4px 0 0">Welcome, <?= e($partner['name']) ?></h2>
            </div>
            <div class="actions-inline">
                <a class="btn btn-soft btn-sm" href="<?= base_url('/partner/shop.php') ?>">Wholesale shop</a>
                <a class="btn btn-soft btn-sm" href="<?= base_url('/partner/account.php') ?>">My account</a>
                <a class="btn btn-danger btn-sm" href="<?= base_url('/partner/logout.php') ?>">Sign out</a>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="stat-grid">
            <div class="stat"><div class="num"><?= $referrals ?></div><div class="lbl">Referred orders</div></div>
            <div class="stat"><div class="num"><?= money($approved) ?></div><div class="lbl">Approved commissions</div></div>
            <div class="stat"><div class="num"><?= money($pending) ?></div><div class="lbl">Pending commissions</div></div>
            <div class="stat"><div class="num"><?= money($balance) ?></div><div class="lbl">Balance due</div></div>
        </div>

        <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start">
            <div class="form-card" style="margin:0;max-width:none">
                <h3 style="margin-bottom:14px">Recent referred orders</h3>
                <?php if (!$recent): ?>
                    <p class="muted">No referred orders yet. Share your link and start earning.</p>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>Date</th><th>Order</th><th>Customer</th><th>Commission</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="muted"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                            <td><strong><?= e($r['order_number']) ?></strong></td>
                            <td><?= e($r['customer_name']) ?></td>
                            <td><strong><?= money((float)$r['commission_amount']) ?></strong></td>
                            <td><span class="tag"><?= e(label($r['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="form-card" style="margin:0;max-width:none">
                <h3 style="margin-bottom:14px">Your referral</h3>
                <p>Referral code<br><code style="font-size:1.1rem;letter-spacing:1px"><?= e($partner['referral_code']) ?></code></p>
                <p style="margin-top:12px">Referral link<br>
                    <a href="<?= e($refLink) ?>" target="_blank" rel="noopener"><?= e($refLink) ?></a></p>
                <p class="muted" style="font-size:.86rem;margin-top:14px">
                    Share the link or have customers enter your code at checkout.
                    Your <?= e((string)$partner['commission_rate']) ?>% commission accrues as <strong>pending</strong>
                    when the order is placed and becomes <strong>approved</strong> once the order is paid.
                </p>
                <p class="muted" style="font-size:.86rem;margin-top:14px">
                    You also enjoy <strong><?= e((string)$partner['wholesale_discount_rate']) ?>%</strong>
                    off retail on your own stock orders via the Wholesale Shop.
                </p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
