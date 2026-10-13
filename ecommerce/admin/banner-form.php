<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$banner = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM banners WHERE id = ?');
    $stmt->execute([$id]);
    $banner = $stmt->fetch();
    if (!$banner) {
        flash_set('error', 'Banner not found.');
        redirect('admin/homepage.php');
    }
}
$slot = $banner['slot'] ?? (($_GET['slot'] ?? '') === 'side' ? 'side' : 'hero');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $slot = ($_POST['slot'] ?? '') === 'side' ? 'side' : 'hero';
    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $button = trim((string) ($_POST['button_label'] ?? ''));
    $link = safe_link((string) ($_POST['link_url'] ?? ''));
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    try {
        $image = $banner['image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $uploaded = handle_upload($_FILES['image'], 'banners');
            if ($uploaded) {
                $image = $uploaded;
            }
        }
        if (!$image) {
            throw new RuntimeException('Upload an image.');
        }
        if ($id > 0) {
            db()->prepare('UPDATE banners SET slot=?, title=?, subtitle=?, button_label=?, link_url=?, image=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([$slot, $title, $subtitle, $button, $link, $image, $sort, $active, $id]);
            flash_set('success', 'Banner updated.');
        } else {
            db()->prepare('INSERT INTO banners (slot, title, subtitle, button_label, link_url, image, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$slot, $title, $subtitle, $button, $link, $image, $sort, $active]);
            flash_set('success', 'Banner added.');
        }
        redirect('admin/homepage.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $banner = array_merge($banner ?: [], [
            'slot' => $slot, 'title' => $title, 'subtitle' => $subtitle, 'button_label' => $button,
            'link_url' => $link, 'sort_order' => $sort, 'is_active' => $active,
        ]);
    }
}

ob_start();
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<div class="panel" style="max-width:720px">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="slot">Placement</label>
      <select id="slot" name="slot">
        <option value="hero" <?= $slot === 'hero' ? 'selected' : '' ?>>Hero slider</option>
        <option value="side" <?= $slot === 'side' ? 'selected' : '' ?>>Side tile</option>
      </select>
    </div>
    <div class="form-group">
      <label for="title">Title <span class="label-note">(optional)</span></label>
      <input type="text" id="title" name="title" value="<?= e($banner['title'] ?? '') ?>">
      <span class="hint">Leave blank for an image-only slide or tile.</span>
    </div>
    <div class="form-group">
      <label for="subtitle">Subtitle <span class="label-note">(optional)</span></label>
      <textarea id="subtitle" name="subtitle"><?= e($banner['subtitle'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="button_label">Button label <span class="label-note">(optional)</span></label>
        <input type="text" id="button_label" name="button_label" value="<?= e($banner['button_label'] ?? '') ?>" placeholder="Shop deals">
      </div>
      <div class="form-group">
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= e((string) ($banner['sort_order'] ?? 0)) ?>">
      </div>
    </div>
    <div class="form-group">
      <label for="link_url">Link</label>
      <input type="text" id="link_url" name="link_url" value="<?= e($banner['link_url'] ?? 'shop.php') ?>" placeholder="shop.php?sort=discount">
    </div>
    <div class="form-group">
      <label for="image">Image</label>
      <?php if (!empty($banner['image'])): ?>
        <img src="<?= e(product_image_url($banner['image'])) ?>" alt="" style="width:180px;height:100px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
      <?php endif; ?>
      <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" <?= empty($banner['image']) ? 'required' : '' ?>>
    </div>
    <label class="checkbox-row"><input type="checkbox" name="is_active" <?= !isset($banner['is_active']) || !empty($banner['is_active']) ? 'checked' : '' ?>> Visible on the storefront</label>
    <div style="margin-top:1rem">
      <button class="btn btn-brand" type="submit">Save</button>
      <a class="btn btn-ghost" href="<?= e(url('admin/homepage.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = $id > 0 ? 'Edit banner' : 'Add banner';
$adminPageSubtitle = $slot === 'side' ? 'Side tile' : 'Hero slide';
$adminActive = 'homepage.php';
require __DIR__ . '/_layout.php';
