<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$settings = get_settings(true);
$defaults = settings_defaults();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    set_settings([
        'footer_about' => trim((string) ($_POST['footer_about'] ?? '')),
        'footer_shop_heading' => trim((string) ($_POST['footer_shop_heading'] ?? '')) ?: $defaults['footer_shop_heading'],
        'footer_account_heading' => trim((string) ($_POST['footer_account_heading'] ?? '')) ?: $defaults['footer_account_heading'],
        'footer_help_heading' => trim((string) ($_POST['footer_help_heading'] ?? '')) ?: $defaults['footer_help_heading'],
        'footer_help' => trim((string) ($_POST['footer_help'] ?? '')),
        'footer_copyright' => trim((string) ($_POST['footer_copyright'] ?? '')),
        'topbar_city' => trim((string) ($_POST['topbar_city'] ?? '')),
        'topbar_help' => trim((string) ($_POST['topbar_help'] ?? '')),
        'topbar_extra' => trim((string) ($_POST['topbar_extra'] ?? '')),
    ]);
    flash_set('success', 'Footer and header text saved.');
    redirect('admin/footer.php');
}

$settings = get_settings(true);

ob_start();
?>
<p class="muted" style="margin-top:-.4rem">These lines appear in the dark footer and the thin bar above the search. Shop links always come from your categories.</p>
<form method="post">
  <?= csrf_field() ?>
  <div class="panel">
    <h2>Top bar</h2>
    <div class="form-row">
      <div class="form-group">
        <label for="topbar_city">Delivery line</label>
        <input type="text" id="topbar_city" name="topbar_city" value="<?= e($settings['topbar_city'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="topbar_help">Help line</label>
        <input type="text" id="topbar_help" name="topbar_help" value="<?= e($settings['topbar_help'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label for="topbar_extra">Extra text</label>
      <input type="text" id="topbar_extra" name="topbar_extra" value="<?= e($settings['topbar_extra'] ?? '') ?>">
    </div>
  </div>
  <div class="panel">
    <h2>Footer</h2>
    <div class="form-group">
      <label for="footer_about">About text under the logo</label>
      <textarea id="footer_about" name="footer_about" rows="3"><?= e($settings['footer_about'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="footer_shop_heading">Shop heading</label>
        <input type="text" id="footer_shop_heading" name="footer_shop_heading" value="<?= e($settings['footer_shop_heading'] ?? 'Shop') ?>">
      </div>
      <div class="form-group">
        <label for="footer_account_heading">Account heading</label>
        <input type="text" id="footer_account_heading" name="footer_account_heading" value="<?= e($settings['footer_account_heading'] ?? 'Account') ?>">
      </div>
    </div>
    <div class="form-group">
      <label for="footer_help_heading">Help heading</label>
      <input type="text" id="footer_help_heading" name="footer_help_heading" value="<?= e($settings['footer_help_heading'] ?? 'Help') ?>">
    </div>
    <div class="form-group">
      <label for="footer_help">Help lines</label>
      <textarea id="footer_help" name="footer_help" rows="5"><?= e($settings['footer_help'] ?? '') ?></textarea>
      <span class="hint">One line per item. Shown in the Help column.</span>
    </div>
    <div class="form-group">
      <label for="footer_copyright">Copyright line</label>
      <input type="text" id="footer_copyright" name="footer_copyright" value="<?= e($settings['footer_copyright'] ?? '') ?>">
      <span class="hint">The year is added automatically in front.</span>
    </div>
  </div>
  <button class="btn btn-brand" type="submit">Save content</button>
</form>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Footer';
$adminPageSubtitle = 'Header bar and footer copy';
$adminActive = 'footer.php';
require __DIR__ . '/_layout.php';
