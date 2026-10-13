<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$product = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash_set('error', 'Product not found.');
        redirect('admin/products.php');
    }
}

$categories = get_categories();
$optionGroups = variant_options_grouped();
$gallery = [];
if ($id > 0) {
    $g = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $g->execute([$id]);
    $gallery = $g->fetchAll();
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = sanitize_rich((string) ($_POST['description'] ?? ''));
    $price = (int) ($_POST['price'] ?? 0);
    $compareRaw = trim((string) ($_POST['compare_price'] ?? ''));
    $compare = $compareRaw === '' ? null : (int) $compareRaw;
    $stock = (int) ($_POST['stock'] ?? 0);
    $status = (string) ($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'draft'], true)) {
        $status = 'active';
    }
    $isFlash = isset($_POST['is_flash']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $freeDelivery = isset($_POST['free_delivery']) ? 1 : 0;
    $isSponsored = isset($_POST['is_sponsored']) ? 1 : 0;
    $whatsapp = isset($_POST['whatsapp_checkout']) ? 1 : 0;

    if ($name === '' || $categoryId < 1 || $price < 0) {
        $error = 'Title, category and a valid selling price are required.';
    } elseif ($compare !== null && $compare <= $price) {
        $error = 'Was price must be higher than the selling price.';
    } else {
        try {
            $imagePath = $product['image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $uploaded = handle_upload($_FILES['image'], 'products');
                if ($uploaded) {
                    $imagePath = $uploaded;
                }
            }
            $extra = handle_uploads($_FILES['gallery'] ?? [], 'products');
            if (!$imagePath && $extra) {
                $imagePath = array_shift($extra);
            }
            $slug = unique_slug(db(), 'products', $slugInput !== '' ? $slugInput : $name, $id > 0 ? $id : null);
            if ($id > 0) {
                $sql = 'UPDATE products SET category_id=?, name=?, slug=?, description=?, price=?, compare_price=?, stock=?, image=?, is_flash=?, is_featured=?, status=?, free_delivery=?, is_sponsored=?, whatsapp_checkout=? WHERE id=?';
                db()->prepare($sql)->execute([
                    $categoryId, $name, $slug, $description, $price, $compare, $stock, $imagePath,
                    $isFlash, $isFeatured, $status, $freeDelivery, $isSponsored, $whatsapp, $id,
                ]);
                $productId = $id;
                $remove = array_map('intval', (array) ($_POST['delete_image'] ?? []));
                if ($remove) {
                    $place = implode(',', array_fill(0, count($remove), '?'));
                    $params = $remove;
                    $params[] = $productId;
                    db()->prepare("DELETE FROM product_images WHERE id IN ($place) AND product_id = ?")->execute($params);
                }
            } else {
                $sql = 'INSERT INTO products (category_id, name, slug, description, price, compare_price, stock, image, is_flash, is_featured, status, free_delivery, is_sponsored, whatsapp_checkout, rating, review_count) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,4.5,0)';
                db()->prepare($sql)->execute([
                    $categoryId, $name, $slug, $description, $price, $compare, $stock, $imagePath,
                    $isFlash, $isFeatured, $status, $freeDelivery, $isSponsored, $whatsapp,
                ]);
                $productId = (int) db()->lastInsertId();
            }
            if ($extra) {
                $insImg = db()->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)');
                $maxSort = db()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id = ?');
                $maxSort->execute([$productId]);
                $sort = (int) $maxSort->fetchColumn();
                foreach ($extra as $path) {
                    $sort++;
                    $insImg->execute([$productId, $path, $sort]);
                }
            }
            save_product_offers($productId, $optionGroups);
            flash_set('success', $id > 0 ? 'Product updated.' : 'Product created.');
            redirect('admin/products.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $product = array_merge($product ?: [], [
        'name' => $name,
        'slug' => $slugInput,
        'category_id' => $categoryId,
        'description' => $description,
        'price' => $price,
        'compare_price' => $compare,
        'stock' => $stock,
        'status' => $status,
        'is_flash' => $isFlash,
        'is_featured' => $isFeatured,
        'free_delivery' => $freeDelivery,
        'is_sponsored' => $isSponsored,
        'whatsapp_checkout' => $whatsapp,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) {
    $tiers = [];
    foreach ((array) ($_POST['tier_min'] ?? []) as $i => $min) {
        $tiers[] = [
            'min_qty' => (int) $min,
            'discount_type' => (($_POST['tier_type'][$i] ?? '') === 'fixed') ? 'fixed' : 'percent',
            'discount_value' => (int) ($_POST['tier_value'][$i] ?? 0),
        ];
    }
    $variantRows = [];
    foreach ((array) ($_POST['var_size'] ?? []) as $i => $size) {
        $variantRows[] = [
            'size_id' => (int) $size,
            'colour_id' => (int) ($_POST['var_colour'][$i] ?? 0),
            'material_id' => (int) ($_POST['var_material'][$i] ?? 0),
            'sku' => (string) ($_POST['var_sku'][$i] ?? ''),
            'price' => (string) ($_POST['var_price'][$i] ?? ''),
            'stock' => (int) ($_POST['var_stock'][$i] ?? 0),
        ];
    }
} else {
    $tiers = $id > 0 ? product_tiers($id) : [];
    $variantRows = $id > 0 ? product_variant_rows($id) : [];
}
if (!$tiers) {
    $tiers = [['min_qty' => 2, 'discount_type' => 'percent', 'discount_value' => 0]];
}
if (!$variantRows) {
    $variantRows = [['size_id' => '', 'colour_id' => '', 'material_id' => '', 'sku' => '', 'price' => '', 'stock' => 0]];
}

ob_start();
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<form id="product-form" class="product-sheet" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <p class="sheet-kicker">Basics</p>
  <h2 class="sheet-heading">Product details</h2>

  <div class="form-group">
    <label for="name">Title</label>
    <input type="text" id="name" name="name" required value="<?= e($product['name'] ?? '') ?>">
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" value="<?= e($product['slug'] ?? '') ?>" placeholder="filled-from-title" data-autofill="<?= $id > 0 ? '0' : '1' ?>">
      <p class="field-note">Used in the product link. Leave blank to build it from the title.</p>
    </div>
    <div class="form-group">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <option value="">None</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="sheet-link" href="<?= e(url('admin/categories.php')) ?>">Add categories</a>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="price">Selling price (NGN)</label>
      <input type="number" id="price" name="price" min="0" required value="<?= e((string) ($product['price'] ?? '')) ?>">
      <p class="field-note">Checkout price buyers pay.</p>
    </div>
    <div class="form-group">
      <label for="compare_price">Was price</label>
      <input type="number" id="compare_price" name="compare_price" min="0" value="<?= e((string) ($product['compare_price'] ?? '')) ?>">
      <p class="field-note">Optional. Must be higher than selling price — shown crossed out.</p>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="stock">Base stock</label>
      <input type="number" id="stock" name="stock" min="0" required value="<?= e((string) ($product['stock'] ?? '10')) ?>">
      <p class="field-note">Used when there are no variants.</p>
    </div>
    <div class="form-group">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="active" <?= ($product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="draft" <?= ($product['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
      </select>
    </div>
  </div>

  <label class="switch-card">
    <input class="switch" type="checkbox" name="free_delivery" <?= !empty($product['free_delivery']) ? 'checked' : '' ?>>
    <span><strong>Free delivery</strong><small>Offer free delivery on this product.</small></span>
  </label>
  <label class="switch-card">
    <input class="switch" type="checkbox" name="is_sponsored" <?= !empty($product['is_sponsored']) ? 'checked' : '' ?>>
    <span><strong>Sponsored product</strong><small>Highlight this product in the shop sidebar.</small></span>
  </label>
  <label class="switch-card">
    <input class="switch" type="checkbox" name="whatsapp_checkout" <?= !empty($product['whatsapp_checkout']) ? 'checked' : '' ?>>
    <span><strong>WhatsApp checkout</strong><small>Show “Order on WhatsApp” on this product’s shop page.</small></span>
  </label>
  <label class="switch-card">
    <input class="switch" type="checkbox" name="is_flash" <?= !empty($product['is_flash']) ? 'checked' : '' ?>>
    <span><strong>Flash deal</strong><small>Show this product on the homepage flash rail.</small></span>
  </label>
  <label class="switch-card">
    <input class="switch" type="checkbox" name="is_featured" <?= !empty($product['is_featured']) ? 'checked' : '' ?>>
    <span><strong>Top pick</strong><small>Show this product in the Top picks row.</small></span>
  </label>

  <p class="sheet-kicker" style="margin-top:1.25rem">Description</p>
  <div class="rte" data-rte>
    <div class="rte-bar">
      <button type="button" data-cmd="undo" title="Undo">↩</button>
      <button type="button" data-cmd="redo" title="Redo">↪</button>
      <select data-block aria-label="Paragraph style">
        <option value="P">Paragraph</option>
        <option value="H2">Heading</option>
        <option value="H3">Subheading</option>
      </select>
      <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
      <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
      <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
      <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
      <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
      <button type="button" data-cmd="link" title="Link">Link</button>
      <button type="button" data-cmd="removeFormat" title="Clear formatting">Tx</button>
    </div>
    <div class="rte-area" contenteditable="true" role="textbox" aria-label="Description"></div>
    <textarea id="description" name="description"><?= e($product['description'] ?? '') ?></textarea>
  </div>

  <p class="sheet-kicker" style="margin-top:1.25rem">Photos</p>
  <div class="form-group">
    <label for="image">Main photo</label>
    <?php if (!empty($product['image'])): ?>
      <img class="thumb" src="<?= e(product_image_url($product['image'])) ?>" alt="" style="width:96px;height:96px;margin-bottom:.5rem">
    <?php endif; ?>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    <p class="field-note">JPEG, PNG or WebP · max 2MB. Leave empty to keep the current photo.</p>
  </div>
  <div class="form-group">
    <label for="gallery">More photos</label>
    <?php if ($gallery): ?>
      <div class="gallery-edit">
        <?php foreach ($gallery as $img): ?>
          <label class="gallery-edit__item">
            <img src="<?= e(product_image_url($img['path'])) ?>" alt="">
            <span><input type="checkbox" name="delete_image[]" value="<?= (int) $img['id'] ?>"> Remove</span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <input type="file" id="gallery" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="field-note">Shoppers can switch these on the product page. Hover zoom uses the photo they are viewing.</p>
  </div>

  <section class="offer-card">
    <div class="offer-card__head">
      <div>
        <h3>Buy more, save more</h3>
        <p>Optional auto discount when buyers take more than one unit.</p>
      </div>
      <button class="btn btn-pill" type="button" id="add-tier">Add tier</button>
    </div>
    <div class="offer-table" id="tier-list">
      <div class="offer-table__labels">
        <span>Buy at least</span><span>Discount type</span><span>Value</span><span></span>
      </div>
      <?php foreach ($tiers as $tier): ?>
        <div class="offer-row">
          <input type="number" name="tier_min[]" min="2" value="<?= (int) ($tier['min_qty'] ?? 2) ?>">
          <select name="tier_type[]">
            <option value="percent" <?= ($tier['discount_type'] ?? '') !== 'fixed' ? 'selected' : '' ?>>Percent %</option>
            <option value="fixed" <?= ($tier['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Amount (₦)</option>
          </select>
          <input type="number" name="tier_value[]" min="0" value="<?= (int) ($tier['discount_value'] ?? 0) ?>">
          <button class="row-remove" type="button" aria-label="Remove tier">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="offer-card">
    <div class="offer-card__head">
      <div>
        <h3>Variants</h3>
        <p>Select options from your variation list (<a href="<?= e(url('admin/variations.php')) ?>">manage</a>).</p>
      </div>
      <button class="btn btn-pill" type="button" id="add-variant">Add combination</button>
    </div>
    <div class="offer-table offer-table--variants" id="variant-list">
      <div class="offer-table__labels">
        <span>Size</span><span>Colour</span><span>Material</span><span>SKU</span><span>Price</span><span>Stock</span><span></span>
      </div>
      <?php foreach ($variantRows as $row): ?>
        <div class="offer-row">
          <?php foreach (['size' => 'var_size', 'colour' => 'var_colour', 'material' => 'var_material'] as $kind => $field): ?>
            <select name="<?= $field ?>[]">
              <option value="">—</option>
              <?php foreach ($optionGroups[$kind] as $opt): ?>
                <option value="<?= (int) $opt['id'] ?>" <?= (int) ($row[$kind . '_id'] ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endforeach; ?>
          <input type="text" name="var_sku[]" value="<?= e((string) ($row['sku'] ?? '')) ?>" placeholder="SKU">
          <input type="number" name="var_price[]" min="0" value="<?= e((string) ($row['price'] ?? '')) ?>" placeholder="Selling price">
          <input type="number" name="var_stock[]" min="0" value="<?= (int) ($row['stock'] ?? 0) ?>">
          <button class="row-remove" type="button" aria-label="Remove combination">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="sheet-actions">
    <button class="btn btn-brand" type="submit"><?= $id > 0 ? 'Save product' : 'Create product' ?></button>
    <a class="btn btn-ghost" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
  </div>
</form>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = $id > 0 ? 'Edit product' : 'Add product';
$adminPageSubtitle = '';
$adminActive = 'products.php';
require __DIR__ . '/_layout.php';
