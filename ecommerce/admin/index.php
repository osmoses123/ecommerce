<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$revenue = (int) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$ordersToday = (int) db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$customerCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$recent = db()->query('SELECT o.*, COALESCE(u.name, o.full_name) AS customer_name FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 8')->fetchAll();
$lowStock = db()->query('SELECT id, name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC, name ASC LIMIT 10')->fetchAll();

ob_start();
?>
<div class="stats">
  <div class="stat-card"><span>Revenue</span><strong><?= e(money_ngn($revenue)) ?></strong></div>
  <div class="stat-card"><span>Orders today</span><strong><?= $ordersToday ?></strong></div>
  <div class="stat-card"><span>Products</span><strong><?= $productCount ?></strong></div>
  <div class="stat-card"><span>Customers</span><strong><?= $customerCount ?></strong></div>
</div>
<div class="panel-grid">
  <div class="panel">
    <h2>Recent orders</h2>
    <?php if (!$recent): ?>
      <div class="empty">No orders yet.</div>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $o): ?>
            <tr>
              <td><a href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a></td>
              <td><?= e($o['customer_name']) ?></td>
              <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
              <td><?= e(money_ngn((int) $o['total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2>Low stock (≤ 5)</h2>
    <?php if (!$lowStock): ?>
      <div class="empty">All products have healthy stock.</div>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Product</th><th>Stock</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $p): ?>
            <tr>
              <td><a href="<?= e(url('admin/product-form.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a></td>
              <td><?= (int) $p['stock'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Dashboard';
$adminPageSubtitle = 'Store performance at a glance';
$adminActive = 'index.php';
require __DIR__ . '/_layout.php';
