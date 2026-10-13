<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$settings = get_settings(true);
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'save') {
        $host = trim((string) ($_POST['smtp_host'] ?? ''));
        $port = trim((string) ($_POST['smtp_port'] ?? '587'));
        $enc = (string) ($_POST['smtp_encryption'] ?? 'tls');
        $user = trim((string) ($_POST['smtp_username'] ?? ''));
        $pass = (string) ($_POST['smtp_password'] ?? '');
        $fromEmail = trim((string) ($_POST['smtp_from_email'] ?? ''));
        $fromName = trim((string) ($_POST['smtp_from_name'] ?? ''));

        if (!in_array($enc, ['none', 'tls', 'ssl'], true)) {
            $enc = 'tls';
        }

        $pairs = [
            'smtp_host' => $host,
            'smtp_port' => $port !== '' ? $port : '587',
            'smtp_encryption' => $enc,
            'smtp_username' => $user,
            'smtp_from_email' => $fromEmail,
            'smtp_from_name' => $fromName !== '' ? $fromName : 'Shopora',
        ];
        if ($pass !== '') {
            $pairs['smtp_password'] = $pass;
        }
        set_settings($pairs);
        flash_set('success', 'SMTP settings saved.');
        redirect('admin/smtp.php');
    }

    if ($action === 'test') {
        $to = trim((string) ($_POST['test_to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid test recipient email.';
        } else {
            $fresh = get_settings(true);
            $testResult = smtp_send($fresh, $to, 'Shopora SMTP test', "This is a test email from Shopora admin.\n\nIf you received this, SMTP is working.");
        }
    }
}

ob_start();
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($testResult): ?>
  <div class="flash flash-<?= $testResult['ok'] ? 'success' : 'error' ?>"><?= e($testResult['message']) ?></div>
<?php endif; ?>

<div class="panel-grid">
  <div class="panel">
    <h2>SMTP settings</h2>
    <p class="muted" style="margin-top:-.4rem">Used for order confirmations and test emails. Password is never shown; leave blank to keep the current one.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="form-group">
        <label for="smtp_host">Host</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?= e($settings['smtp_host']) ?>" placeholder="smtp.example.com">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="smtp_port">Port</label>
          <input type="text" id="smtp_port" name="smtp_port" value="<?= e($settings['smtp_port']) ?>">
        </div>
        <div class="form-group">
          <label for="smtp_encryption">Encryption</label>
          <select id="smtp_encryption" name="smtp_encryption">
            <?php foreach (['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $k => $label): ?>
              <option value="<?= $k ?>" <?= ($settings['smtp_encryption'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="smtp_username">Username</label>
        <input type="text" id="smtp_username" name="smtp_username" value="<?= e($settings['smtp_username']) ?>" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="smtp_password">Password</label>
        <input type="password" id="smtp_password" name="smtp_password" value="" placeholder="Leave blank to keep current" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="smtp_from_email">From email</label>
        <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= e($settings['smtp_from_email']) ?>">
      </div>
      <div class="form-group">
        <label for="smtp_from_name">From name</label>
        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e($settings['smtp_from_name']) ?>">
      </div>
      <button class="btn btn-brand" type="submit">Save SMTP</button>
    </form>
  </div>
  <div class="panel">
    <h2>Send test email</h2>
    <p class="muted" style="margin-top:-.4rem">Uses the saved settings above (save first if you just changed them).</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <div class="form-group">
        <label for="test_to">Send to</label>
        <input type="email" id="test_to" name="test_to" required placeholder="you@example.com">
      </div>
      <button class="btn btn-ink" type="submit">Send test</button>
    </form>
  </div>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'SMTP';
$adminPageSubtitle = 'Outbound email for orders';
$adminActive = 'smtp.php';
require __DIR__ . '/_layout.php';
