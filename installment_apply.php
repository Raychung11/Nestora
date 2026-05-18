<?php
require_once __DIR__ . '/inc/functions.php';

$pageTitle = 'Apply for a Monthly Comfort Plan';
$pageDesc  = 'Bring comfort home today, pay comfortably over time.';

$eligible = db()->query(
    "SELECT id, name, slug, price, promo_price, max_installment_months
     FROM products
     WHERE status='active' AND installment_eligible=1
     ORDER BY is_featured DESC, name"
)->fetchAll();

$prefillSlug = input('product');
$done   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $productId = (int) input('product_id');
    $months    = (int) input('months');
    $name      = input('name');
    $phone     = input('phone');
    $email     = input('email');

    if (!in_array($months, [6, 12, 24], true)) {
        $errors[] = 'Please choose a 6, 12 or 24 month plan.';
    }
    if ($name === '')  { $errors[] = 'Please enter your name.'; }
    if ($phone === '') { $errors[] = 'Please enter a contact number.'; }

    $product = null;
    if ($productId > 0) {
        $ps = db()->prepare("SELECT * FROM products WHERE id = :id AND status='active' LIMIT 1");
        $ps->execute([':id' => $productId]);
        $product = $ps->fetch() ?: null;
    }
    if (!$product) {
        $errors[] = 'Please select a product available on a monthly comfort plan.';
    }

    if (!$errors && $product) {
        $total   = effective_price($product);
        $monthly = monthly_payment($total, $months);
        db()->prepare(
            "INSERT INTO installment_requests
             (customer_name, phone, email, product_summary, total_amount, months, monthly_payment, status)
             VALUES (:n,:p,:e,:sum,:tot,:m,:mp,'new')"
        )->execute([
            ':n' => $name, ':p' => $phone, ':e' => $email ?: null,
            ':sum' => $product['name'], ':tot' => $total,
            ':m' => (string) $months, ':mp' => $monthly,
        ]);
        $done = true;
        $confirm = ['name' => $name, 'product' => $product['name'], 'months' => $months,
                    'monthly' => $monthly, 'total' => $total];
    }
}

require_once __DIR__ . '/inc/header.php';
?>
<section class="band">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow" style="color:#e9c4ad">Monthly Comfort Plan</span>
        <h2>Apply in a minute</h2>
        <p><?= e(get_setting('installment_public_text', 'Bring comfort home today, pay comfortably over time.')) ?></p>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($done): ?>
            <div class="form-card" style="text-align:center">
                <h2 style="margin-bottom:12px">Request received</h2>
                <p class="muted">Thank you, <strong><?= e($confirm['name']) ?></strong>. Your plan request for
                    <strong><?= e($confirm['product']) ?></strong> &mdash; <?= (int)$confirm['months'] ?> months at
                    <strong><?= money($confirm['monthly']) ?>/month</strong> &mdash; is being reviewed by our Nestora team.</p>
                <a class="btn btn-primary btn-lg mt" href="<?= whatsapp_url('Hi Nestora, I just applied for a ' . $confirm['months'] . '-month comfort plan for ' . $confirm['product'] . '.') ?>" target="_blank" rel="noopener">Continue on WhatsApp</a>
            </div>
        <?php elseif (!$eligible): ?>
            <p class="muted" style="text-align:center">No items are currently on a monthly comfort plan.
                <a href="<?= whatsapp_url() ?>" target="_blank" rel="noopener" style="color:var(--terracotta)">Chat with us</a> to ask.</p>
        <?php else: ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error"><?= e($err) ?></div>
            <?php endforeach; ?>
            <form class="form-card" method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label>Choose a product</label>
                    <select name="product_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($eligible as $p):
                            $pr = (isset($p['promo_price']) && $p['promo_price'] > 0 && $p['promo_price'] < $p['price'])
                                ? (float)$p['promo_price'] : (float)$p['price']; ?>
                            <option value="<?= (int)$p['id'] ?>"
                                <?= ($prefillSlug === $p['slug'] || (int)input('product_id') === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> — <?= money($pr) ?> (up to <?= (int)$p['max_installment_months'] ?> mo)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Plan length</label>
                    <select name="months" required>
                        <?php foreach ([6, 12, 24] as $m): ?>
                            <option value="<?= $m ?>" <?= (int)input('months', 24) === $m ? 'selected' : '' ?>><?= $m ?> months</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Your name</label><input type="text" name="name" value="<?= e(input('name')) ?>" required></div>
                <div class="form-row">
                    <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" value="<?= e(input('phone')) ?>" required></div>
                    <div class="field"><label>Email (optional)</label><input type="email" name="email" value="<?= e(input('email')) ?>"></div>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Submit plan request</button>
                <p class="muted" style="font-size:.82rem;margin-top:14px">Monthly payment = product price &divide; number of months. All plans are subject to manual approval by our Nestora team.</p>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
