<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$gateway = (string) ($_GET['gateway'] ?? '');
$orderNum = trim((string) ($_GET['order'] ?? ''));
$settings = get_settings(true);

$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
$stmt->execute([$orderNum]);
$order = $stmt->fetch();
if (!$order) {
    flash_set('error', 'Order not found.');
    redirect('index.php');
}

if (($order['payment_status'] ?? '') === 'paid') {
    redirect('order-success.php?order=' . urlencode($orderNum));
}

$reference = (string) ($order['payment_reference'] ?: $orderNum);
if ($gateway === 'paystack') {
    $ref = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? $reference));
    $verify = paystack_verify($settings, $ref !== '' ? $ref : $reference);
} elseif ($gateway === 'flutterwave') {
    $ref = trim((string) ($_GET['tx_ref'] ?? $reference));
    $status = strtolower((string) ($_GET['status'] ?? ''));
    if (in_array($status, ['cancelled', 'failed'], true)) {
        db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([(int) $order['id']]);
        flash_set('error', 'Payment was cancelled or failed. You can try again from the order confirmation page.');
        redirect('order-success.php?order=' . urlencode($orderNum) . '&pay=0');
    }
    $verify = flutterwave_verify($settings, $ref !== '' ? $ref : $reference);
} else {
    flash_set('error', 'Unknown payment gateway.');
    redirect('order-success.php?order=' . urlencode($orderNum) . '&pay=0');
}

if (!$verify['ok']) {
    db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([(int) $order['id']]);
    flash_set('error', $verify['message']);
    redirect('order-success.php?order=' . urlencode($orderNum) . '&pay=0');
}

if ((int) ($verify['amount'] ?? 0) !== (int) $order['total']) {
    db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([(int) $order['id']]);
    flash_set('error', 'Paid amount does not match this order.');
    redirect('order-success.php?order=' . urlencode($orderNum) . '&pay=0');
}

mark_order_paid((int) $order['id'], (string) ($verify['reference'] ?? $reference));

$mailTo = (string) ($order['guest_email'] ?? '');
if ($mailTo === '' && !empty($order['user_id'])) {
    $u = db()->prepare('SELECT email FROM users WHERE id = ?');
    $u->execute([(int) $order['user_id']]);
    $mailTo = (string) ($u->fetchColumn() ?: '');
}
if ($mailTo !== '') {
    $body = "Hello {$order['full_name']},\n\nPayment received for Shopora order {$orderNum}.\nTotal: " . money_ngn((int) $order['total']) . "\nMethod: " . payment_label((string) $order['payment_method']) . "\n\nWe will call {$order['phone']} before dispatch.\n\n— Shopora";
    smtp_send($settings, $mailTo, 'Payment received ' . $orderNum, $body);
}

flash_set('success', 'Payment confirmed. Thank you.');
redirect('order-success.php?order=' . urlencode($orderNum) . '&paid=1');
