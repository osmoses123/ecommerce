<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_ngn(int|float $amount): string
{
    return '₦' . number_format((int) $amount, 0, '.', ',');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function redirect(string $path): never
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function unique_slug(PDO $pdo, string $table, string $base, ?int $excludeId = null): string
{
    $slug = slugify($base);
    $n = 0;
    while (true) {
        $try = $n === 0 ? $slug : $slug . '-' . $n;
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$try];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $try;
        }
        $n++;
    }
}

function settings_defaults(): array
{
    return [
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
    ];
}

function footer_help_lines(?string $text = null): array
{
    $text = $text ?? (string) (get_settings()['footer_help'] ?? '');
    $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

function cart_snapshot(): array
{
    $lines = [];
    foreach (cart_lines() as $line) {
        $p = $line['product'];
        $lines[] = [
            'key' => $line['key'],
            'name' => $p['name'],
            'variant' => $line['variant_label'] ?? '',
            'qty' => (int) $line['qty'],
            'price' => money_ngn((int) $line['unit_price']),
            'total' => money_ngn((int) $line['line_total']),
            'image' => product_image_url($p['image'] ?? null),
            'url' => url('product.php?slug=' . $p['slug']),
        ];
    }
    return [
        'count' => cart_count(),
        'subtotal' => money_ngn(cart_subtotal()),
        'cart_url' => url('cart.php'),
        'checkout_url' => url('checkout.php'),
        'lines' => $lines,
    ];
}

function wants_json(): bool
{
    if (($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
        return true;
    }
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($accept, 'application/json');
}

function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . url(ltrim($path, '/'));
}

function http_json(string $method, string $endpoint, array $headers = [], ?array $body = null): array
{
    $ch = curl_init($endpoint);
    $hdrs = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'message' => $err !== '' ? $err : 'Network error'];
    }
    $data = json_decode((string) $raw, true);
    return [
        'ok' => $code >= 200 && $code < 300,
        'status' => $code,
        'data' => is_array($data) ? $data : null,
        'message' => is_array($data) ? (string) ($data['message'] ?? '') : 'Invalid response',
        'raw' => (string) $raw,
    ];
}

function payment_gateways_available(): array
{
    $s = get_settings();
    return [
        'pay_on_delivery' => true,
        'paystack' => ($s['paystack_enabled'] ?? '') === '1'
            && trim((string) ($s['paystack_public_key'] ?? '')) !== ''
            && trim((string) ($s['paystack_secret_key'] ?? '')) !== '',
        'flutterwave' => ($s['flutterwave_enabled'] ?? '') === '1'
            && trim((string) ($s['flutterwave_public_key'] ?? '')) !== ''
            && trim((string) ($s['flutterwave_secret_key'] ?? '')) !== '',
    ];
}

function payment_label(string $method): string
{
    return match ($method) {
        'paystack' => 'Paystack',
        'flutterwave' => 'Flutterwave',
        'pay_on_delivery' => 'Pay on delivery',
        default => ucfirst(str_replace('_', ' ', $method)),
    };
}

function payment_status_label(string $status): string
{
    return match ($status) {
        'paid' => 'Paid',
        'pending' => 'Awaiting payment',
        'failed' => 'Failed',
        'unpaid' => 'Unpaid',
        default => ucfirst($status),
    };
}

function paystack_initialize(array $settings, string $email, int $amountNgn, string $reference, string $callbackUrl, array $meta = []): array
{
    $secret = trim((string) ($settings['paystack_secret_key'] ?? ''));
    $res = http_json('POST', 'https://api.paystack.co/transaction/initialize', [
        'Authorization: Bearer ' . $secret,
    ], [
        'email' => $email,
        'amount' => $amountNgn * 100,
        'currency' => 'NGN',
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => $meta,
    ]);
    if (!$res['ok'] || empty($res['data']['data']['authorization_url'])) {
        return ['ok' => false, 'message' => $res['message'] !== '' ? $res['message'] : 'Could not start Paystack payment.'];
    }
    return [
        'ok' => true,
        'url' => (string) $res['data']['data']['authorization_url'],
        'reference' => (string) ($res['data']['data']['reference'] ?? $reference),
    ];
}

