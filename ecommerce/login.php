<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('account.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        wishlist_merge_user((int) $user['id']);
        $return = $_SESSION['return_to'] ?? url('account.php');
        unset($_SESSION['return_to']);
        flash_set('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '.');
        if (str_starts_with((string) $return, '/')) {
            header('Location: ' . $return);
            exit;
        }
        redirect('account.php');
    }
    $error = 'Wrong email or password.';
}

$pageTitle = 'Sign in — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="auth-card">
    <h1>Sign in</h1>
    <p class="muted">Use your Shopora customer account.</p>
    <?php if ($error): ?><div class="flash flash--error" style="margin-bottom:1rem"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required>
      </div>
      <button class="btn btn-brand btn-block" type="submit">Sign in</button>
    </form>
    <p style="margin-top:1rem;text-align:center">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
