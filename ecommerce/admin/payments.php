<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$settings = get_settings(true);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $paystackSecret = trim((string) ($_POST['paystack_secret_key'] ?? ''));
    $flutterSecret = trim((string) ($_POST['flutterwave_secret_key'] ?? ''));
    if ($paystackSecret === '') {
        $paystackSecret = (string) ($settings['paystack_secret_key'] ?? '');
    }
    if ($flutterSecret === '') {
        $flutterSecret = (string) ($settings['flutterwave_secret_key'] ?? '');
    }
    set_settings([
        'paystack_enabled' => isset($_POST['paystack_enabled']) ? '1' : '0',
        'paystack_public_key' => trim((string) ($_POST['paystack_public_key'] ?? '')),
        'paystack_secret_key' => $paystackSecret,
        'flutterwave_enabled' => isset($_POST['flutterwave_enabled']) ? '1' : '0',
        'flutterwave_public_key' => trim((string) ($_POST['flutterwave_public_key'] ?? '')),
        'flutterwave_secret_key' => $flutterSecret,
    ]);
    flash_set('success', 'Payment settings saved.');
    redirect('admin/payments.php');
}

$settings = get_settings(true);

ob_start();
?>
<p class="muted" style="margin-top:-.4rem">Add your live or test keys from Paystack and Flutterwave. Leave a secret key blank to keep the current one. Pay on delivery always stays available.</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="panel-grid">
    <div class="panel">
      <h2>Paystack</h2>
      <label class="switch-card">
        <input class="switch" type="checkbox" name="paystack_enabled" <?= ($settings['paystack_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
        <span><strong>Enable Paystack</strong><small>Show Paystack on the checkout page.</small></span>
      </label>
      <div class="form-group">
        <label for="paystack_public_key">Public key</label>
        <input type="text" id="paystack_public_key" name="paystack_public_key" value="<?= e($settings['paystack_public_key'] ?? '') ?>" placeholder="pk_test_…">
      </div>
      <div class="form-group">
        <label for="paystack_secret_key">Secret key</label>
        <input type="password" id="paystack_secret_key" name="paystack_secret_key" value="" placeholder="Leave blank to keep current" autocomplete="new-password">
        <span class="hint"><?= trim((string) ($settings['paystack_secret_key'] ?? '')) !== '' ? 'A secret key is already saved.' : 'No secret key saved yet.' ?></span>
      </div>
    </div>
    <div class="panel">
      <h2>Flutterwave</h2>
      <label class="switch-card">
        <input class="switch" type="checkbox" name="flutterwave_enabled" <?= ($settings['flutterwave_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
        <span><strong>Enable Flutterwave</strong><small>Show Flutterwave on the checkout page.</small></span>
      </label>
      <div class="form-group">
        <label for="flutterwave_public_key">Public key</label>
        <input type="text" id="flutterwave_public_key" name="flutterwave_public_key" value="<?= e($settings['flutterwave_public_key'] ?? '') ?>" placeholder="FLWPUBK_TEST-…">
      </div>
      <div class="form-group">
        <label for="flutterwave_secret_key">Secret key</label>
        <input type="password" id="flutterwave_secret_key" name="flutterwave_secret_key" value="" placeholder="Leave blank to keep current" autocomplete="new-password">
        <span class="hint"><?= trim((string) ($settings['flutterwave_secret_key'] ?? '')) !== '' ? 'A secret key is already saved.' : 'No secret key saved yet.' ?></span>
      </div>
    </div>
  </div>
  <button class="btn btn-brand" type="submit">Save payment settings</button>
</form>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Payments';
$adminPageSubtitle = 'Paystack and Flutterwave';
$adminActive = 'payments.php';
require __DIR__ . '/_layout.php';
