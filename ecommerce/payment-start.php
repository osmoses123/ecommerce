<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$orderNum = trim((string) ($_GET['order'] ?? ''));
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
$stmt->execute([$orderNum]);
$order = $stmt->fetch();
if (!$order) {
    flash_set('error', 'Order not found.');
    redirect('cart.php');
}

if (($order['payment_status'] ?? '') === 'paid') {
    redirect('order-success.php?order=' . urlencode($orderNum));
}

$method = (string) $order['payment_method'];
$available = payment_gateways_available();
if (!in_array($method, ['paystack', 'flutterwave'], true) || empty($available[$method])) {
    flash_set('error', 'That payment method is not available.');
    redirect('order-success.php?order=' . urlencode($orderNum) . '&mail=0');
}

$settings = get_settings(true);
$email = (string) ($order['guest_email'] ?: '');
if ($email === '' && !empty($order['user_id'])) {
    $u = db()->prepare('SELECT email FROM users WHERE id = ?');
    $u->execute([(int) $order['user_id']]);
    $email = (string) ($u->fetchColumn() ?: '');
}
if ($email === '') {
    flash_set('error', 'This order has no email for payment.');
    redirect('order-success.php?order=' . urlencode($orderNum));
}

$reference = $order['payment_reference'] ?: ($orderNum . '-' . strtoupper(bin2hex(random_bytes(3))));
db()->prepare('UPDATE orders SET payment_reference = ?, payment_status = ? WHERE id = ?')
    ->execute([$reference, 'pending', (int) $order['id']]);

$callback = absolute_url('payment-callback.php?gateway=' . $method . '&order=' . rawurlencode($orderNum));

if ($method === 'paystack') {
    $init = paystack_initialize($settings, $email, (int) $order['total'], $reference, $callback, [
        'order_number' => $orderNum,
        'order_id' => (int) $order['id'],
    ]);
} else {
    $init = flutterwave_initialize(
        $settings,
        $email,
        (string) $order['full_name'],
        (string) $order['phone'],
        (int) $order['total'],
        $reference,
        $callback
    );
}

if (!$init['ok']) {
    db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([(int) $order['id']]);
    flash_set('error', $init['message']);
    redirect('order-success.php?order=' . urlencode($orderNum) . '&pay=0');
}

header('Location: ' . $init['url']);
exit;
