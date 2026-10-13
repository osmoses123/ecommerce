<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
$lines = cart_lines();
if (!$lines) {
    flash_set('error', 'Your cart is empty.');
    redirect('cart.php');
}

$subtotal = cart_subtotal();
$allFree = true;
foreach ($lines as $line) {
    if (empty($line['product']['free_delivery'])) {
        $allFree = false;
        break;
    }
}

$countries = db()->query('SELECT * FROM countries ORDER BY is_default DESC, name ASC')->fetchAll();
$defaultCountryId = 0;
foreach ($countries as $c) {
    if (!empty($c['is_default'])) {
        $defaultCountryId = (int) $c['id'];
        break;
    }
}
if ($defaultCountryId < 1 && $countries) {
    $defaultCountryId = (int) $countries[0]['id'];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $countryId = (int) ($_POST['country_id'] ?? 0);
    $stateId = (int) ($_POST['state_id'] ?? 0);
    $cityId = (int) ($_POST['city_id'] ?? 0);
    $createAccount = !$user && isset($_POST['create_account']);
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password_confirm'] ?? '');
    $paymentMethod = (string) ($_POST['payment_method'] ?? 'pay_on_delivery');
    $gateways = payment_gateways_available();
    if (!isset($gateways[$paymentMethod]) || !$gateways[$paymentMethod]) {
        $paymentMethod = 'pay_on_delivery';
    }

    $loc = db()->prepare('SELECT s.name AS state_name, s.shipping_fee, c.name AS country_name FROM states s JOIN countries c ON c.id = s.country_id WHERE s.id = ? AND s.country_id = ?');
    $loc->execute([$stateId, $countryId]);
    $place = $loc->fetch();
    $cityStmt = db()->prepare('SELECT name FROM cities WHERE id = ? AND state_id = ?');
    $cityStmt->execute([$cityId, $stateId]);
    $cityName = $cityStmt->fetchColumn();

    if ($fullName === '' || $phone === '' || $address === '' || !$place || !$cityName) {
        $error = 'Please fill in delivery details and choose a country, state, and city.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !$user) {
        $error = 'Enter a valid email so we can send the order receipt.';
    } elseif ($createAccount && strlen($password) < 6) {
        $error = 'Choose a password of at least 6 characters to create an account.';
    } elseif ($createAccount && $password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $userId = $user ? (int) $user['id'] : null;
            $mailTo = $user['email'] ?? $email;
            if ($createAccount) {
                $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $exists->execute([$email]);
                if ($exists->fetch()) {
                    throw new RuntimeException('An account with that email already exists. Sign in, or check out as a guest.');
                }
                $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)')->execute([
                    $fullName, $email, $phone, password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();
            }
            $shipping = shipping_quote($subtotal, (int) $place['shipping_fee'], $allFree);
            $total = $subtotal + $shipping;
            $onum = order_number();
            $payStatus = in_array($paymentMethod, ['paystack', 'flutterwave'], true) ? 'pending' : 'unpaid';
            $ins = $pdo->prepare('INSERT INTO orders (user_id, order_number, status, subtotal, shipping, total, full_name, phone, address, city, state, country, guest_email, payment_method, payment_status, payment_reference) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([
                $userId, $onum, 'pending', $subtotal, $shipping, $total,
                $fullName, $phone, $address, (string) $cityName, (string) $place['state_name'], (string) $place['country_name'],
                $mailTo, $paymentMethod, $payStatus, null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty, line_total) VALUES (?,?,?,?,?,?)');
            $updStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $updVariant = $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($lines as $line) {
                $p = $line['product'];
                if (!empty($line['variant']['id'])) {
                    $updVariant->execute([$line['qty'], (int) $line['variant']['id'], $line['qty']]);
                    if ($updVariant->rowCount() === 0) {
                        throw new RuntimeException($p['name'] . ' does not have enough stock for that option.');
                    }
                } else {
                    $updStock->execute([$line['qty'], (int) $p['id'], $line['qty']]);
                    if ($updStock->rowCount() === 0) {
                        throw new RuntimeException($p['name'] . ' does not have enough stock.');
                    }
                }
                $itemName = $p['name'] . ($line['variant_label'] !== '' ? ' (' . $line['variant_label'] . ')' : '');
                $insItem->execute([$orderId, (int) $p['id'], $itemName, $line['unit_price'], $line['qty'], $line['line_total']]);
            }
            $pdo->commit();
            cart_clear();
            if ($createAccount && $userId) {
                $_SESSION['user_id'] = $userId;
                wishlist_merge_user($userId);
            }

            if (in_array($paymentMethod, ['paystack', 'flutterwave'], true)) {
                redirect('payment-start.php?order=' . urlencode($onum));
            }

            $settings = get_settings(true);
            $body = "Hello {$fullName},\n\nYour Shopora order {$onum} has been placed.\nTotal: " . money_ngn($total) . "\nShip to: {$address}, {$cityName}, {$place['state_name']}, {$place['country_name']}\nPayment: Pay on delivery\n\nWe will call {$phone} before dispatch.\n\n— Shopora";
            $mail = smtp_send($settings, $mailTo, 'Order confirmation ' . $onum, $body);
            $qs = 'order=' . urlencode($onum) . ($mail['ok'] ? '' : '&mail=0');
            redirect('order-success.php?' . $qs);
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = $ex->getMessage();
        }
    }
}

