<?php
require_once __DIR__ . '/inc/functions.php';

$pageTitle = 'Your Cart';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = input('action');
    $pid    = (int) input('product_id');

    if ($action === 'add' && $pid > 0) {
        $stmt = db()->prepare("SELECT id FROM products WHERE id=:id AND status='active' LIMIT 1");
        $stmt->execute([':id' => $pid]);
        if ($stmt->fetchColumn()) {
            $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
            set_flash('success', 'Added to your comfort cart.');
        } else {
            set_flash('error', 'That item is not available.');
        }
    } elseif ($action === 'update' && $pid > 0) {
        $qty = max(0, (int) input('qty'));
        if ($qty === 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            $_SESSION['cart'][$pid] = min($qty, 99);
        }
        set_flash('success', 'Cart updated.');
    } elseif ($action === 'remove' && $pid > 0) {
        unset($_SESSION['cart'][$pid]);
        set_flash('success', 'Item removed.');
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        set_flash('info', 'Cart cleared.');
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

            <div class="cart-summary">
                <form method="post" data-confirm="Clear all items from your cart?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <button class="btn btn-soft" type="submit">Clear cart</button>
                </form>
                <div style="text-align:right">
                    <div class="muted">Estimated total</div>
                    <div style="font-family:var(--font-serif);font-size:1.8rem;color:var(--brown)"><?= money($total) ?></div>
                    <a class="btn btn-primary btn-lg mt" href="<?= base_url('/checkout.php') ?>">Proceed to order</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
