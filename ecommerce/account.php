<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$user = require_user(url('account.php'));
$stmt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([(int) $user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'My account — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="section-head">
      <h2>Hello, <?= e(explode(' ', $user['name'])[0]) ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>">Log out</a>
    </div>
    <div class="panel" style="margin-bottom:1rem">
      <p style="margin:0"><strong><?= e($user['name']) ?></strong></p>
      <p class="muted" style="margin:.25rem 0 0"><?= e($user['email']) ?><?= $user['phone'] ? ' · ' . e($user['phone']) : '' ?></p>
    </div>
    <div class="panel">
      <h3 style="font-family:var(--font-display);margin:0 0 .75rem">Order history</h3>
      <?php if (!$orders): ?>
        <div class="empty-state" style="border:0;padding:1.5rem">No orders yet. <a href="<?= e(url('shop.php')) ?>">Start shopping</a></div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="text-align:left;color:var(--muted);font-size:.8rem">
                <th style="padding:.5rem 0">Order</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
                <tr style="border-top:1px solid var(--line)">
                  <td style="padding:.75rem 0"><a href="<?= e(url('track.php?order=' . urlencode($o['order_number']))) ?>"><strong><?= e($o['order_number']) ?></strong></a></td>
                  <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                  <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                  <td class="price"><?= e(money_ngn((int) $o['total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