function paystack_verify(array $settings, string $reference): array
{
    $secret = trim((string) ($settings['paystack_secret_key'] ?? ''));
    $res = http_json('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference), [
        'Authorization: Bearer ' . $secret,
    ]);
    $status = (string) ($res['data']['data']['status'] ?? '');
    $amount = (int) round(((float) ($res['data']['data']['amount'] ?? 0)) / 100);
    if (!$res['ok'] || $status !== 'success') {
        return ['ok' => false, 'message' => $res['message'] !== '' ? $res['message'] : 'Paystack payment was not successful.', 'amount' => $amount];
    }
    return ['ok' => true, 'amount' => $amount, 'reference' => (string) ($res['data']['data']['reference'] ?? $reference)];
}

function flutterwave_initialize(array $settings, string $email, string $name, string $phone, int $amountNgn, string $reference, string $redirectUrl): array
{
    $secret = trim((string) ($settings['flutterwave_secret_key'] ?? ''));
    $res = http_json('POST', 'https://api.flutterwave.com/v3/payments', [
        'Authorization: Bearer ' . $secret,
    ], [
        'tx_ref' => $reference,
        'amount' => $amountNgn,
        'currency' => 'NGN',
        'redirect_url' => $redirectUrl,
        'customer' => [
            'email' => $email,
            'name' => $name,
            'phonenumber' => $phone,
        ],
        'customizations' => [
            'title' => (string) ($settings['site_title'] ?? 'Shopora'),
            'description' => 'Order ' . $reference,
        ],
    ]);
    if (!$res['ok'] || empty($res['data']['data']['link'])) {
        return ['ok' => false, 'message' => $res['message'] !== '' ? $res['message'] : 'Could not start Flutterwave payment.'];
    }
    return ['ok' => true, 'url' => (string) $res['data']['data']['link'], 'reference' => $reference];
}

function flutterwave_verify(array $settings, string $reference): array
{
    $secret = trim((string) ($settings['flutterwave_secret_key'] ?? ''));
    $res = http_json('GET', 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . rawurlencode($reference), [
        'Authorization: Bearer ' . $secret,
    ]);
    $status = strtolower((string) ($res['data']['data']['status'] ?? ''));
    $amount = (int) round((float) ($res['data']['data']['amount'] ?? 0));
    if (!$res['ok'] || $status !== 'successful') {
        return ['ok' => false, 'message' => $res['message'] !== '' ? $res['message'] : 'Flutterwave payment was not successful.', 'amount' => $amount];
    }
    return ['ok' => true, 'amount' => $amount, 'reference' => $reference];
}

function mark_order_paid(int $orderId, string $reference): void
{
    db()->prepare("UPDATE orders SET payment_status = 'paid', payment_reference = ?, status = CASE WHEN status = 'pending' THEN 'processing' ELSE status END WHERE id = ?")
        ->execute([$reference, $orderId]);
}

function get_settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $defaults = settings_defaults();
    try {
        $stmt = db()->query('SELECT `key`, `value` FROM settings');
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $defaults[$row['key']] = (string) $row['value'];
        }
    } catch (Throwable $e) {
        // install not run yet
    }
    $cache = $defaults;
    return $cache;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    $stmt->execute([$key, $value]);
    get_settings(true);
}

