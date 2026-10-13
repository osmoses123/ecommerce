<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = (int) $admin['id'];
        redirect('admin/index.php');
    }
    $error = 'Invalid admin credentials.';
}
$settings = get_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin login — Shopora</title>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
  <style>:root { --brand: <?= e($settings['primary_color'] ?: '#F05A1A') ?>; }</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>Shopora Admin</h1>
    <p>Sign in to manage catalog, orders and site settings.</p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button class="btn btn-brand" type="submit" style="width:100%">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
