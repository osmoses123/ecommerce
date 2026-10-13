<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');

$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = 'active'";
$params = [];

if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR p.slug LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($category !== '') {
    $sql .= ' AND c.slug = ?';
    $params[] = $category;
}

$sql .= match ($sort) {
    'price_asc' => ' ORDER BY p.price ASC',
    'price_desc' => ' ORDER BY p.price DESC',
    'discount' => ' ORDER BY CASE WHEN p.compare_price IS NOT NULL AND p.compare_price > p.price THEN (p.compare_price - p.price) / p.compare_price ELSE 0 END DESC, p.price ASC',
    default => ' ORDER BY p.created_at DESC',
};

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$cats = get_categories();

$pageTitle = 'Shop — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="section-head">
      <h2><?= $q !== '' ? 'Results for “' . e($q) . '”' : ($category !== '' ? e(ucwords(str_replace('-', ' ', $category))) : 'All products') ?></h2>
      <span class="muted"><?= count($products) ?> item<?= count($products) === 1 ? '' : 's' ?></span>
    </div>
    <form class="toolbar" method="get" action="<?= e(url('shop.php')) ?>">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <label class="sr-only" for="category">Category</label>
        <select id="category" name="category" class="form-control" style="width:auto;min-width:180px" onchange="this.form.submit()">
          <option value="">All categories</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $category === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="sr-only" for="sort">Sort</label>
        <select id="sort" name="sort" class="form-control" style="width:auto;min-width:160px" onchange="this.form.submit()">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
          <option value="discount" <?= $sort === 'discount' ? 'selected' : '' ?>>Biggest discount</option>
        </select>
      </div>
    </form>

    <?php if (!$products): ?>
      <div class="empty-state">
        <p>No products match your filters. Try another category or clear search.</p>
        <a class="btn btn-brand" href="<?= e(url('shop.php')) ?>">Clear filters</a>
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