function set_settings(array $pairs): void
{
    foreach ($pairs as $key => $value) {
        set_setting((string) $key, (string) $value);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $stmt = db()->prepare('SELECT id, name, email, phone FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function require_user(?string $returnTo = null): array
{
    $user = current_user();
    if ($user) {
        return $user;
    }
    $return = $returnTo ?? ($_SERVER['REQUEST_URI'] ?? url('account.php'));
    $_SESSION['return_to'] = $return;
    flash_set('error', 'Please log in to continue.');
    redirect('login.php');
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $admin = null;
    if ($admin !== null) {
        return $admin;
    }
    $stmt = db()->prepare('SELECT id, name, email FROM admins WHERE id = ?');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    $admin = $stmt->fetch() ?: null;
    if (!$admin) {
        unset($_SESSION['admin_id']);
    }
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if ($admin) {
        return $admin;
    }
    redirect('admin/login.php');
}

function cart_line_key(int $productId, ?int $variantId = null): string
{
    return $variantId ? ('v' . $variantId) : ('p' . $productId);
}

function cart_get(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $normalized = [];
    foreach ($_SESSION['cart'] as $key => $qty) {
        $k = (string) $key;
        if (preg_match('/^\d+$/', $k)) {
            $k = 'p' . $k;
        }
        if (!preg_match('/^[pv]\d+$/', $k)) {
            continue;
        }
        $normalized[$k] = (int) $qty;
    }
    $_SESSION['cart'] = $normalized;
    return $normalized;
}

function cart_count(): int
{
    $n = 0;
    foreach (cart_get() as $qty) {
        $n += (int) $qty;
    }
    return $n;
}

function cart_add(int $productId, int $qty = 1, ?int $variantId = null): void
{
    $cart = cart_get();
    $key = cart_line_key($productId, $variantId);
    $cart[$key] = ($cart[$key] ?? 0) + max(1, $qty);
    $_SESSION['cart'] = $cart;
}

function cart_set(int $productId, int $qty, ?int $variantId = null): void
{
    cart_set_line(cart_line_key($productId, $variantId), $qty);
}

function cart_set_line(string $key, int $qty): void
{
    $cart = cart_get();
    if (!preg_match('/^[pv]\d+$/', $key)) {
        return;
    }
    if ($qty <= 0) {
        unset($cart[$key]);
    } else {
        $cart[$key] = $qty;
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove_line(string $key): void
{
    $cart = cart_get();
    unset($cart[$key]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function variant_options_grouped(): array
{
    $grouped = ['size' => [], 'colour' => [], 'material' => []];
    foreach (db()->query('SELECT * FROM variant_options ORDER BY kind ASC, sort_order ASC, label ASC') as $row) {
        $grouped[$row['kind']][] = $row;
    }
    return $grouped;
}

function product_tiers(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_tiers WHERE product_id = ? ORDER BY min_qty ASC');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function product_variant_rows(int $productId): array
{
    $stmt = db()->prepare('SELECT v.*, s.label AS size_label, c.label AS colour_label, m.label AS material_label
        FROM product_variants v
        LEFT JOIN variant_options s ON s.id = v.size_id
        LEFT JOIN variant_options c ON c.id = v.colour_id
        LEFT JOIN variant_options m ON m.id = v.material_id
        WHERE v.product_id = ? ORDER BY v.id ASC');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function variant_label(array $variant): string
{
    $parts = array_filter([
        $variant['size_label'] ?? '',
        $variant['colour_label'] ?? '',
        $variant['material_label'] ?? '',
    ], static fn ($part) => $part !== '' && $part !== null);
    return implode(' / ', $parts);
}

function save_product_offers(int $productId, array $optionGroups): void
{
    $allowed = ['size' => [], 'colour' => [], 'material' => []];
    foreach ($optionGroups as $kind => $rows) {
        foreach ($rows as $row) {
            $allowed[$kind][(int) $row['id']] = true;
        }
    }

    db()->prepare('DELETE FROM product_tiers WHERE product_id = ?')->execute([$productId]);
    $mins = [];
    $tierMin = (array) ($_POST['tier_min'] ?? []);
    $tierType = (array) ($_POST['tier_type'] ?? []);
    $tierValue = (array) ($_POST['tier_value'] ?? []);
    $insTier = db()->prepare('INSERT INTO product_tiers (product_id, min_qty, discount_type, discount_value) VALUES (?,?,?,?)');
    foreach ($tierMin as $i => $minRaw) {
        $min = (int) $minRaw;
        $type = (($tierType[$i] ?? '') === 'fixed') ? 'fixed' : 'percent';
        $value = max(0, (int) ($tierValue[$i] ?? 0));
        if ($min < 2 || $value < 1) {
            continue;
        }
        if ($type === 'percent') {
            $value = min(100, $value);
        }
        if (isset($mins[$min])) {
            throw new RuntimeException('Each “buy at least” quantity can only be used once.');
        }
        $mins[$min] = true;
        $insTier->execute([$productId, $min, $type, $value]);
    }

    db()->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$productId]);
    $sizes = (array) ($_POST['var_size'] ?? []);
    $colours = (array) ($_POST['var_colour'] ?? []);
    $materials = (array) ($_POST['var_material'] ?? []);
    $skus = (array) ($_POST['var_sku'] ?? []);
    $prices = (array) ($_POST['var_price'] ?? []);
    $stocks = (array) ($_POST['var_stock'] ?? []);
    $insVar = db()->prepare('INSERT INTO product_variants (product_id, size_id, colour_id, material_id, sku, price, stock) VALUES (?,?,?,?,?,?,?)');
    $seen = [];
    foreach ($sizes as $i => $sizeRaw) {
        $sizeId = (int) $sizeRaw;
        $colourId = (int) ($colours[$i] ?? 0);
        $materialId = (int) ($materials[$i] ?? 0);
        $sku = trim((string) ($skus[$i] ?? ''));
        $priceRaw = trim((string) ($prices[$i] ?? ''));
        $stock = max(0, (int) ($stocks[$i] ?? 0));
        if ($sizeId < 1 && $colourId < 1 && $materialId < 1 && $sku === '' && $priceRaw === '' && $stock === 0) {
            continue;
        }
        if ($sizeId > 0 && empty($allowed['size'][$sizeId])) {
            throw new RuntimeException('Choose a size from the variation list.');
        }
        if ($colourId > 0 && empty($allowed['colour'][$colourId])) {
            throw new RuntimeException('Choose a colour from the variation list.');
        }
        if ($materialId > 0 && empty($allowed['material'][$materialId])) {
            throw new RuntimeException('Choose a material from the variation list.');
        }
        $combo = $sizeId . ':' . $colourId . ':' . $materialId;
        if (isset($seen[$combo])) {
            throw new RuntimeException('Two combinations use the same size, colour, and material.');
        }
        $seen[$combo] = true;
        $price = $priceRaw === '' ? null : max(0, (int) $priceRaw);
        $insVar->execute([
            $productId,
            $sizeId > 0 ? $sizeId : null,
            $colourId > 0 ? $colourId : null,
            $materialId > 0 ? $materialId : null,
            $sku !== '' ? $sku : null,
            $price,
            $stock,
        ]);
    }
}

function price_after_tiers(int $base, int $qty, array $tiers): int
{
    $match = null;
    foreach ($tiers as $tier) {
        if ($qty >= (int) $tier['min_qty'] && ($match === null || (int) $tier['min_qty'] >= (int) $match['min_qty'])) {
            $match = $tier;
        }
    }
    if ($match === null) {
        return $base;
    }
    if ($match['discount_type'] === 'percent') {
        $pct = max(0, min(100, (int) $match['discount_value']));
        return max(0, (int) round($base * (100 - $pct) / 100));
    }
    return max(0, $base - (int) $match['discount_value']);
}

function cart_lines(): array
{
    $cart = cart_get();
    if (!$cart) {
        return [];
    }
    $productIds = [];
    $variantIds = [];
    foreach ($cart as $key => $qty) {
        if (str_starts_with((string) $key, 'v')) {
            $variantIds[] = (int) substr((string) $key, 1);
        } else {
            $productIds[] = (int) substr((string) $key, 1);
        }
    }
    $variants = [];
    if ($variantIds) {
        $place = implode(',', array_fill(0, count($variantIds), '?'));
        $stmt = db()->prepare("SELECT v.*, s.label AS size_label, c.label AS colour_label, m.label AS material_label
            FROM product_variants v
            LEFT JOIN variant_options s ON s.id = v.size_id
            LEFT JOIN variant_options c ON c.id = v.colour_id
            LEFT JOIN variant_options m ON m.id = v.material_id
            WHERE v.id IN ($place)");
        $stmt->execute($variantIds);
        foreach ($stmt->fetchAll() as $row) {
            $variants[(int) $row['id']] = $row;
            $productIds[] = (int) $row['product_id'];
        }
    }
    $productIds = array_values(array_unique(array_filter($productIds)));
    if (!$productIds) {
        return [];
    }
    $place = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = db()->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN ($place)");
    $stmt->execute($productIds);
    $products = [];
    foreach ($stmt->fetchAll() as $row) {
        $products[(int) $row['id']] = $row;
    }
    $tierStmt = db()->prepare("SELECT * FROM product_tiers WHERE product_id IN ($place) ORDER BY min_qty ASC");
    $tierStmt->execute($productIds);
    $tiersByProduct = [];
    foreach ($tierStmt->fetchAll() as $tier) {
        $tiersByProduct[(int) $tier['product_id']][] = $tier;
    }
    $lines = [];
    foreach ($cart as $key => $qty) {
        $qty = max(1, (int) $qty);
        $variant = null;
        $pid = 0;
        if (str_starts_with((string) $key, 'v')) {
            $vid = (int) substr((string) $key, 1);
            if (!isset($variants[$vid])) {
                continue;
            }
            $variant = $variants[$vid];
            $pid = (int) $variant['product_id'];
        } else {
            $pid = (int) substr((string) $key, 1);
        }
        if (!isset($products[$pid])) {
            continue;
        }
        $p = $products[$pid];
        $base = $variant && $variant['price'] !== null ? (int) $variant['price'] : (int) $p['price'];
        $unit = price_after_tiers($base, $qty, $tiersByProduct[$pid] ?? []);
        $stock = $variant ? (int) $variant['stock'] : (int) $p['stock'];
        $lines[] = [
            'key' => (string) $key,
            'product' => $p,
            'variant' => $variant,
            'variant_label' => $variant ? variant_label($variant) : '',
            'qty' => $qty,
            'stock' => $stock,
            'base_price' => $base,
            'unit_price' => $unit,
            'line_total' => $unit * $qty,
        ];
    }
    return $lines;
}

function cart_subtotal(): int
{
    $sum = 0;
    foreach (cart_lines() as $line) {
        $sum += $line['line_total'];
    }
    return $sum;
}

function shipping_for(int $subtotal): int
{
    return $subtotal >= SHIPPING_FREE_OVER ? 0 : SHIPPING_FLAT;
}

function shipping_quote(int $subtotal, ?int $stateFee, bool $allFreeDelivery): int
{
    if ($allFreeDelivery || $subtotal >= SHIPPING_FREE_OVER) {
        return 0;
    }
    if ($stateFee !== null && $stateFee >= 0) {
        return $stateFee;
    }
    return SHIPPING_FLAT;
}

function sanitize_rich(string $html): string
{
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3>');
    $html = preg_replace('/\s(on\w+|style|class)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html) ?? $html;
    $html = preg_replace_callback('/<a\b[^>]*>/i', static function (array $m): string {
        if (preg_match('/href\s*=\s*("|\')(https?:\/\/[^"\']+)\1/i', $m[0], $h)) {
            return '<a href="' . htmlspecialchars($h[2], ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer" target="_blank">';
        }
        return '<a>';
    }, $html) ?? $html;
    return $html;
}

function rich_html(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    if (!preg_match('/<[a-z][^>]*>/i', $html)) {
        return nl2br(e($html));
    }
    return sanitize_rich($html);
}

function safe_link(string $link): string
{
    $link = trim($link);
    if ($link === '') {
        return 'shop.php';
    }
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if (preg_match('~^[a-z0-9_./?&=%#+\\-]+$~i', $link)) {
        return ltrim($link, '/');
    }
    return 'shop.php';
}

function public_href(string $link): string
{
    $link = safe_link($link);
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }
    return url($link);
}

function wishlist_session(): array
{
    if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }
    return $_SESSION['wishlist'];
}

function wishlist_ids(): array
{
    static $ids = null;
    static $gen = -1;
    $want = (int) ($GLOBALS['__wishlist_gen'] ?? 0);
    if ($ids !== null && $gen === $want) {
        return $ids;
    }
    $gen = $want;
    $user = current_user();
    if ($user) {
        $stmt = db()->prepare('SELECT product_id FROM wishlists WHERE user_id = ?');
        $stmt->execute([(int) $user['id']]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return $ids;
    }
    $ids = array_map('intval', array_keys(wishlist_session()));
    return $ids;
}

function wishlist_count(): int
{
    return count(wishlist_ids());
}

function wishlist_has(int $productId): bool
{
    return in_array($productId, wishlist_ids(), true);
}

function wishlist_toggle(int $productId): bool
{
    $user = current_user();
    if ($user) {
        if (wishlist_has($productId)) {
            db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([(int) $user['id'], $productId]);
            $on = false;
        } else {
            db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([(int) $user['id'], $productId]);
            $on = true;
        }
    } else {
        $bag = wishlist_session();
        if (isset($bag[$productId])) {
            unset($bag[$productId]);
            $on = false;
        } else {
            $bag[$productId] = 1;
            $on = true;
        }
        $_SESSION['wishlist'] = $bag;
    }
    wishlist_ids_reset();
    return $on;
}

function wishlist_ids_reset(): void
{
    $GLOBALS['__wishlist_gen'] = (int) ($GLOBALS['__wishlist_gen'] ?? 0) + 1;
}

function wishlist_merge_user(int $userId): void
{
    foreach (array_keys(wishlist_session()) as $pid) {
        db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$userId, (int) $pid]);
    }
    $_SESSION['wishlist'] = [];
    wishlist_ids_reset();
}

function discount_percent(?int $price, ?int $compare): ?int
{
    if ($price === null || $compare === null || $compare <= $price || $compare <= 0) {
        return null;
    }
    return (int) round((($compare - $price) / $compare) * 100);
}

function product_image_url(?string $image): string
{
    if ($image && is_file(BASE_PATH . '/' . ltrim($image, '/'))) {
        return url(ltrim($image, '/'));
    }
    return url('uploads/products/phone-spark.png');
}

function logo_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    $rel = ltrim($path, '/');
    if (is_file(BASE_PATH . '/' . $rel)) {
        return url($rel);
    }
    return null;
}

function handle_upload(array $file, string $subdir): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }
    if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Image must be 2MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED, true)) {
        throw new RuntimeException('Only JPEG, PNG, or WebP images are allowed.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'bin',
    };

    $dir = BASE_PATH . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}

function order_number(): string
{
    return 'SHP-' . strtoupper(bin2hex(random_bytes(5)));
}

/**
 * Minimal SMTP client via fsockopen. Supports none / tls / ssl + AUTH LOGIN.
 * @return array{ok:bool,message:string}
 */
function smtp_send(array $settings, string $to, string $subject, string $body): array
{
    $host = trim((string) ($settings['smtp_host'] ?? ''));
    $port = (int) ($settings['smtp_port'] ?? 587);
    $enc = strtolower((string) ($settings['smtp_encryption'] ?? 'tls'));
    $user = (string) ($settings['smtp_username'] ?? '');
    $pass = (string) ($settings['smtp_password'] ?? '');
    $fromEmail = trim((string) ($settings['smtp_from_email'] ?? ''));
    $fromName = trim((string) ($settings['smtp_from_name'] ?? 'Shopora'));

    if ($host === '' || $fromEmail === '') {
        return ['ok' => false, 'message' => 'SMTP is not configured. Set host and from email in Admin → SMTP.'];
    }

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $timeout = 20;
    $fp = @fsockopen($remote, $port, $errno, $errstr, $timeout);
    if (!$fp) {
        return ['ok' => false, 'message' => "Could not connect to SMTP server: $errstr ($errno)"];
    }
    stream_set_timeout($fp, $timeout);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 512)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };
    $expect = static function (string $resp, string $code) use (&$result): bool {
        return str_starts_with(trim($resp), $code);
    };

    $banner = $read();
    if (!$expect($banner, '220')) {
        fclose($fp);
        return ['ok' => false, 'message' => 'SMTP greeting failed: ' . trim($banner)];
    }

    $write('EHLO shopora.local');
    $ehlo = $read();
    if (!$expect($ehlo, '250')) {
        $write('HELO shopora.local');
        $ehlo = $read();
    }

    if ($enc === 'tls') {
        $write('STARTTLS');
        $tls = $read();
        if (!$expect($tls, '220')) {
            fclose($fp);
            return ['ok' => false, 'message' => 'STARTTLS failed: ' . trim($tls)];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'message' => 'Could not enable TLS encryption.'];
        }
        $write('EHLO shopora.local');
        $read();
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        $auth = $read();
        if (!$expect($auth, '334')) {
            fclose($fp);
            return ['ok' => false, 'message' => 'AUTH LOGIN not accepted: ' . trim($auth)];
        }
        $write(base64_encode($user));
        $u = $read();
        if (!$expect($u, '334')) {
            fclose($fp);
            return ['ok' => false, 'message' => 'SMTP username rejected.'];
        }
        $write(base64_encode($pass));
        $p = $read();
        if (!$expect($p, '235')) {
            fclose($fp);
            return ['ok' => false, 'message' => 'SMTP authentication failed. Check username/password.'];
        }
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    if (!$expect($read(), '250')) {
        fclose($fp);
        return ['ok' => false, 'message' => 'MAIL FROM rejected.'];
    }
    $write('RCPT TO:<' . $to . '>');
    $rcpt = $read();
    if (!$expect($rcpt, '250') && !$expect($rcpt, '251')) {
        fclose($fp);
        return ['ok' => false, 'message' => 'Recipient rejected: ' . trim($rcpt)];
    }
    $write('DATA');
    if (!$expect($read(), '354')) {
        fclose($fp);
        return ['ok' => false, 'message' => 'DATA command rejected.'];
    }

    $headers = [];
    $headers[] = 'From: ' . ($fromName !== '' ? '"' . addcslashes($fromName, '"') . '" <' . $fromEmail . '>' : $fromEmail);
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Date: ' . date('r');

    $msg = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body) . "\r\n.";
    $write($msg);
    $dataResp = $read();
    $write('QUIT');
    fclose($fp);

    if (!$expect($dataResp, '250')) {
        return ['ok' => false, 'message' => 'Message not accepted: ' . trim($dataResp)];
    }
    return ['ok' => true, 'message' => 'Email sent successfully.'];
}

function status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}

