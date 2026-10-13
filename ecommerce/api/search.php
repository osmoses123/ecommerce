<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

$like = '%' . $q . '%';
$stmt = db()->prepare('SELECT p.name, p.slug, p.price, p.image, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = \'active\' AND (p.name LIKE ? OR p.slug LIKE ? OR p.description LIKE ?) ORDER BY p.name ASC LIMIT 8');
$stmt->execute([$like, $like, $like]);
$items = [];
foreach ($stmt->fetchAll() as $row) {
    $items[] = [
        'name' => $row['name'],
        'category' => $row['category_name'] ?? '',
        'price' => money_ngn((int) $row['price']),
        'image' => product_image_url($row['image'] ?? null),
        'url' => url('product.php?slug=' . $row['slug']),
    ];
}
echo json_encode(['items' => $items]);
