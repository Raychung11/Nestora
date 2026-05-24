<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/vouchers.php';
require_once __DIR__ . '/inc/inventory.php';

$pageTitle = 'Your Cart';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    $pid    = (int) input('product_id');

    if ($action === 'add' && $pid > 0) {
        $stmt = db()->prepare("SELECT * FROM products WHERE id=:id AND status='active' LIMIT 1");
        $stmt->execute([':id' => $pid]);
        $prod = $stmt->fetch();
        if (!$prod) {
            set_flash('error', 'That item is not available.');
        } else {
            $wanted = ($_SESSION['cart'][$pid] ?? 0) + 1;
            if (!inventory_in_stock($prod, $wanted)) {
                set_flash('error', 'Sorry, there is not enough stock for that quantity.');
            } else {
                $_SESSION['cart'][$pid] = $wanted;
                set_flash('success', 'Added to your comfort cart.');
            }
        }
    } elseif ($action === 'update' && $pid > 0) {
        $qty = max(0, (int) input('qty'));
        if ($qty === 0) {
            unset($_SESSION['cart'][$pid]);
            set_flash('success', 'Cart updated.');
        } else {
            $qty = min($qty, 99);
            $stmt = db()->prepare("SELECT * FROM products WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $pid]);
            $prod = $stmt->fetch();
            $remaining = $prod ? inventory_remaining($prod) : null;
            if ($remaining !== null && $qty > $remaining) {
                $qty = max(0, $remaining);
                set_flash('info', 'Quantity adjusted to the stock available.');
            } else {
                set_flash('success', 'Cart updated.');
            }
            if ($qty > 0) {
                $_SESSION['cart'][$pid] = $qty;
            } else {
                unset($_SESSION['cart'][$pid]);
            }
        }
    } elseif ($action === 'remove' && $pid > 0) {
        unset($_SESSION['cart'][$pid]);
        set_flash('success', 'Item removed.');
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        voucher_clear();
        set_flash('info', 'Cart cleared.');
    } elseif ($action === 'apply_voucher') {
        $res = voucher_apply(input('voucher_code'), cart_subtotal());
        set_flash($res['ok'] ? 'success' : 'error', $res['message']);
    } elseif ($action === 'remove_voucher') {
        voucher_clear();
        set_flash('info', 'Discount removed.');
    }
    redirect(base_url('/cart.php'));
}

// Build cart view
$items = [];
$total = 0.0;
if ($_SESSION['cart']) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $rows = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $qty   = (int) $_SESSION['cart'][$r['id']];
        $price = effective_price($r);
        $line  = $price * $qty;
        $total += $line;
        $items[] = ['p' => $r, 'qty' => $qty, 'price' => $price, 'line' => $line];
    }
}

require_once __DIR__ . '/inc/header.php';
?>
<section>
    <div class="container">
        <div class="section-head"><span class="eyebrow">Comfort Cart</span><h2>Your selection</h2></div>

        <?php if (!$items): ?>
            <p class="muted" style="text-align:center">Your cart is gently empty.
                <a href="<?= base_url('/products.php?type=furniture') ?>" style="color:var(--terracotta)">Discover comfort &rarr;</a></p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): $p = $it['p']; ?>
                    <tr>
                        <td>
                            <a href="<?= base_url('/product.php?slug=' . urlencode($p['slug'])) ?>"><strong><?= e($p['name']) ?></strong></a><br>
                            <span class="muted" style="font-size:.82rem"><?= e($p['sku']) ?></span>
                        </td>
                        <td><?= money($it['price']) ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:6px;align-items:center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <input type="number" name="qty" value="<?= $it['qty'] ?>" min="0" max="99" style="width:74px;padding:8px">
                                <button class="btn btn-soft btn-sm" type="submit">Update</button>
                            </form>
                        </td>
                        <td><?= money($it['line']) ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php $vc = voucher_current($total); $discount = $vc['discount'] ?? 0.0; $grand = max(0, $total - $discount); ?>
            <div class="cart-summary">
                <div>
                    <form method="post" data-confirm="Clear all items from your cart?" style="margin-bottom:14px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                        <button class="btn btn-soft" type="submit">Clear cart</button>
                    </form>
                    <?php if ($vc): ?>
                        <form method="post" style="display:flex;gap:8px;align-items:center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_voucher">
                            <span class="tag">Code <?= e($vc['code']) ?> &minus;<?= money($discount) ?></span>
                            <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:flex;gap:8px;align-items:center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="apply_voucher">
                            <input type="text" name="voucher_code" placeholder="Discount code" style="width:160px;padding:8px;text-transform:uppercase">
                            <button class="btn btn-soft btn-sm" type="submit">Apply</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div style="text-align:right">
                    <div class="muted">Subtotal: <?= money($total) ?></div>
                    <?php if ($discount > 0): ?>
                        <div class="muted" style="color:var(--terracotta)">Discount: &minus;<?= money($discount) ?></div>
                    <?php endif; ?>
                    <div class="muted" style="margin-top:6px">Estimated total</div>
                    <div style="font-family:var(--font-serif);font-size:1.8rem;color:var(--brown)"><?= money($grand) ?></div>
                    <a class="btn btn-primary btn-lg mt" href="<?= base_url('/checkout.php') ?>">Proceed to order</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