function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
}

function product_gallery(array $product): array
{
    $images = [];
    if (!empty($product['image'])) {
        $images[] = (string) $product['image'];
    }
    $stmt = db()->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([(int) $product['id']]);
    foreach ($stmt->fetchAll() as $row) {
        if (!in_array($row['path'], $images, true)) {
            $images[] = (string) $row['path'];
        }
    }
    return $images;
}

function whatsapp_product_url(array $product): ?string
{
    if (empty($product['whatsapp_checkout'])) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', (string) (get_settings()['whatsapp_number'] ?? '')) ?? '';
    if ($digits === '') {
        return null;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . $host . url('product.php?slug=' . $product['slug']);
    $text = 'Hello, I want to order ' . $product['name'] . ' — ' . money_ngn((int) $product['price']) . ' ' . $link;
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function handle_uploads(array $files, string $subdir): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }
    $saved = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $file = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $path = handle_upload($file, $subdir);
        if ($path) {
            $saved[] = $path;
        }
    }
    return $saved;
}

function render_product_card(array $p): string
{
    $pct = discount_percent((int) $p['price'], isset($p['compare_price']) ? (int) $p['compare_price'] : null);
    $img = product_image_url($p['image'] ?? null);
    $wished = wishlist_has((int) $p['id']);
    $html = '<article class="product-card">';
    $html .= '<div class="product-card__media">';
    $html .= '<a href="' . e(url('product.php?slug=' . $p['slug'])) . '">';
    if ($pct !== null) {
        $html .= '<span class="badge-deal">-' . $pct . '%</span>';
    }
    $html .= '<img src="' . e($img) . '" alt="' . e($p['name']) . '" loading="lazy">';
    $html .= '</a>';
    $html .= '<button type="button" class="wish-btn' . ($wished ? ' is-on' : '') . '" data-wish="' . (int) $p['id'] . '" aria-pressed="' . ($wished ? 'true' : 'false') . '" aria-label="Save to wishlist">♥</button>';
    $html .= '</div>';
    $html .= '<div class="product-card__body">';
    if (!empty($p['category_name'])) {
        $html .= '<p class="product-card__cat">' . e($p['category_name']) . '</p>';
    }
    $html .= '<h3 class="product-card__name"><a href="' . e(url('product.php?slug=' . $p['slug'])) . '">' . e($p['name']) . '</a></h3>';
    $html .= '<p class="product-card__rating"><span class="stars">★</span> ' . e(number_format((float) $p['rating'], 1)) . ' <span class="muted">(' . (int) $p['review_count'] . ')</span></p>';
    $html .= '<p class="product-card__price"><span class="price">' . e(money_ngn((int) $p['price'])) . '</span>';
    if (!empty($p['compare_price']) && (int) $p['compare_price'] > (int) $p['price']) {
        $html .= ' <span class="price-old">' . e(money_ngn((int) $p['compare_price'])) . '</span>';
    }
    $html .= '</p>';
    $html .= '<form method="post" action="' . e(url('cart-add.php')) . '" class="product-card__cart">';
    $html .= csrf_field();
    $html .= '<input type="hidden" name="product_id" value="' . (int) $p['id'] . '">';
    $html .= '<input type="hidden" name="qty" value="1">';
    $html .= '<input type="hidden" name="return" value="' . e($_SERVER['REQUEST_URI'] ?? url('')) . '">';
    $html .= '<button type="submit" class="btn btn-brand btn-sm">Add to cart</button>';
    $html .= '</form></div></article>';
    return $html;
}
