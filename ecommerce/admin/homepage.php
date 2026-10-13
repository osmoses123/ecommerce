<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete_banner') {
            db()->prepare('DELETE FROM banners WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash_set('success', 'Banner removed.');
        } elseif ($action === 'save_column') {
            $position = (int) ($_POST['position'] ?? 0);
            if ($position < 1 || $position > 2) {
                throw new RuntimeException('Choose column 1 or 2.');
            }
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $button = trim((string) ($_POST['button_label'] ?? ''));
            $link = safe_link((string) ($_POST['link_url'] ?? ''));
            $existing = db()->prepare('SELECT * FROM home_columns WHERE position = ?');
            $existing->execute([$position]);
            $row = $existing->fetch();
            $image = $row['image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $uploaded = handle_upload($_FILES['image'], 'banners');
                if ($uploaded) {
                    $image = $uploaded;
                }
            }
            if ($row) {
                db()->prepare('UPDATE home_columns SET title=?, body=?, button_label=?, link_url=?, image=? WHERE position=?')->execute([$title, $body, $button, $link, $image, $position]);
            } else {
                db()->prepare('INSERT INTO home_columns (position, title, body, button_label, link_url, image) VALUES (?,?,?,?,?,?)')->execute([$position, $title, $body, $button, $link, $image]);
            }
            flash_set('success', 'Homepage column saved.');
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('admin/homepage.php');
}

$heroes = db()->query("SELECT * FROM banners WHERE slot = 'hero' ORDER BY sort_order ASC, id ASC")->fetchAll();
$sides = db()->query("SELECT * FROM banners WHERE slot = 'side' ORDER BY sort_order ASC, id ASC")->fetchAll();
$columns = [];
foreach (db()->query('SELECT * FROM home_columns ORDER BY position ASC')->fetchAll() as $col) {
    $columns[(int) $col['position']] = $col;
}

ob_start();
?>
<p class="muted" style="margin-top:-.4rem">The hero slider and the two tiles beside it are managed here. The two columns under Top picks are edited at the bottom.</p>

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <h2 style="margin:0">Hero slider</h2>
    <a class="btn btn-brand btn-sm" href="<?= e(url('admin/banner-form.php?slot=hero')) ?>">Add slide</a>
  </div>
  <?php if (!$heroes): ?>
    <div class="empty">No slides yet.</div>
  <?php else: ?>
    <table class="data">
      <thead><tr><th></th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($heroes as $b): ?>
          <tr>
            <td><img class="thumb" src="<?= e(product_image_url($b['image'])) ?>" alt=""></td>
            <td><strong><?= e($b['title'] !== '' ? $b['title'] : 'Image only') ?></strong><div class="muted" style="font-size:.8rem"><?= e($b['link_url']) ?></div></td>
            <td><?= (int) $b['sort_order'] ?></td>
            <td><?= !empty($b['is_active']) ? 'Showing' : 'Hidden' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/banner-form.php?id=' . $b['id'])) ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this slide?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_banner">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <h2 style="margin:0">Side tiles</h2>
    <a class="btn btn-brand btn-sm" href="<?= e(url('admin/banner-form.php?slot=side')) ?>">Add tile</a>
  </div>
  <?php if (!$sides): ?>
    <div class="empty">No side tiles yet.</div>
  <?php else: ?>
    <table class="data">
      <thead><tr><th></th><th>Label</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($sides as $b): ?>
          <tr>
            <td><img class="thumb" src="<?= e(product_image_url($b['image'])) ?>" alt=""></td>
            <td><strong><?= e($b['title'] !== '' ? $b['title'] : 'Image only') ?></strong></td>
            <td><?= (int) $b['sort_order'] ?></td>
            <td><?= !empty($b['is_active']) ? 'Showing' : 'Hidden' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/banner-form.php?id=' . $b['id'])) ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this tile?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_banner">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<h2 style="font-family:var(--font-display);font-size:1.15rem">After Top picks</h2>
<p class="muted">Two quiet columns on the homepage. Shoppers see them directly under Top picks.</p>
<div class="panel-grid">
  <?php for ($pos = 1; $pos <= 2; $pos++): $col = $columns[$pos] ?? []; ?>
    <div class="panel">
      <h2>Column <?= $pos ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_column">
        <input type="hidden" name="position" value="<?= $pos ?>">
        <div class="form-group">
          <label for="title-<?= $pos ?>">Title <span class="label-note">(optional)</span></label>
          <input type="text" id="title-<?= $pos ?>" name="title" value="<?= e($col['title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="body-<?= $pos ?>">Short line</label>
          <input type="text" id="body-<?= $pos ?>" name="body" value="<?= e($col['body'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="btn-<?= $pos ?>">Button label</label>
            <input type="text" id="btn-<?= $pos ?>" name="button_label" value="<?= e($col['button_label'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="link-<?= $pos ?>">Link</label>
            <input type="text" id="link-<?= $pos ?>" name="link_url" value="<?= e($col['link_url'] ?? 'shop.php') ?>">
          </div>
        </div>
        <div class="form-group">
          <label for="img-<?= $pos ?>">Image</label>
          <?php if (!empty($col['image'])): ?>
            <img class="thumb" src="<?= e(product_image_url($col['image'])) ?>" alt="" style="width:72px;height:72px;margin-bottom:.4rem">
          <?php endif; ?>
          <input type="file" id="img-<?= $pos ?>" name="image" accept="image/jpeg,image/png,image/webp">
        </div>
        <button class="btn btn-brand" type="submit">Save column</button>
      </form>
    </div>
  <?php endfor; ?>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Homepage';
$adminPageSubtitle = 'Slider, side tiles, and the two columns under Top picks';
$adminActive = 'homepage.php';
require __DIR__ . '/_layout.php';
