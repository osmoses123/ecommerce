<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$settings = get_settings(true);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $title = trim((string) ($_POST['site_title'] ?? ''));
    $desc = trim((string) ($_POST['site_description'] ?? ''));
    $color = trim((string) ($_POST['primary_color'] ?? ''));
    if ($title === '') {
        $error = 'Site title is required.';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $error = 'Primary color must be a hex value like #F05A1A.';
    } else {
        try {
            $logoPath = $settings['logo_path'] ?? '';
            if (!empty($_FILES['logo']['name'])) {
                $uploaded = handle_upload($_FILES['logo'], 'branding');
                if ($uploaded) {
                    $logoPath = $uploaded;
                }
            }
            set_settings([
                'site_title' => $title,
                'site_description' => $desc,
                'primary_color' => strtoupper($color),
                'logo_path' => $logoPath,
                'whatsapp_number' => preg_replace('/\D+/', '', (string) ($_POST['whatsapp_number'] ?? '')) ?? '',
            ]);
            flash_set('success', 'Appearance saved. Open the storefront to see changes.');
            redirect('admin/appearance.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $settings = array_merge($settings, [
        'site_title' => $title,
        'site_description' => $desc,
        'primary_color' => $color,
    ]);
}

$logoSrc = logo_url($settings['logo_path'] ?? null);

ob_start();
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<p class="muted" style="margin-top:-.5rem;margin-bottom:1rem">These controls drive the public website title, meta description, brand color, and logo.</p>

<div class="preview-strip" id="preview-strip" style="--brand: <?= e($settings['primary_color']) ?>">
  <?php if ($logoSrc): ?>
    <img id="preview-logo" src="<?= e($logoSrc) ?>" alt="">
  <?php else: ?>
    <img id="preview-logo" src="" alt="" hidden>
  <?php endif; ?>
  <span class="preview-title" id="preview-title"><?= e($settings['site_title']) ?></span>
  <div class="preview-search"><span style="background:<?= e($settings['primary_color']) ?>">Search</span></div>
</div>

<div class="panel" style="max-width:640px">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="site_title">Site title</label>
      <input type="text" id="site_title" name="site_title" required value="<?= e($settings['site_title']) ?>">
    </div>
    <div class="form-group">
      <label for="site_description">Meta description</label>
      <textarea id="site_description" name="site_description" rows="3"><?= e($settings['site_description']) ?></textarea>
      <span class="hint">Shown in search snippets and the storefront footer.</span>
    </div>
    <div class="form-group">
      <label for="primary_color">Primary color</label>
      <div class="color-pair">
        <input type="color" id="primary_color" name="primary_color" value="<?= e($settings['primary_color']) ?>">
        <input type="text" id="primary_color_hex" value="<?= e($settings['primary_color']) ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" aria-label="Primary color hex">
      </div>
      <span class="hint">Injected as CSS <code>--brand</code> on every public page.</span>
    </div>
    <div class="form-group">
      <label for="logo">Logo</label>
      <?php if ($logoSrc): ?>
        <img src="<?= e($logoSrc) ?>" alt="Current logo" style="height:48px;margin-bottom:.5rem;border-radius:6px">
      <?php endif; ?>
      <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
      <span class="hint">JPEG, PNG or WebP · max 2MB. Leave empty to keep the current logo.</span>
    </div>
    <div class="form-group">
      <label for="whatsapp_number">WhatsApp number</label>
      <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?= e($settings['whatsapp_number'] ?? '') ?>" placeholder="2348012345678">
      <span class="hint">Country code plus number, digits only. Used by products that have WhatsApp checkout turned on.</span>
    </div>
    <button class="btn btn-brand" type="submit">Save appearance</button>
  </form>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Appearance';
$adminPageSubtitle = 'Brand the live storefront';
$adminActive = 'appearance.php';
require __DIR__ . '/_layout.php';
