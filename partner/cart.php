<?php
require_once __DIR__ . '/../inc/partner_auth.php';
require_once __DIR__ . '/../inc/partners.php';
require_once __DIR__ . '/../inc/inventory.php';

$session = require_partner();
$partner = current_partner_row();
if (!$partner) { partner_logout(); redirect(base_url('/partner/login.php')); }

if (!isset($_SESSION['partner_cart']) || !is_array($_SESSION['partner_cart'])) {
    $_SESSION['partner_cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    $pid    = (int) input('product_id');

    if ($action === 'add' && $pid > 0) {
        $stmt = db()->prepare("SELECT * FROM products WHERE id=:id AND status='active' LIMIT 1");
        $stmt->execute([':id' => $pid]);
        $prod = $stmt->fetch();
        $qty  = max(1, min(999, (int) input('qty', 1)));
        if (!$prod) {
            set_flash('error', 'That item is not available.');
        } else {
            $newQty = ($_SESSION['partner_cart'][$pid] ?? 0) + $qty;
            if (!inventory_in_stock($prod, $newQty)) {
                set_flash('error', 'Not enough stock for the requested quantity.');
            } else {
                $_SESSION['partner_cart'][$pid] = $newQty;
                set_flash('success', 'Added to wholesale cart.');
            }
        }
    } elseif ($action === 'update' && $pid > 0) {
        $qty = max(0, min(999, (int) input('qty')));
        if ($qty === 0) {
            unset($_SESSION['partner_cart'][$pid]);
        } else {
            $_SESSION['partner_cart'][$pid] = $qty;
        }
        set_flash('success', 'Wholesale cart updated.');
    } elseif ($action === 'remove' && $pid > 0) {
        unset($_SESSION['partner_cart'][$pid]);
        set_flash('success', 'Item removed.');
    } elseif ($action === 'clear') {
        $_SESSION['partner_cart'] = [];
        set_flash('info', 'Wholesale cart cleared.');
    }
    redirect(base_url('/partner/cart.php'));
}

$rate = (float) $partner['wholesale_discount_rate'];
$items = [];
$total = 0.0;
if ($_SESSION['partner_cart']) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['partner_cart'])));
    $rows = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $qty   = (int) $_SESSION['partner_cart'][$r['id']];
        $price = partner_wholesale_price($r, $rate);
        $line  = $price * $qty;
        $total += $line;
        $items[] = ['p' => $r, 'qty' => $qty, 'price' => $price, 'line' => $line];
    }
}

$pageTitle = 'Wholesale Cart';
require_once __DIR__ . '/../inc/header.php';
?>
<section class="band-soft" style="padding:46px 0 18px">
    <div class="container">
        <span class="eyebrow">Partner Portal &middot; Wholesale</span>
        <h2 style="margin:4px 0 6px">Wholesale cart</h2>
        <p class="muted"><a href="<?= base_url('/partner/shop.php') ?>" style="color:var(--terracotta)">&larr; Continue shopping</a></p>
    </div>
</section>
<section>
    <div class="container">
        <?php if (!$items): ?>
            <p class="muted" style="text-align:center">Your wholesale cart is empty. <a href="<?= base_url('/partner/shop.php') ?>" style="color:var(--terracotta)">Browse the wholesale shop &rarr;</a></p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Item</th><th>Wholesale</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): $p = $it['p']; ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong><br><span class="muted" style="font-size:.82rem"><?= e($p['sku']) ?></span></td>
                    <td><?= money($it['price']) ?></td>
                    <td>
                        <form method="post" style="display:flex;gap:6px;align-items:center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <input type="number" name="qty" value="<?= $it['qty'] ?>" min="0" max="999" style="width:80px;padding:8px">
                            <button class="btn btn-soft btn-sm" type="submit">Update</button>
                        </form>
                    </td>
                    <td><?= money($it['line']) ?></td>
                    <td>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="cart-summary">
            <form method="post" data-confirm="Clear the wholesale cart?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-soft" type="submit">Clear cart</button>
            </form>
            <div style="text-align:right">
                <div class="muted">Wholesale total</div>
                <div style="font-family:var(--font-serif);font-size:1.8rem;color:var(--brown)"><?= money($total) ?></div>
                <a class="btn btn-primary btn-lg mt" href="<?= base_url('/partner/checkout.php') ?>">Proceed to wholesale checkout</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
