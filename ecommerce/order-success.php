<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$orderNum = trim((string) ($_GET['order'] ?? ''));
$mailFailed = isset($_GET['mail']) && $_GET['mail'] === '0';
$paid = isset($_GET['paid']) && $_GET['paid'] === '1';
$payFailed = isset($_GET['pay']) && $_GET['pay'] === '0';

$order = null;
if ($orderNum !== '') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
    $stmt->execute([$orderNum]);
    $order = $stmt->fetch() ?: null;
}

$pageTitle = 'Order placed — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="auth-card" style="max-width:520px;text-align:center">
      <?php if ($paid || ($order && ($order['payment_status'] ?? '') === 'paid')): ?>
        <p style="color:var(--success);font-weight:700;margin:0 0 .35rem">Payment confirmed</p>
        <h1 style="font-family:var(--font-display)">Thank you</h1>
      <?php else: ?>
        <p style="color:var(--success);font-weight:700;margin:0 0 .35rem">Order confirmed</p>
        <h1 style="font-family:var(--font-display)">Thank you</h1>
      <?php endif; ?>
      <?php if ($orderNum !== ''): ?>
        <p>Your order number is <strong><?= e($orderNum) ?></strong>.</p>
      <?php endif; ?>
      <?php if ($order): ?>
        <p class="muted">Payment: <?= e(payment_label((string) $order['payment_method'])) ?> · <?= e(payment_status_label((string) ($order['payment_status'] ?? 'unpaid'))) ?></p>
      <?php endif; ?>
      <?php if ($payFailed && $order && in_array($order['payment_method'], ['paystack', 'flutterwave'], true)): ?>
        <p class="muted">Online payment did not complete. You can try again.</p>
        <a class="btn btn-brand" href="<?= e(url('payment-start.php?order=' . urlencode($orderNum))) ?>">Pay now</a>
      <?php elseif ($order && ($order['payment_status'] ?? '') === 'pending' && in_array($order['payment_method'], ['paystack', 'flutterwave'], true)): ?>
        <p class="muted">Finish payment to confirm this order.</p>
        <a class="btn btn-brand" href="<?= e(url('payment-start.php?order=' . urlencode($orderNum))) ?>">Continue to payment</a>
      <?php elseif (!$order || ($order['payment_method'] ?? '') === 'pay_on_delivery'): ?>
        <p class="muted">We will call you before dispatch. Payment is on delivery.</p>
      <?php else: ?>
        <p class="muted">We will call you before dispatch.</p>
      <?php endif; ?>
      <?php if ($mailFailed): ?>
        <p class="muted" style="font-size:.9rem">We could not email the confirmation (SMTP not configured or send failed). Your order is still placed.</p>
      <?php endif; ?>
      <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-top:1.25rem">
        <a class="btn btn-ghost" href="<?= e(url('account.php')) ?>">View orders</a>
        <a class="btn btn-ghost" href="<?= e(url('shop.php')) ?>">Keep shopping</a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
