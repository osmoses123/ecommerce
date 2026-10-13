<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$result = null;
$error = '';
$orderNum = trim((string) ($_GET['order'] ?? $_POST['order'] ?? ''));

if ($orderNum !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
    }
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
    $stmt->execute([$orderNum]);
    $order = $stmt->fetch();
    $user = current_user();
    if (!$order) {
        $error = 'No order found with that number.';
    } elseif ($user && (int) $order['user_id'] !== (int) $user['id'] && empty($_SESSION['admin_id'])) {
        // Public track by unguessable order number is OK per brief
        $result = $order;
    } else {
        $result = $order;
    }
    if ($order && !$result) {
        $result = $order;
    }
}

$myOrders = [];
if ($user = current_user()) {
    $st = db()->prepare('SELECT order_number, status, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
    $st->execute([(int) $user['id']]);
    $myOrders = $st->fetchAll();
}

$pageTitle = 'Track order — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container" style="max-width:720px">
    <div class="section-head"><h2>Track order</h2></div>
    <div class="panel" style="margin-bottom:1rem">
      <form method="get">
        <div class="form-group">
          <label for="order">Order number</label>
          <input class="form-control" id="order" name="order" placeholder="SHP-XXXXXXXXXX" value="<?= e($orderNum) ?>" required>
        </div>
        <button class="btn btn-brand" type="submit">Track</button>
      </form>
    </div>
    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($result): ?>
      <div class="panel">
        <p style="margin:0 0 .35rem"><strong><?= e($result['order_number']) ?></strong></p>
        <p><span class="status-pill status-<?= e($result['status']) ?>"><?= e(status_label($result['status'])) ?></span></p>
        <p class="muted">Placed <?= e(date('d M Y H:i', strtotime($result['created_at']))) ?> · <?= e(money_ngn((int) $result['total'])) ?></p>
        <p><?= e($result['city']) ?>, <?= e($result['state']) ?><?= !empty($result['country']) ? ', ' . e($result['country']) : '' ?></p>
      </div>
    <?php endif; ?>
    <?php if ($myOrders): ?>
      <div class="panel" style="margin-top:1rem">
        <h3 style="font-family:var(--font-display);margin:0 0 .75rem">Your recent orders</h3>
        <ul style="list-style:none;margin:0;padding:0">
          <?php foreach ($myOrders as $o): ?>
            <li style="padding:.5rem 0;border-bottom:1px solid var(--line)">
              <a href="<?= e(url('track.php?order=' . urlencode($o['order_number']))) ?>"><strong><?= e($o['order_number']) ?></strong></a>
              <span class="status-pill status-<?= e($o['status']) ?>" style="margin-left:.5rem"><?= e(status_label($o['status'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