$postedCountry = (int) ($_POST['country_id'] ?? $defaultCountryId);
$stateStmt = db()->prepare('SELECT id, name, shipping_fee FROM states WHERE country_id = ? ORDER BY name ASC');
$stateStmt->execute([$postedCountry]);
$states = $stateStmt->fetchAll();
$postedState = (int) ($_POST['state_id'] ?? 0);
if ($postedState < 1) {
    foreach ($states as $s) {
        if (strcasecmp($s['name'], 'Lagos') === 0) {
            $postedState = (int) $s['id'];
            break;
        }
    }
}
$cities = [];
if ($postedState > 0) {
    $cityStmt = db()->prepare('SELECT id, name FROM cities WHERE state_id = ? ORDER BY name ASC');
    $cityStmt->execute([$postedState]);
    $cities = $cityStmt->fetchAll();
}
$postedCity = (int) ($_POST['city_id'] ?? 0);
$stateFee = SHIPPING_FLAT;
foreach ($states as $s) {
    if ((int) $s['id'] === $postedState) {
        $stateFee = (int) $s['shipping_fee'];
        break;
    }
}
$shipping = shipping_quote($subtotal, $stateFee, $allFree);
$total = $subtotal + $shipping;
$gateways = payment_gateways_available();
$postedPay = (string) ($_POST['payment_method'] ?? 'pay_on_delivery');
if (!isset($gateways[$postedPay]) || !$gateways[$postedPay]) {
    $postedPay = 'pay_on_delivery';
}

