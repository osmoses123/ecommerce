<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT o.*, COALESCE(u.name, o.full_name) AS customer_name, COALESCE(u.email, o.guest_email) AS customer_email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    flash_set('error', 'Order not found.');
    redirect('admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($status, $allowed, true)) {
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash_set('success', 'Order status updated.');
        redirect('admin/order.php?id=' . $id);
    }
}

$items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();

ob_start();
?>
<div class="panel-grid">
  <div class="panel">
    <h2><?= e($order['order_number']) ?></h2>
    <p><span class="status-pill status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></p>
    <p><strong><?= e($order['customer_name']) ?></strong><br><?= e($order['customer_email']) ?><br><?= e($order['phone']) ?></p>
    <p><?= e($order['full_name']) ?><?= empty($order['user_id']) ? ' <span class="muted">(guest)</span>' : '' ?><br><?= e($order['address']) ?><br><?= e($order['city']) ?>, <?= e($order['state']) ?><?= !empty($order['country']) ? ', ' . e($order['country']) : '' ?></p>
    <p class="muted">
      Payment: <?= e(payment_label((string) $order['payment_method'])) ?>
      · <?= e(payment_status_label((string) ($order['payment_status'] ?? 'unpaid'))) ?>
      <?php if (!empty($order['payment_reference'])): ?> · Ref <?= e($order['payment_reference']) ?><?php endif; ?>
      · Placed <?= e(date('d M Y H:i', strtotime($order['created_at']))) ?>
    </p>
    <form method="post" style="margin-top:1rem;display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <div class="form-group" style="margin:0;min-width:180px">
        <label for="status">Update status</label>
        <select id="status" name="status">
          <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-brand" type="submit">Save</button>
    </form>
  </div>
  <div class="panel">
    <h2>Items</h2>
    <table class="data">
      <thead><tr><th>Product</th><th>Qty</th><th>Line</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['product_name']) ?><div class="muted" style="font-size:.8rem"><?= e(money_ngn((int) $it['unit_price'])) ?> each</div></td>
            <td><?= (int) $it['qty'] ?></td>
            <td><?= e(money_ngn((int) $it['line_total'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="summary-row" style="margin-top:1rem"><span>Subtotal</span><span><?= e(money_ngn((int) $order['subtotal'])) ?></span></div>
    <div class="summary-row"><span>Shipping</span><span><?= e(money_ngn((int) $order['shipping'])) ?></span></div>
    <div class="summary-row" style="font-family:var(--font-display);font-weight:800;font-size:1.1rem"><span>Total</span><span><?= e(money_ngn((int) $order['total'])) ?></span></div>
  </div>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Order detail';
$adminPageSubtitle = $order['order_number'];
$adminActive = 'orders.php';
require __DIR__ . '/_layout.php';
