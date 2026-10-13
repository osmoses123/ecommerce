<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$stmt = db()->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.status = 'active'");
$stmt->execute([$slug]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="page-pad"><div class="container"><div class="empty-state"><p>That product is not available.</p><a class="btn btn-brand" href="' . e(url('shop.php')) . '">Back to shop</a></div></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$rel = db()->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' LIMIT 4");
$rel->execute([(int) $product['category_id'], (int) $product['id']]);
$related = $rel->fetchAll();

$sponsored = db()->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_sponsored = 1 AND p.status = 'active' AND p.id != ? ORDER BY p.id DESC LIMIT 4");
$sponsored->execute([(int) $product['id']]);
$sponsored = $sponsored->fetchAll();

$images = product_gallery($product);
$variantRows = product_variant_rows((int) $product['id']);
$tiers = product_tiers((int) $product['id']);
$variantJson = [];
foreach ($variantRows as $row) {
    $variantJson[] = [
        'id' => (int) $row['id'],
        'size' => (int) ($row['size_id'] ?? 0),
        'colour' => (int) ($row['colour_id'] ?? 0),
        'material' => (int) ($row['material_id'] ?? 0),
        'size_label' => (string) ($row['size_label'] ?? ''),
        'colour_label' => (string) ($row['colour_label'] ?? ''),
        'material_label' => (string) ($row['material_label'] ?? ''),
        'price' => $row['price'] !== null ? (int) $row['price'] : (int) $product['price'],
        'stock' => (int) $row['stock'],
        'sku' => (string) ($row['sku'] ?? ''),
    ];
}
$usedKinds = ['size' => false, 'colour' => false, 'material' => false];
foreach ($variantJson as $row) {
    foreach ($usedKinds as $kind => $_) {
        if ($row[$kind] > 0) {
            $usedKinds[$kind] = true;
        }
    }
}
$kindOptions = ['size' => [], 'colour' => [], 'material' => []];
foreach ($variantJson as $row) {
    foreach ($kindOptions as $kind => $_) {
        if ($row[$kind] > 0) {
            $kindOptions[$kind][$row[$kind]] = $row[$kind . '_label'];
        }
    }
}
$pct = discount_percent((int) $product['price'], $product['compare_price'] !== null ? (int) $product['compare_price'] : null);
$wa = whatsapp_product_url($product);
$wished = wishlist_has((int) $product['id']);
$cats = get_categories();
$pageTitle = $product['name'] . ' — ' . get_settings()['site_title'];
$metaDescription = mb_substr(trim(strip_tags((string) $product['description'])), 0, 155);
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="pdp-shell">
      <aside class="shop-side">
        <h2>Categories</h2>
        <ul>
          <?php foreach ($cats as $cat): ?>
            <li><a href="<?= e(url('shop.php?category=' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($sponsored): ?>
          <h2>Sponsored</h2>
          <div class="side-products">
            <?php foreach ($sponsored as $sp): ?>
              <a class="side-product" href="<?= e(url('product.php?slug=' . $sp['slug'])) ?>">
                <img src="<?= e(product_image_url($sp['image'])) ?>" alt="">
                <span>
                  <strong><?= e($sp['name']) ?></strong>
                  <em><?= e(money_ngn((int) $sp['price'])) ?></em>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </aside>

      <div class="pdp">
        <div class="pdp__media">
          <div class="zoom-stage" id="zoom-stage">
            <?php if ($pct !== null): ?><span class="badge-deal">-<?= $pct ?>%</span><?php endif; ?>
            <img id="pdp-photo" src="<?= e(product_image_url($images[0] ?? $product['image'])) ?>" alt="<?= e($product['name']) ?>">
            <div class="zoom-lens" id="zoom-lens" hidden></div>
          </div>
          <?php if (count($images) > 1): ?>
            <div class="pdp-thumbs">
              <?php foreach ($images as $i => $path): ?>
                <button type="button" class="pdp-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= e(product_image_url($path)) ?>">
                  <img src="<?= e(product_image_url($path)) ?>" alt="">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="pdp__info">
          <p class="muted"><a href="<?= e(url('shop.php?category=' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a></p>
          <h1><?= e($product['name']) ?></h1>
          <p class="product-card__rating"><span class="stars">★</span> <?= e(number_format((float) $product['rating'], 1)) ?> <span class="muted">(<?= (int) $product['review_count'] ?> reviews)</span></p>
          <p class="pdp__price price" id="pdp-price" data-base="<?= (int) $product['price'] ?>">
            <?= e(money_ngn((int) $product['price'])) ?>
            <?php if ($product['compare_price'] && (int) $product['compare_price'] > (int) $product['price']): ?>
              <span class="price-old"><?= e(money_ngn((int) $product['compare_price'])) ?></span>
            <?php endif; ?>
          </p>
          <?php if ($tiers): ?>
            <ul class="tier-notes">
              <?php foreach ($tiers as $tier): ?>
                <li>Buy <?= (int) $tier['min_qty'] ?> or more and save <?= $tier['discount_type'] === 'percent' ? (int) $tier['discount_value'] . '%' : e(money_ngn((int) $tier['discount_value'])) ?> each</li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <p>
            <?php if ((int) $product['stock'] > 0 || $variantJson): ?>
              <span id="pdp-stock" style="color:var(--success);font-weight:600"><?= $variantJson ? 'Choose options' : ((int) $product['stock'] . ' in stock') ?></span>
            <?php else: ?>
              <span id="pdp-stock" style="color:var(--deal);font-weight:600">Out of stock</span>
            <?php endif; ?>
            <?php if (!empty($product['free_delivery'])): ?>
              <span class="pill-free">Free delivery</span>
            <?php endif; ?>
          </p>
          <?php if ((int) $product['stock'] > 0 || $variantJson): ?>
          <form method="post" action="<?= e(url('cart-add.php')) ?>" class="pdp-buy" id="pdp-form">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="variant_id" id="variant_id" value="">
            <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
            <?php if ($variantJson): ?>
              <div class="variant-picks">
                <?php foreach (['size' => 'Size', 'colour' => 'Colour', 'material' => 'Material'] as $kind => $label): ?>
                  <?php if (!$usedKinds[$kind]) continue; ?>
                  <label>
                    <?= e($label) ?>
                    <select class="form-control" data-variant="<?= e($kind) ?>">
                      <option value="">Choose</option>
                      <?php foreach ($kindOptions[$kind] as $optId => $optLabel): ?>
                        <option value="<?= (int) $optId ?>"><?= e($optLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="muted" id="variant-miss" hidden>That combination is not available.</p>
              <script type="application/json" id="variant-data"><?= json_encode($variantJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            <?php endif; ?>
            <div class="qty-row">
              <label for="qty"><strong>Qty</strong></label>
              <input class="form-control" type="number" id="qty" name="qty" value="1" min="1" max="<?= (int) $product['stock'] ?>">
            </div>
            <div class="pdp-actions">
              <button type="submit" class="btn btn-brand" name="action" value="cart">Add to cart</button>
              <button type="submit" class="btn btn-ink" name="action" value="buy">Buy now</button>
              <button type="button" class="wish-btn wish-btn--inline<?= $wished ? ' is-on' : '' ?>" data-wish="<?= (int) $product['id'] ?>" aria-pressed="<?= $wished ? 'true' : 'false' ?>" aria-label="Save to wishlist">♥</button>
            </div>
          </form>
          <?php endif; ?>
          <?php if ($wa): ?>
            <a class="btn btn-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">Order on WhatsApp</a>
          <?php endif; ?>
          <div class="pdp-copy">
            <h2>About this item</h2>
            <div class="rich"><?= rich_html($product['description']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($related): ?>
    <div class="section-head"><h2>Related products</h2></div>
    <div class="product-grid">
      <?php foreach ($related as $p): ?>
        <?= render_product_card($p) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
