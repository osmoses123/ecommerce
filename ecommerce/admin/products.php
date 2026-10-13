<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    flash_set('success', 'Product deleted.');
    redirect('admin/products.php');
}

$products = db()->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();

ob_start();
?>
<div style="display:flex;justify-content:flex-end;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
  <a class="btn btn-brand" href="<?= e(url('admin/product-form.php')) ?>">Add product</a>
</div>
<div class="panel">
  <?php if (!$products): ?>
    <div class="empty">No products found.</div>
  <?php else: ?>
    <table class="data" id="products-table">
      <thead>
        <tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Flags</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><img class="thumb" src="<?= e(product_image_url($p['image'])) ?>" alt=""></td>
            <td><strong><?= e($p['name']) ?></strong><div class="muted" style="font-size:.8rem"><?= e($p['slug']) ?></div></td>
            <td><?= e($p['category_name'] ?? '—') ?></td>
            <td><?= e(money_ngn((int) $p['price'])) ?></td>
            <td><?= (int) $p['stock'] ?></td>
            <td>
              <?= $p['is_flash'] ? '<span class="status-pill status-pending">Flash</span> ' : '' ?>
              <?= $p['is_featured'] ? '<span class="status-pill status-processing">Featured</span>' : '' ?>
            </td>
            <td style="white-space:nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/product-form.php?id=' . $p['id'])) ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Products';
$adminPageSubtitle = 'Catalog management';
$adminActive = 'products.php';
require __DIR__ . '/_layout.php';
