<?php
declare(strict_types=1);

function migrate_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function migrate_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!migrate_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function migrate_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS vendors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  is_inhouse TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pimg_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot ENUM('hero','side') NOT NULL DEFAULT 'hero',
  title VARCHAR(160) NOT NULL,
  subtitle VARCHAR(255) NOT NULL DEFAULT '',
  button_label VARCHAR(80) NOT NULL DEFAULT '',
  link_url VARCHAR(255) NOT NULL DEFAULT '',
  image VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS home_columns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position TINYINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(255) NOT NULL DEFAULT '',
  button_label VARCHAR(80) NOT NULL DEFAULT '',
  link_url VARCHAR(255) NOT NULL DEFAULT '',
  image VARCHAR(255) NULL,
  UNIQUE KEY uq_home_col_pos (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS countries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  iso2 CHAR(2) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_country_iso (iso2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS states (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  shipping_fee INT NOT NULL DEFAULT 1500,
  CONSTRAINT fk_state_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  state_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  CONSTRAINT fk_city_state FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS variant_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('size','colour','material') NOT NULL,
  label VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_variant_option (kind, label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_tiers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  min_qty INT NOT NULL,
  discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  discount_value INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_tier_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  size_id INT UNSIGNED NULL,
  colour_id INT UNSIGNED NULL,
  material_id INT UNSIGNED NULL,
  sku VARCHAR(80) NULL,
  price INT NULL,
  stock INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pv_size FOREIGN KEY (size_id) REFERENCES variant_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_pv_colour FOREIGN KEY (colour_id) REFERENCES variant_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_pv_material FOREIGN KEY (material_id) REFERENCES variant_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wishlists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wish (user_id, product_id),
  CONSTRAINT fk_wish_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wish_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

    migrate_add_column($pdo, 'products', 'sku', 'VARCHAR(80) NULL');
    migrate_add_column($pdo, 'products', 'vendor_id', 'INT UNSIGNED NULL');
    migrate_add_column($pdo, 'products', 'status', "ENUM('active','draft') NOT NULL DEFAULT 'active'");
    migrate_add_column($pdo, 'products', 'free_delivery', 'TINYINT(1) NOT NULL DEFAULT 0');
    migrate_add_column($pdo, 'products', 'is_sponsored', 'TINYINT(1) NOT NULL DEFAULT 0');
    migrate_add_column($pdo, 'products', 'whatsapp_checkout', 'TINYINT(1) NOT NULL DEFAULT 0');

    $idx = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $idx->execute(['products', 'uq_products_sku']);
    if ((int) $idx->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE products ADD UNIQUE KEY uq_products_sku (sku)');
    }

    $nullable = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'")->fetchColumn();
    if ($nullable === 'NO') {
        $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users' LIMIT 1")->fetchColumn();
        if ($fk) {
            $pdo->exec('ALTER TABLE orders DROP FOREIGN KEY `' . str_replace('`', '', (string) $fk) . '`');
        }
        $pdo->exec('ALTER TABLE orders MODIFY user_id INT UNSIGNED NULL');
        $pdo->exec('ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)');
    }

    migrate_add_column($pdo, 'orders', 'guest_email', 'VARCHAR(190) NULL');
    migrate_add_column($pdo, 'orders', 'country', "VARCHAR(80) NOT NULL DEFAULT 'Nigeria'");
    migrate_add_column($pdo, 'orders', 'payment_status', "VARCHAR(20) NOT NULL DEFAULT 'unpaid'");
    migrate_add_column($pdo, 'orders', 'payment_reference', 'VARCHAR(120) NULL');

    $ins = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
    foreach ([
        'whatsapp_number' => '2348012345678',
        'paystack_public_key' => '',
        'paystack_secret_key' => '',
        'paystack_enabled' => '0',
        'flutterwave_public_key' => '',
        'flutterwave_secret_key' => '',
        'flutterwave_enabled' => '0',
        'footer_about' => 'Shop phones, fashion, appliances and more across Nigeria — pay on delivery, fast Lagos dispatch.',
        'footer_shop_heading' => 'Shop',
        'footer_account_heading' => 'Account',
        'footer_help_heading' => 'Help',
        'footer_help' => "Pay on delivery in Lagos, Abuja, Port Harcourt & Ibadan\nEasy returns within 7 days\nSupport: help@shopora.test",
        'footer_copyright' => 'iClose Logistics and Corporate Services. Prices in Naira.',
        'topbar_city' => 'Deliver to Lagos',
        'topbar_help' => 'Need help? Call 0700-SHOPORA',
        'topbar_extra' => 'Sell with us',
    ] as $k => $v) {
        $ins->execute([$k, $v]);
    }

    migrate_once($pdo, 'seeded_vendor', static function () use ($pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM vendors')->fetchColumn() > 0) {
            return;
        }
        $title = 'Shopora';
        $row = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'site_title'")->fetchColumn();
        if (is_string($row) && $row !== '') {
            $title = $row;
        }
        $pdo->prepare('INSERT INTO vendors (name, is_inhouse) VALUES (?, 1)')->execute([$title]);
    });

    migrate_once($pdo, 'seeded_banners', static function () use ($pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn() > 0) {
            return;
        }
        $b = $pdo->prepare('INSERT INTO banners (slot, title, subtitle, button_label, link_url, image, sort_order, is_active) VALUES (?,?,?,?,?,?,?,1)');
        $b->execute(['hero', 'Flash Friday is live', 'Cut prices on phones, fashion and kitchen staples — pay on delivery across Lagos.', 'Shop deals', 'shop.php?sort=discount', 'uploads/banners/banner-flash-friday.png', 1]);
        $b->execute(['hero', 'New phones, honest prices', 'Everyday smartphones with warranty and pay on delivery.', 'Browse phones', 'shop.php?category=phones-tablets', 'uploads/products/phone-note.png', 2]);
        $b->execute(['side', 'Pay on delivery', '', '', 'shop.php?category=appliances', 'uploads/banners/banner-pay-on-delivery.png', 1]);
        $b->execute(['side', 'Phones from ₦128,500', '', '', 'shop.php?category=phones-tablets', 'uploads/products/phone-spark.png', 2]);
    });

    migrate_once($pdo, 'seeded_home_columns', static function () use ($pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM home_columns')->fetchColumn() > 0) {
            return;
        }
        $h = $pdo->prepare('INSERT INTO home_columns (position, title, body, button_label, link_url, image) VALUES (?,?,?,?,?,?)');
        $h->execute([1, 'Home & kitchen', 'Cookware and small appliances for everyday Nigerian kitchens.', 'Shop the aisle', 'shop.php?category=home-kitchen', 'uploads/products/cookware-set.png']);
        $h->execute([2, 'Phones this week', 'Reliable everyday phones with pay on delivery.', 'See phones', 'shop.php?category=phones-tablets', 'uploads/products/phone-spark.png']);
    });

    if ((int) $pdo->query('SELECT COUNT(*) FROM countries')->fetchColumn() === 0) {
        migrate_seed_locations($pdo);
    }

    migrate_once($pdo, 'seeded_variant_options', static function () use ($pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM variant_options')->fetchColumn() > 0) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO variant_options (kind, label, sort_order) VALUES (?,?,?)');
        $sets = [
            'size' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '32', '34', '36', '38', '40', '42'],
            'colour' => ['Black', 'White', 'Red', 'Blue', 'Green', 'Orange', 'Brown', 'Grey', 'Navy', 'Beige'],
            'material' => ['Cotton', 'Polyester', 'Denim', 'Leather', 'Silk', 'Plastic', 'Metal', 'Wood'],
        ];
        foreach ($sets as $kind => $labels) {
            foreach ($labels as $i => $label) {
                $ins->execute([$kind, $label, $i + 1]);
            }
        }
    });

    migrate_once($pdo, 'seeded_product_extras', static function () use ($pdo): void {
        $pdo->exec("UPDATE products SET whatsapp_checkout = 1, is_sponsored = 1 WHERE slug = 'aura-spark-x5'");
        $pdo->exec("UPDATE products SET free_delivery = 1, is_sponsored = 1 WHERE slug = 'pulsebuds-anc'");
        $pid = $pdo->query("SELECT id FROM products WHERE slug = 'aura-spark-x5'")->fetchColumn();
        if ($pid && (int) $pdo->query('SELECT COUNT(*) FROM product_images WHERE product_id = ' . (int) $pid)->fetchColumn() === 0) {
            $img = $pdo->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)');
            $img->execute([(int) $pid, 'uploads/products/phone-note.png', 1]);
        }
    });
}

