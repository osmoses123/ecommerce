<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $key = (string) ($_POST['line_key'] ?? '');
    if ($action === 'update') {
        cart_set_line($key, (int) ($_POST['qty'] ?? 0));
        flash_set('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        cart_remove_line($key);
        flash_set('success', 'Item removed.');
    }
    redirect('cart.php');
}

$lines = cart_lines();
$subtotal = cart_subtotal();
$shipping = $lines ? shipping_for($subtotal) : 0;
$total = $subtotal + $shipping;

$pageTitle = 'Cart — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="section-head"><h2>Your cart</h2></div>
    <?php if (!$lines): ?>
      <div class="empty-state">
        <p>Your cart is empty. Find something good on the shop floor.</p>
        <a class="btn btn-brand" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <div class="panel">
          <?php foreach ($lines as $line): $p = $line['product']; ?>
            <div class="cart-line">
              <img src="<?= e(product_image_url($p['image'])) ?>" alt="">
              <div>
                <strong><a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>"><?= e($p['name']) ?></a></strong>
                <?php if ($line['variant_label'] !== ''): ?><p class="muted" style="margin:.15rem 0"><?= e($line['variant_label']) ?></p><?php endif; ?>
                <p class="price" style="margin:.25rem 0">
                  <?= e(money_ngn($line['unit_price'])) ?>
                  <?php if ($line['unit_price'] < $line['base_price']): ?><span class="price-old"><?= e(money_ngn($line['base_price'])) ?></span><?php endif; ?>
                </p>
                <form method="post" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                  <?= csrf_field() ?>
                  <input type="hidden" name="line_key" value="<?= e($line['key']) ?>">
                  <input type="hidden" name="action" value="update">
                  <label class="sr-only" for="qty-<?= e($line['key']) ?>">Quantity</label>
                  <input class="form-control" style="width:72px" type="number" id="qty-<?= e($line['key']) ?>" name="qty" value="<?= (int) $line['qty'] ?>" min="1" max="<?= (int) $line['stock'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Update</button>
                </form>
                <form method="post" style="margin-top:.35rem">
                  <?= csrf_field() ?>
                  <input type="hidden" name="line_key" value="<?= e($line['key']) ?>">
                  <input type="hidden" name="action" value="remove">
                  <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--deal)">Remove</button>
                </form>
              </div>
              <div class="price"><?= e(money_ngn($line['line_total'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <aside class="panel">
          <h3 style="font-family:var(--font-display);margin:0 0 .75rem">Order summary</h3>
          <div class="summary-row"><span>Subtotal</span><span class="price"><?= e(money_ngn($subtotal)) ?></span></div>
          <div class="summary-row"><span>Shipping</span><span><?= $shipping === 0 ? 'Free' : e(money_ngn($shipping)) ?></span></div>
          <?php if ($subtotal < SHIPPING_FREE_OVER): ?>
            <p class="muted" style="font-size:.82rem">Free shipping over <?= e(money_ngn(SHIPPING_FREE_OVER)) ?>.</p>
          <?php endif; ?>
          <div class="summary-row total"><span>Total</span><span><?= e(money_ngn($total)) ?></span></div>
          <a class="btn btn-brand btn-block" style="margin-top:1rem" href="<?= e(url('checkout.php')) ?>">Checkout</a>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
