<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (wants_json()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'POST only']);
        exit;
    }
    redirect('shop.php');
}

if (wants_json()) {
    $token = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!verify_csrf($token)) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid session. Refresh the page.']);
        exit;
    }
} else {
    require_csrf();
}

$productId = (int) ($_POST['product_id'] ?? 0);
$variantId = (int) ($_POST['variant_id'] ?? 0);
$qty = max(1, (int) ($_POST['qty'] ?? 1));
$return = (string) ($_POST['return'] ?? url('cart.php'));

$stmt = db()->prepare('SELECT id, stock, name FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
$variant = null;
if ($product && $variantId > 0) {
    $vstmt = db()->prepare('SELECT * FROM product_variants WHERE id = ? AND product_id = ?');
    $vstmt->execute([$variantId, $productId]);
    $variant = $vstmt->fetch() ?: null;
}
$hasVariants = false;
if ($product) {
    $count = db()->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id = ?');
    $count->execute([$productId]);
    $hasVariants = (int) $count->fetchColumn() > 0;
}
$stock = $variant ? (int) $variant['stock'] : (int) ($product['stock'] ?? 0);
$buyNow = (string) ($_POST['action'] ?? '') === 'buy';
$error = '';
if (!$product || ($hasVariants && !$variant) || $stock < 1) {
    $error = $hasVariants && !$variant ? 'Choose a size, colour, or material first.' : 'That product is not available.';
    $buyNow = false;
} else {
    $addQty = min($qty, $stock);
    if ($buyNow) {
        cart_set($productId, $addQty, $variant ? $variantId : null);
    } else {
        cart_add($productId, $addQty, $variant ? $variantId : null);
        if (!wants_json()) {
            flash_set('success', $product['name'] . ' added to cart.');
        }
    }
}

if (wants_json() && !$buyNow) {
    header('Content-Type: application/json; charset=UTF-8');
    if ($error !== '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $error] + cart_snapshot());
        exit;
    }
    echo json_encode(['ok' => true, 'message' => $product['name'] . ' added to cart.'] + cart_snapshot());
    exit;
}

if ($error !== '') {
    flash_set('error', $error);
}

if ($buyNow && $error === '') {
    redirect('checkout.php');
}

if ($return === '' || !str_starts_with($return, '/')) {
    $return = url('cart.php');
}
header('Location: ' . $return);
exit;
