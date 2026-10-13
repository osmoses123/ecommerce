<?php
declare(strict_types=1);

/**
 * Shopora installer — creates DB, tables, and seed data. Safe to re-run.
 */

$configPath = __DIR__ . '/includes/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo 'Missing includes/config.php';
    exit;
}
require_once $configPath;

$messages = [];
$errors = [];
$ok = false;

function install_pdo_server(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PDO MySQL extension is not enabled. Enable extension=pdo_mysql in php.ini.');
    }

    $server = install_pdo_server();
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $messages[] = 'Database `' . DB_NAME . '` is ready.';

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  icon_label VARCHAR(16) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  price INT NOT NULL,
  compare_price INT NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  is_flash TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  rating DECIMAL(2,1) NOT NULL DEFAULT 4.5,
  review_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  order_number VARCHAR(32) NOT NULL UNIQUE,
  status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  subtotal INT NOT NULL,
  shipping INT NOT NULL,
  total INT NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'pay_on_delivery',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(200) NOT NULL,
  unit_price INT NOT NULL,
  qty INT NOT NULL,
  line_total INT NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

    $messages[] = 'Tables created (or already present).';

    $defaults = [
        'site_title' => 'Shopora',
        'site_description' => 'Shop phones, fashion, appliances and more across Nigeria — pay on delivery, fast Lagos dispatch.',
        'primary_color' => '#F05A1A',
        'logo_path' => 'uploads/branding/default-logo.png',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => 'Shopora',
    ];
    $insSetting = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $insSetting->execute([$k, $v]);
    }
    $messages[] = 'Settings seeded.';

    $adminEmail = 'admin@shopora.test';
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)')->execute([
            'Store Owner',
            $adminEmail,
            password_hash('Admin@12345', PASSWORD_DEFAULT),
        ]);
        $messages[] = 'Admin account created.';
    } else {
        $messages[] = 'Admin account already exists — skipped.';
    }

    $custEmail = 'ada@shopora.test';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$custEmail]);
    $userRow = $stmt->fetch();
    if (!$userRow) {
        $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)')->execute([
            'Ada Okonkwo',
            $custEmail,
            '08031234567',
            password_hash('Customer@123', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $messages[] = 'Demo customer created.';
    } else {
        $userId = (int) $userRow['id'];
        $messages[] = 'Demo customer already exists — skipped.';
    }

    $cats = [
        ['Phones & Tablets', 'phones-tablets', '📱', 1],
        ['Computing', 'computing', '💻', 2],
        ['Electronics', 'electronics', '🎧', 3],
        ['Fashion', 'fashion', '👟', 4],
        ['Beauty', 'beauty', '✨', 5],
        ['Home & Kitchen', 'home-kitchen', '🏠', 6],
        ['Appliances', 'appliances', '🧊', 7],
        ['Groceries', 'groceries', '🛒', 8],
    ];
    $catIds = [];
    $catCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($catCount === 0) {
        $insCat = $pdo->prepare('INSERT INTO categories (name, slug, icon_label, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($cats as $c) {
            $insCat->execute($c);
            $catIds[$c[1]] = (int) $pdo->lastInsertId();
        }
        $messages[] = 'Categories seeded.';
    } else {
        foreach ($pdo->query('SELECT id, slug FROM categories') as $row) {
            $catIds[$row['slug']] = (int) $row['id'];
        }
        $messages[] = 'Categories already present — skipped.';
    }

    $products = [
        ['phones-tablets', 'Aura Spark X5 Smartphone', 'aura-spark-x5', 'Bright 6.6" display, long battery, and dual SIM for daily Lagos hustle. Ships with charger and screen guard. Ideal as a reliable everyday phone.', 128500, 185000, 42, 'uploads/products/phone-spark.png', 1, 1, 4.6, 214],
        ['phones-tablets', 'Nova Note 12 Pro', 'nova-note-12-pro', 'Large AMOLED-style screen and fast charging for streaming and work chats. Plenty of storage for photos and offline maps. Solid mid-range pick.', 219000, 265000, 28, 'uploads/products/phone-note.png', 0, 1, 4.7, 156],
        ['computing', 'OrbitBook 15 Laptop', 'orbitbook-15', '15.6" laptop for school, office docs, and light design. SSD boot means less waiting. Comes ready for Windows updates out of the box.', 485000, 560000, 12, 'uploads/products/laptop-pavilion.png', 0, 1, 4.5, 89],
        ['computing', 'SwiftClick Wireless Mouse', 'swiftclick-mouse', 'Quiet clicks and a compact shape that fits most hands. Works with USB receiver — no software needed. Great spare for the office.', 8500, 12000, 120, 'uploads/products/mouse-wireless.png', 1, 0, 4.3, 401],
        ['electronics', 'PulseBuds ANC Earbuds', 'pulsebuds-anc', 'Active noise control for danfo rides and open offices. Clear calls and a case that lasts the workweek. Sweat-resistant for gym sessions.', 45000, 69000, 65, 'uploads/products/earbuds-noise.png', 1, 1, 4.4, 322],
        ['electronics', 'Vista 43" Smart LED TV', 'vista-43-led', 'Full HD streaming on a slim set that fits small parlours. Built-in apps for your favourite shows. Wall-mount ready with clean cable routing.', 198000, 245000, 18, 'uploads/products/tv-led43.png', 0, 1, 4.6, 77],
        ['fashion', 'Lagos Runner Sneakers', 'lagos-runner', 'Cushioned soles for market walks and weekend errands. Breathable upper in orange-white finish. True-to-size for most feet.', 28500, 42000, 80, 'uploads/products/sneakers-runner.png', 1, 0, 4.5, 510],
        ['fashion', 'Ankara Midi Dress', 'ankara-midi-dress', 'Bold print midi with side pockets and soft lining. Ready for owambe or Sunday service. Machine wash cold, hang dry.', 32000, null, 35, 'uploads/products/dress-ankara.png', 0, 1, 4.8, 143],
        ['beauty', 'GlowC Vitamin Serum', 'glowc-serum', 'Lightweight vitamin C serum for dull skin after harmattan. Absorbs fast under moisturizer. Use SPF in the daytime.', 12500, 18000, 90, 'uploads/products/serum-vitc.png', 1, 0, 4.2, 268],
        ['beauty', 'Pure Shea Body Cream', 'pure-shea-cream', 'Rich shea cream that locks in moisture without a greasy finish. Unscented enough for sensitive skin. Jar lasts weeks of daily use.', 7800, null, 150, 'uploads/products/cream-shea.png', 0, 0, 4.7, 390],
        ['home-kitchen', 'Forge Nonstick Cookware Set', 'forge-cookware', 'Pots and pans with even heat for jollof and stews. Compatible with gas cookers common in Nigerian kitchens. Dishwasher-safe lids.', 56000, 78000, 22, 'uploads/products/cookware-set.png', 0, 1, 4.4, 95],
        ['home-kitchen', 'Pearl Ceramic Dinner Set', 'pearl-dinner-set', 'Service for six — plates that look sharp on the dining table. Chip-resistant glaze for everyday meals. Stacks neatly in small cupboards.', 24000, 30000, 40, 'uploads/products/plates-ceramic.png', 0, 0, 4.1, 62],
        ['appliances', 'BlendMax Countertop Blender', 'blendmax-blender', '1.5L jar for smoothies, pepper, and soups. Strong motor that handles ice and tough veggies. Easy-clean blade assembly.', 38500, 52000, 30, 'uploads/products/blender-counter.png', 1, 0, 4.5, 188],
        ['appliances', 'ChillTwin 200L Fridge', 'chilltwin-200', 'Double-door fridge with freezer space for leftovers and drinks. Quiet enough for studio flats. Efficient for NEPA-aware homes.', 275000, 320000, 8, 'uploads/products/fridge-double.png', 0, 1, 4.6, 54],
        ['groceries', 'Harvest Gold Rice 25kg', 'harvest-gold-rice-25kg', 'Parboiled long-grain rice for family pots and bukka kitchens. Clean grains with consistent cook. Sealed bag for freshness.', 42000, null, 55, 'uploads/products/rice-25kg.png', 0, 0, 4.3, 720],
        ['groceries', 'RedGold Palm Oil 5L', 'redgold-palm-oil-5l', 'Deep colour palm oil for stews and frying. Food-grade jerry can with tight seal. Popular size for busy households.', 14500, 17500, 70, 'uploads/products/oil-palm5l.png', 1, 0, 4.0, 210],
    ];

    $prodCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($prodCount === 0) {
        $insP = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, compare_price, stock, image, is_flash, is_featured, rating, review_count) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($products as $p) {
            $cid = $catIds[$p[0]] ?? null;
            if (!$cid) {
                continue;
            }
            $insP->execute([$cid, $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11]]);
        }
        $messages[] = '16 products seeded.';
    } else {
        $messages[] = 'Products already present — skipped.';
    }

    $orderCount = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    if ($orderCount === 0 && $userId > 0) {
        $allProducts = $pdo->query('SELECT id, name, price FROM products ORDER BY id ASC LIMIT 6')->fetchAll();
        if (count($allProducts) >= 3) {
            $ordersSeed = [
                ['SHP-A1B2C3D4E5', 'delivered', 0, [$allProducts[0], $allProducts[1]]],
                ['SHP-F6G7H8I9J0', 'processing', -2, [$allProducts[2], $allProducts[3]]],
                ['SHP-K1L2M3N4O5', 'pending', -1, [$allProducts[4]]],
            ];
            $insO = $pdo->prepare('INSERT INTO orders (user_id, order_number, status, subtotal, shipping, total, full_name, phone, address, city, state, payment_method, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $insI = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty, line_total) VALUES (?,?,?,?,?,?)');
            foreach ($ordersSeed as $os) {
                $sub = 0;
                $lines = [];
                foreach ($os[3] as $prod) {
                    $qty = 1;
                    $lt = (int) $prod['price'] * $qty;
                    $sub += $lt;
                    $lines[] = [$prod, $qty, $lt];
                }
                $ship = $sub >= 50000 ? 0 : 1500;
                $created = (new DateTimeImmutable('now'))->modify($os[2] . ' days')->format('Y-m-d H:i:s');
                $insO->execute([
                    $userId, $os[0], $os[1], $sub, $ship, $sub + $ship,
                    'Ada Okonkwo', '08031234567', '14 Admiralty Way, Lekki Phase 1', 'Lagos', 'Lagos', 'pay_on_delivery', $created,
                ]);
                $oid = (int) $pdo->lastInsertId();
                foreach ($lines as $ln) {
                    $insI->execute([$oid, $ln[0]['id'], $ln[0]['name'], $ln[0]['price'], $ln[1], $ln[2]]);
                }
            }
            $messages[] = 'Sample orders seeded for Ada.';
        }
    } else {
        $messages[] = 'Orders already present or skipped.';
    }

    require_once __DIR__ . '/includes/migrate.php';
    migrate_schema($pdo);
    $messages[] = 'Extra tables (banners, locations, wishlist) are ready.';

    $ok = true;
    $messages[] = 'Install complete.';
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopora Install</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#F4F5F7;color:#14181F;margin:0;padding:2rem}
        .box{max-width:640px;margin:0 auto;background:#fff;border:1px solid #E6E8EC;border-radius:12px;padding:1.5rem 1.75rem}
        h1{font-size:1.4rem;margin:0 0 .5rem}
        .ok{color:#0F9F6E} .err{color:#E11D2E}
        ul{padding-left:1.2rem} code{background:#F4F5F7;padding:.1rem .35rem;border-radius:4px}
        a{color:#F05A1A}
    </style>
</head>
<body>
<div class="box">
    <h1>Shopora installer</h1>
    <p>Creates the <code>ecommerce</code> database, tables, and demo data for XAMPP (root / empty password).</p>
    <?php if ($errors): ?>
        <p class="err"><strong>Install failed</strong></p>
        <ul><?php foreach ($errors as $err): ?><li class="err"><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
        <p>Make sure MySQL is running in XAMPP, then refresh this page.</p>
    <?php else: ?>
        <p class="ok"><strong>Success</strong></p>
        <ul><?php foreach ($messages as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach; ?></ul>
        <h2>Credentials</h2>
        <ul>
            <li><strong>Admin:</strong> admin@shopora.test / Admin@12345 → <a href="<?= htmlspecialchars(BASE_URL . '/admin/login.php') ?>">Admin login</a></li>
            <li><strong>Customer:</strong> ada@shopora.test / Customer@123 → <a href="<?= htmlspecialchars(BASE_URL . '/login.php') ?>">Store login</a></li>
        </ul>
        <p><a href="<?= htmlspecialchars(BASE_URL . '/index.php') ?>">Open storefront</a></p>
    <?php endif; ?>
</div>
</body>
</html>
