<?php
declare(strict_types=1);
/** @var string $adminPageTitle */
/** @var string $adminPageSubtitle */
/** @var string $adminActive */
/** @var string $adminContent */

$admin = require_admin();
$settings = get_settings();
$logoSrc = logo_url($settings['logo_path'] ?? null);
$flash = flash_get();
$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminPageSubtitle = $adminPageSubtitle ?? '';
$adminActive = $adminActive ?? '';

$nav = [
    'index.php' => 'Dashboard',
    'products.php' => 'Products',
    'categories.php' => 'Categories',
    'variations.php' => 'Variations',
    'homepage.php' => 'Homepage',
    'footer.php' => 'Header & footer',
    'locations.php' => 'Locations',
    'orders.php' => 'Orders',
    'customers.php' => 'Customers',
    'appearance.php' => 'Appearance',
    'payments.php' => 'Payments',
    'smtp.php' => 'SMTP',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($adminPageTitle) ?> — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
  <style>:root { --brand: <?= e($settings['primary_color'] ?: '#F05A1A') ?>; }</style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar__brand">
      <?php if ($logoSrc): ?><img src="<?= e($logoSrc) ?>" alt=""><?php endif; ?>
      Shopora Admin
    </div>
    <nav class="admin-nav">
      <?php foreach ($nav as $file => $label): ?>
        <a class="<?= $adminActive === $file ? 'is-active' : '' ?>" href="<?= e(url('admin/' . $file)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <div class="nav-sep">Links</div>
      <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">View storefront</a>
      <a href="<?= e(url('admin/logout.php')) ?>">Logout</a>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="admin-top">
      <div>
        <h1><?= e($adminPageTitle) ?></h1>
        <?php if ($adminPageSubtitle !== ''): ?><p><?= e($adminPageSubtitle) ?></p><?php endif; ?>
      </div>
      <div style="color:var(--muted);font-size:.9rem"><?= e($admin['name']) ?></div>
    </div>
    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $adminContent ?? '' ?>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="<?= e(url('assets/js/admin.js')) ?>"></script>
</body>
</html>
