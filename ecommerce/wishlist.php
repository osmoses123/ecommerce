<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$ids = wishlist_ids();
$products = [];
if ($ids) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN ($place) AND p.status = 'active'");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
}

$pageTitle = 'Wishlist — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="section-head"><h2>Wishlist</h2></div>
    <?php if (!$products): ?>
      <div class="empty-state">
        <p>Nothing saved yet. Tap the heart on a product to keep it here.</p>
        <a class="btn btn-brand" href="<?= e(url('shop.php')) ?>">Browse the shop</a>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $p): ?>
          <?= render_product_card($p) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
