<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$orders = db()->query('SELECT o.*, COALESCE(u.name, o.full_name) AS customer_name, COALESCE(u.email, o.guest_email) AS customer_email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC')->fetchAll();

ob_start();
?>
<div class="panel">
  <?php if (!$orders): ?>
    <div class="empty">No orders yet.</div>
  <?php else: ?>
    <table class="data" id="orders-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Total</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><strong><?= e($o['order_number']) ?></strong></td>
            <td><?= e($o['customer_name']) ?><div class="muted" style="font-size:.8rem"><?= e($o['customer_email']) ?></div></td>
            <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td>
              <?= e(payment_label((string) $o['payment_method'])) ?>
              <div class="muted" style="font-size:.78rem"><?= e(payment_status_label((string) ($o['payment_status'] ?? 'unpaid'))) ?></div>
            </td>
            <td><?= e(money_ngn((int) $o['total'])) ?></td>
            <td><?= e(date('d M Y H:i', strtotime($o['created_at']))) ?></td>
            <td><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Orders';
$adminPageSubtitle = 'Fulfillment queue';
$adminActive = 'orders.php';
require __DIR__ . '/_layout.php';