$pageTitle = 'Checkout — ' . get_settings()['site_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <div class="section-head"><h2>Checkout</h2></div>
    <?php if ($error): ?><div class="flash flash--error"><div class="container" style="padding:0"><?= e($error) ?></div></div><?php endif; ?>
    <?php if (!$user): ?>
      <p class="muted" style="margin-top:-.4rem">You can check out as a guest. Create an account only if you want to track orders later.</p>
    <?php endif; ?>
    <form method="post" class="checkout-layout" id="checkout-form"
      data-subtotal="<?= $subtotal ?>"
      data-free="<?= $allFree || $subtotal >= SHIPPING_FREE_OVER ? '1' : '0' ?>"
      data-locations="<?= e(url('api/locations.php')) ?>">
      <?= csrf_field() ?>
      <div class="panel">
        <h3 style="font-family:var(--font-display);margin:0 0 1rem">Delivery details</h3>
        <div class="form-group">
          <label for="full_name">Full name</label>
          <input class="form-control" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? ($user['name'] ?? '')) ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" required value="<?= e($_POST['phone'] ?? ($user['phone'] ?? '')) ?>">
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" <?= $user ? '' : 'required' ?> value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>" <?= $user ? 'readonly' : '' ?>>
          </div>
        </div>
        <div class="form-group">
          <label for="address">Street address</label>
          <input class="form-control" id="address" name="address" required value="<?= e($_POST['address'] ?? '') ?>" placeholder="Street, area">
        </div>
        <div class="form-group">
          <label for="country_id">Country</label>
          <select class="form-control" id="country_id" name="country_id" required>
            <?php foreach ($countries as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $postedCountry ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="state_id">State</label>
            <select class="form-control" id="state_id" name="state_id" required>
              <option value="">Select state</option>
              <?php foreach ($states as $s): ?>
                <option value="<?= (int) $s['id'] ?>" data-fee="<?= (int) $s['shipping_fee'] ?>" <?= (int) $s['id'] === $postedState ? 'selected' : '' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="city_id">City</label>
            <select class="form-control" id="city_id" name="city_id" required>
              <option value="">Select city</option>
              <?php foreach ($cities as $city): ?>
                <option value="<?= (int) $city['id'] ?>" <?= (int) $city['id'] === $postedCity ? 'selected' : '' ?>><?= e($city['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if (!$user): ?>
          <label class="checkbox-row" style="margin:1rem 0 .5rem">
            <input type="checkbox" name="create_account" id="create_account" <?= isset($_POST['create_account']) ? 'checked' : '' ?>>
            Create an account with this email
          </label>
          <div id="account-fields" <?= isset($_POST['create_account']) ? '' : 'hidden' ?>>
            <div class="form-row">
              <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" minlength="6" autocomplete="new-password">
              </div>
              <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="6" autocomplete="new-password">
              </div>
            </div>
          </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Payment method</label>
          <div class="pay-options">
            <label class="pay-option">
              <input type="radio" name="payment_method" value="pay_on_delivery" <?= $postedPay === 'pay_on_delivery' ? 'checked' : '' ?>>
              <span><strong>Pay on delivery</strong><small>Cash when your parcel arrives</small></span>
            </label>
            <?php if (!empty($gateways['paystack'])): ?>
            <label class="pay-option">
              <input type="radio" name="payment_method" value="paystack" <?= $postedPay === 'paystack' ? 'checked' : '' ?>>
              <span><strong>Paystack</strong><small>Card, bank transfer, USSD</small></span>
            </label>
            <?php endif; ?>
            <?php if (!empty($gateways['flutterwave'])): ?>
            <label class="pay-option">
              <input type="radio" name="payment_method" value="flutterwave" <?= $postedPay === 'flutterwave' ? 'checked' : '' ?>>
              <span><strong>Flutterwave</strong><small>Card, bank, mobile money</small></span>
            </label>
            <?php endif; ?>
          </div>
          <?php if (empty($gateways['paystack']) && empty($gateways['flutterwave'])): ?>
            <p class="muted" style="margin:.5rem 0 0;font-size:.85rem"></p>
          <?php endif; ?>
        </div>
      </div>
      <aside class="panel">
        <h3 style="font-family:var(--font-display);margin:0 0 .75rem">Summary</h3>
        <?php foreach ($lines as $line): ?>
          <div class="summary-row"><span><?= e($line['product']['name']) ?><?= $line['variant_label'] !== '' ? ' (' . e($line['variant_label']) . ')' : '' ?> × <?= (int) $line['qty'] ?></span><span><?= e(money_ngn($line['line_total'])) ?></span></div>
        <?php endforeach; ?>
        <div class="summary-row"><span>Shipping</span><span id="ship-amount"><?= $shipping === 0 ? 'Free' : e(money_ngn($shipping)) ?></span></div>
        <div class="summary-row total"><span>Total</span><span id="grand-total"><?= e(money_ngn($total)) ?></span></div>
        <button type="submit" class="btn btn-brand btn-block" style="margin-top:1rem">Place order</button>
      </aside>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
