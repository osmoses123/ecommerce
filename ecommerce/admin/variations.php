<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$kinds = [
    'size' => 'Size',
    'colour' => 'Colour',
    'material' => 'Material',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $kind = (string) ($_POST['kind'] ?? '');
    try {
        if (!isset($kinds[$kind]) && $action !== 'delete') {
            throw new RuntimeException('Choose size, colour, or material.');
        }
        if ($action === 'add') {
            $label = trim((string) ($_POST['label'] ?? ''));
            if ($label === '') {
                throw new RuntimeException('Enter a name for this option.');
            }
            $sort = (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM variant_options')->fetchColumn() + 1;
            db()->prepare('INSERT INTO variant_options (kind, label, sort_order) VALUES (?,?,?)')->execute([$kind, $label, $sort]);
            flash_set('success', $kinds[$kind] . ' option added.');
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM variant_options WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash_set('success', 'Option removed. Combinations that used it now leave that field blank.');
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (str_contains($message, 'uq_variant_option')) {
            $message = 'That option already exists.';
        }
        flash_set('error', $message);
    }
    redirect('admin/variations.php');
}

$groups = variant_options_grouped();

ob_start();
?>
<p class="muted" style="margin-top:-.4rem">These lists fill the size, colour, and material menus when you add a product combination.</p>
<div class="stats" style="grid-template-columns:repeat(3,1fr)">
  <?php foreach ($kinds as $kind => $label): ?>
    <div class="panel">
      <h2><?= e($label) ?></h2>
      <form method="post" style="display:flex;gap:.4rem;margin-bottom:.8rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <input type="text" name="label" required placeholder="Add <?= e(strtolower($label)) ?>" style="flex:1">
        <button class="btn btn-brand" type="submit">Add</button>
      </form>
      <?php if (!$groups[$kind]): ?>
        <div class="empty">None yet.</div>
      <?php else: ?>
        <ul class="option-list">
          <?php foreach ($groups[$kind] as $opt): ?>
            <li>
              <span><?= e($opt['label']) ?></span>
              <form method="post" onsubmit="return confirm('Remove this option?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $opt['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Variations';
$adminPageSubtitle = 'Size, colour, and material options';
$adminActive = 'variations.php';
require __DIR__ . '/_layout.php';
