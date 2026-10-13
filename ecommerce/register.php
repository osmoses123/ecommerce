<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('account.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            wishlist_merge_user((int) $_SESSION['user_id']);
            flash_set('success', 'Account created. Welcome to Shopora.');
            redirect('account.php');
        } catch (PDOException $e) {
            $error = 'That email is already registered. Try signing in.';
        }
    }
}

$pageTitle = 'Register — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="auth-card">
    <h1>Create account</h1>
    <p class="muted">Shop faster with saved details and order history.</p>
    <?php if ($error): ?><div class="flash flash--error" style="margin-bottom:1rem"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="name">Full name</label>
        <input class="form-control" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="phone">Phone</label>
        <input class="form-control" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="8">
      </div>
      <button class="btn btn-brand btn-block" type="submit">Register</button>
    </form>
    <p style="margin-top:1rem;text-align:center">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
