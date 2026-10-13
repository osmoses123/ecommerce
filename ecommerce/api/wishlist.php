<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST only']);
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!verify_csrf($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid session. Refresh the page.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);
$stmt = db()->prepare('SELECT id FROM products WHERE id = ? AND status = \'active\'');
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Product not found.']);
    exit;
}

$on = wishlist_toggle($productId);
echo json_encode([
    'ok' => true,
    'wished' => $on,
    'count' => wishlist_count(),
]);