function migrate_once(PDO $pdo, string $key, callable $seed): void
{
    $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    if ($stmt->fetchColumn() !== false) {
        return;
    }
    $seed();
    $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)')->execute([$key, '1']);
}

function migrate_seed_locations(PDO $pdo): void
{
    $tree = [
        ['Nigeria', 'NG', 1, [
            ['Abia', 2500, ['Umuahia', 'Aba']],
            ['Adamawa', 2500, ['Yola', 'Mubi']],
            ['Akwa Ibom', 2500, ['Uyo', 'Eket']],
            ['Anambra', 2500, ['Awka', 'Onitsha', 'Nnewi']],
            ['Bauchi', 2500, ['Bauchi', 'Azare']],
            ['Bayelsa', 2500, ['Yenagoa']],
            ['Benue', 2500, ['Makurdi', 'Gboko']],
            ['Borno', 2500, ['Maiduguri', 'Biu']],
            ['Cross River', 2500, ['Calabar', 'Ikom']],
            ['Delta', 2500, ['Asaba', 'Warri']],
            ['Ebonyi', 2500, ['Abakaliki', 'Afikpo']],
            ['Edo', 2500, ['Benin City', 'Auchi']],
            ['Ekiti', 2500, ['Ado-Ekiti', 'Ikere']],
            ['Enugu', 2500, ['Enugu', 'Nsukka']],
            ['FCT', 2000, ['Abuja', 'Gwagwalada', 'Kubwa']],
            ['Gombe', 2500, ['Gombe', 'Kaltungo']],
            ['Imo', 2500, ['Owerri', 'Orlu']],
            ['Jigawa', 2500, ['Dutse', 'Hadejia']],
            ['Kaduna', 2000, ['Kaduna', 'Zaria']],
            ['Kano', 2000, ['Kano', 'Wudil']],
            ['Katsina', 2500, ['Katsina', 'Daura']],
            ['Kebbi', 2500, ['Birnin Kebbi', 'Argungu']],
            ['Kogi', 2500, ['Lokoja', 'Okene']],
            ['Kwara', 2500, ['Ilorin', 'Offa']],
            ['Lagos', 1500, ['Lagos', 'Ikeja', 'Lekki', 'Ikorodu', 'Epe']],
            ['Nasarawa', 2500, ['Lafia', 'Keffi']],
            ['Niger', 2500, ['Minna', 'Bida']],
            ['Ogun', 1500, ['Abeokuta', 'Sagamu', 'Ijebu Ode']],
            ['Ondo', 2500, ['Akure', 'Ondo']],
            ['Osun', 2500, ['Osogbo', 'Ile-Ife']],
            ['Oyo', 2000, ['Ibadan', 'Ogbomoso', 'Oyo']],
            ['Plateau', 2500, ['Jos', 'Bukuru']],
            ['Rivers', 2000, ['Port Harcourt', 'Bonny']],
            ['Sokoto', 2500, ['Sokoto']],
            ['Taraba', 2500, ['Jalingo', 'Wukari']],
            ['Yobe', 2500, ['Damaturu', 'Potiskum']],
            ['Zamfara', 2500, ['Gusau']],
        ]],
        ['Ghana', 'GH', 0, [
            ['Greater Accra', 8000, ['Accra', 'Tema']],
            ['Ashanti', 9000, ['Kumasi']],
            ['Western', 9000, ['Takoradi']],
        ]],
        ['Kenya', 'KE', 0, [
            ['Nairobi', 12000, ['Nairobi']],
            ['Mombasa', 14000, ['Mombasa']],
        ]],
        ['South Africa', 'ZA', 0, [
            ['Gauteng', 15000, ['Johannesburg', 'Pretoria']],
            ['Western Cape', 16000, ['Cape Town']],
        ]],
        ['United Kingdom', 'GB', 0, [
            ['England', 18000, ['London', 'Manchester', 'Birmingham']],
            ['Scotland', 19000, ['Edinburgh', 'Glasgow']],
        ]],
        ['United States', 'US', 0, [
            ['California', 22000, ['Los Angeles', 'San Francisco']],
            ['New York', 22000, ['New York']],
            ['Texas', 21000, ['Houston', 'Dallas']],
        ]],
        ['Canada', 'CA', 0, [
            ['Ontario', 21000, ['Toronto', 'Ottawa']],
            ['British Columbia', 22000, ['Vancouver']],
        ]],
    ];

    $insC = $pdo->prepare('INSERT INTO countries (name, iso2, is_default) VALUES (?,?,?)');
    $insS = $pdo->prepare('INSERT INTO states (country_id, name, shipping_fee) VALUES (?,?,?)');
    $insCity = $pdo->prepare('INSERT INTO cities (state_id, name) VALUES (?,?)');
    foreach ($tree as $country) {
        $insC->execute([$country[0], $country[1], $country[2]]);
        $cid = (int) $pdo->lastInsertId();
        foreach ($country[3] as $state) {
            $insS->execute([$cid, $state[0], $state[1]]);
            $sid = (int) $pdo->lastInsertId();
            foreach ($state[2] as $city) {
                $insCity->execute([$sid, $city]);
            }
        }
    }
}
