<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $icon = trim((string) ($_POST['icon_label'] ?? ''));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            if ($id > 0) {
                $slug = unique_slug(db(), 'categories', $name, $id);
                db()->prepare('UPDATE categories SET name=?, slug=?, icon_label=?, sort_order=? WHERE id=?')->execute([$name, $slug, $icon, $sort, $id]);
                flash_set('success', 'Category updated.');
            } else {
                $slug = unique_slug(db(), 'categories', $name);
                db()->prepare('INSERT INTO categories (name, slug, icon_label, sort_order) VALUES (?,?,?,?)')->execute([$name, $slug, $icon, $sort]);
                flash_set('success', 'Category added.');
            }
            redirect('admin/categories.php');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            flash_set('error', 'Cannot delete: this category still has products. Move or delete them first.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash_set('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

$categories = get_categories();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    foreach ($categories as $c) {
        if ((int) $c['id'] === $editId) {
            $edit = $c;
            break;
        }
    }
}

ob_start();
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<div class="panel-grid">
  <div class="panel">
    <h2>All categories</h2>
    <table class="data">
      <thead><tr><th>Icon</th><th>Name</th><th>Sort</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td><?= e($c['icon_label']) ?></td>
            <td><strong><?= e($c['name']) ?></strong><div class="muted" style="font-size:.8rem"><?= e($c['slug']) ?></div></td>
            <td><?= (int) $c['sort_order'] ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/categories.php?edit=' . $c['id'])) ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <h2><?= $edit ? 'Edit category' : 'Add category' ?></h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required value="<?= e($edit['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="icon_label">Icon label (emoji or 2-letter code)</label>
        <input type="text" id="icon_label" name="icon_label" maxlength="16" value="<?= e($edit['icon_label'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= e((string) ($edit['sort_order'] ?? '0')) ?>">
      </div>
      <button class="btn btn-brand" type="submit">Save</button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Categories';
$adminPageSubtitle = 'Organize the storefront navigation';
$adminActive = 'categories.php';
require __DIR__ . '/_layout.php';
