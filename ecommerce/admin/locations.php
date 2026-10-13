<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_country') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $iso = strtoupper(trim((string) ($_POST['iso2'] ?? '')));
            if ($name === '' || !preg_match('/^[A-Z]{2}$/', $iso)) {
                throw new RuntimeException('Country name and a 2-letter code are required.');
            }
            $isDefault = isset($_POST['is_default']) ? 1 : 0;
            if ($isDefault) {
                db()->exec('UPDATE countries SET is_default = 0');
            }
            db()->prepare('INSERT INTO countries (name, iso2, is_default) VALUES (?,?,?)')->execute([$name, $iso, $isDefault]);
            flash_set('success', 'Country added.');
        } elseif ($action === 'default_country') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->exec('UPDATE countries SET is_default = 0');
            db()->prepare('UPDATE countries SET is_default = 1 WHERE id = ?')->execute([$id]);
            flash_set('success', 'Default country updated.');
        } elseif ($action === 'delete_country') {
            db()->prepare('DELETE FROM countries WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash_set('success', 'Country removed, including its states and cities.');
        } elseif ($action === 'add_state') {
            $countryId = (int) ($_POST['country_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $fee = max(0, (int) ($_POST['shipping_fee'] ?? 0));
            if ($countryId < 1 || $name === '') {
                throw new RuntimeException('Pick a country and enter a state name.');
            }
            db()->prepare('INSERT INTO states (country_id, name, shipping_fee) VALUES (?,?,?)')->execute([$countryId, $name, $fee]);
            flash_set('success', 'State added.');
        } elseif ($action === 'save_fee') {
            db()->prepare('UPDATE states SET shipping_fee = ? WHERE id = ?')->execute([max(0, (int) ($_POST['shipping_fee'] ?? 0)), (int) ($_POST['id'] ?? 0)]);
            flash_set('success', 'Shipping fee saved.');
        } elseif ($action === 'delete_state') {
            db()->prepare('DELETE FROM states WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash_set('success', 'State removed.');
        } elseif ($action === 'add_city') {
            $stateId = (int) ($_POST['state_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($stateId < 1 || $name === '') {
                throw new RuntimeException('Pick a state and enter a city name.');
            }
            db()->prepare('INSERT INTO cities (state_id, name) VALUES (?,?)')->execute([$stateId, $name]);
            flash_set('success', 'City added.');
        } elseif ($action === 'delete_city') {
            db()->prepare('DELETE FROM cities WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash_set('success', 'City removed.');
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    $back = 'admin/locations.php';
    $qs = [];
    if (!empty($_POST['country_id'])) {
        $qs[] = 'country_id=' . (int) $_POST['country_id'];
    }
    if (!empty($_POST['state_id'])) {
        $qs[] = 'state_id=' . (int) $_POST['state_id'];
    }
    if ($qs) {
        $back .= '?' . implode('&', $qs);
    }
    redirect($back);
}

$countries = db()->query('SELECT * FROM countries ORDER BY is_default DESC, name ASC')->fetchAll();
$countryId = (int) ($_GET['country_id'] ?? 0);
if ($countryId < 1 && $countries) {
    foreach ($countries as $c) {
        if (!empty($c['is_default'])) {
            $countryId = (int) $c['id'];
            break;
        }
    }
    if ($countryId < 1) {
        $countryId = (int) $countries[0]['id'];
    }
}
$states = [];
if ($countryId > 0) {
    $st = db()->prepare('SELECT * FROM states WHERE country_id = ? ORDER BY name ASC');
    $st->execute([$countryId]);
    $states = $st->fetchAll();
}
$stateId = (int) ($_GET['state_id'] ?? 0);
if ($stateId < 1 && $states) {
    foreach ($states as $s) {
        if (strcasecmp($s['name'], 'Lagos') === 0) {
            $stateId = (int) $s['id'];
            break;
        }
    }
    if ($stateId < 1) {
        $stateId = (int) $states[0]['id'];
    }
}
$cities = [];
if ($stateId > 0) {
    $ct = db()->prepare('SELECT * FROM cities WHERE state_id = ? ORDER BY name ASC');
    $ct->execute([$stateId]);
    $cities = $ct->fetchAll();
}

ob_start();
?>
<p class="muted" style="margin-top:-.4rem">Checkout asks shoppers to pick a country, state, and city. Nigeria is the default. Shipping uses the state fee unless the order qualifies for free delivery.</p>
<div class="panel-grid">
  <div class="panel">
    <h2>Countries</h2>
    <form method="post" class="form-row" style="align-items:end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_country">
      <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
      <div class="form-group"><label>Code</label><input type="text" name="iso2" maxlength="2" required placeholder="NG"></div>
      <div class="form-group"><button class="btn btn-brand" type="submit">Add</button></div>
    </form>
    <table class="data">
      <thead><tr><th>Country</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($countries as $c): ?>
          <tr>
            <td>
              <a href="<?= e(url('admin/locations.php?country_id=' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a>
              <span class="muted"><?= e($c['iso2']) ?></span>
              <?= !empty($c['is_default']) ? '<span class="status-pill status-delivered">Default</span>' : '' ?>
            </td>
            <td style="white-space:nowrap">
              <?php if (empty($c['is_default'])): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="default_country">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Make default</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this country and all of its states and cities?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_country">
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
    <h2>States</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_state">
      <div class="form-group">
        <label for="country_id">Country</label>
        <select id="country_id" name="country_id">
          <?php foreach ($countries as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $countryId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group"><label>State</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Shipping (₦)</label><input type="number" name="shipping_fee" min="0" value="1500"></div>
      </div>
      <button class="btn btn-brand" type="submit">Add state</button>
    </form>
    <table class="data">
      <thead><tr><th>State</th><th>Fee</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($states as $s): ?>
          <tr>
            <td><a href="<?= e(url('admin/locations.php?country_id=' . $countryId . '&state_id=' . $s['id'])) ?>"><?= e($s['name']) ?></a></td>
            <td>
              <form method="post" style="display:flex;gap:.35rem;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_fee">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <input type="hidden" name="country_id" value="<?= $countryId ?>">
                <input type="number" name="shipping_fee" min="0" value="<?= (int) $s['shipping_fee'] ?>" style="width:110px">
                <button class="btn btn-ghost btn-sm" type="submit">Save</button>
              </form>
            </td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this state and its cities?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_state">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <input type="hidden" name="country_id" value="<?= $countryId ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="panel">
  <h2>Cities</h2>
  <form method="post" class="form-row" style="align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_city">
    <input type="hidden" name="country_id" value="<?= $countryId ?>">
    <div class="form-group">
      <label for="state_id">State</label>
      <select id="state_id" name="state_id">
        <?php foreach ($states as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $stateId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>City</label><input type="text" name="name" required></div>
    <div class="form-group"><button class="btn btn-brand" type="submit">Add city</button></div>
  </form>
  <table class="data">
    <thead><tr><th>City</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($cities as $city): ?>
        <tr>
          <td><?= e($city['name']) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Delete this city?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_city">
              <input type="hidden" name="id" value="<?= (int) $city['id'] ?>">
              <input type="hidden" name="country_id" value="<?= $countryId ?>">
              <input type="hidden" name="state_id" value="<?= $stateId ?>">
              <button class="btn btn-danger btn-sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$adminContent = ob_get_clean();
$adminPageTitle = 'Shipping locations';
$adminPageSubtitle = 'Countries, states, and cities';
$adminActive = 'locations.php';
require __DIR__ . '/_layout.php';
