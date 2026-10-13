<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$customers = db()->query('SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count FROM users u ORDER BY u.created_at DESC')->fetchAll();

ob_start();
?>
<div class="panel">
  <?php if (!$customers): ?>
    <div class="empty">No customers yet.</div>
  <?php else: ?>
    <table class="data" id="customers-table">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td><?= e($c['email']) ?></td>
            <td><?= e($c['phone'] ?? '—') ?></td>
            <td><?= (int) $c['order_count'] ?></td>
            <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Customers';
$adminPageSubtitle = 'Registered shoppers';
$adminActive = 'customers.php';
require __DIR__ . '/_layout.php';
