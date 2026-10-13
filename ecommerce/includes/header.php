<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$settings = get_settings();
$user = current_user();
$categories = [];
try {
    $categories = get_categories();
} catch (Throwable $e) {
    $categories = [];
}
$pageTitle = $pageTitle ?? $settings['site_title'];
$metaDescription = $metaDescription ?? $settings['site_description'];
$logoSrc = logo_url($settings['logo_path'] ?? null);
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/store.css')) ?>">
    <style>:root { --brand: <?= e($settings['primary_color'] ?: '#F05A1A') ?>; }</style>
</head>
<body data-wish-url="<?= e(url('api/wishlist.php')) ?>" data-cart-url="<?= e(url('cart-add.php')) ?>" data-cart-summary="<?= e(url('api/cart.php')) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="topbar">
        <div class="container topbar__inner">
            <?php if (trim((string) ($settings['topbar_city'] ?? '')) !== ''): ?>
                <span><?= e($settings['topbar_city']) ?></span>
                <span class="topbar__sep">·</span>
            <?php endif; ?>
            <?php if (trim((string) ($settings['topbar_help'] ?? '')) !== ''): ?>
                <span><?= e($settings['topbar_help']) ?></span>
                <span class="topbar__sep">·</span>
            <?php endif; ?>
            <a href="<?= e(url('track.php')) ?>">Track order</a>
            <?php if (trim((string) ($settings['topbar_extra'] ?? '')) !== ''): ?>
                <span class="topbar__sep">·</span>
                <span><?= e($settings['topbar_extra']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="mainbar">
        <div class="container mainbar__inner">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <?php if ($logoSrc): ?>
                    <img src="<?= e($logoSrc) ?>" alt="<?= e($settings['site_title']) ?>" class="brand__logo">
                <?php else: ?>
                    <span class="brand__word"><?= e($settings['site_title']) ?></span>
                <?php endif; ?>
            </a>
            <div class="search-wrap">
                <form class="search-capsule" action="<?= e(url('shop.php')) ?>" method="get" role="search">
                    <label class="sr-only" for="q">Search products</label>
                    <input id="q" type="search" name="q" placeholder="Search phones, fashion, appliances…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off" data-search-url="<?= e(url('api/search.php')) ?>">
                    <button type="submit" aria-label="Search">Search</button>
                </form>
                <div class="search-suggest" id="search-suggest" hidden></div>
            </div>
            <nav class="mainbar__actions">
                <?php if ($user): ?>
                    <a class="action-link" href="<?= e(url('account.php')) ?>">
                        <span class="action-link__label">Account</span>
                        <strong><?= e(explode(' ', $user['name'])[0]) ?></strong>
                    </a>
                <?php else: ?>
                    <a class="action-link" href="<?= e(url('login.php')) ?>">
                        <span class="action-link__label">Account</span>
                        <strong>Sign in</strong>
                    </a>
                <?php endif; ?>
                <a class="action-link" href="<?= e(url('wishlist.php')) ?>">
                    <span class="action-link__label">Saved</span>
                    <strong>Wishlist</strong>
                    <?php if (wishlist_count() > 0): ?>
                        <span class="cart-badge" id="wish-count"><?= wishlist_count() ?></span>
                    <?php else: ?>
                        <span class="cart-badge" id="wish-count" hidden>0</span>
                    <?php endif; ?>
                </a>
                <div class="cart-flyout" id="cart-flyout">
                    <a class="action-link cart-link" href="<?= e(url('cart.php')) ?>">
                        <span class="action-link__label">Cart</span>
                        <strong>Basket</strong>
                        <span class="cart-badge" id="cart-count" <?= cart_count() > 0 ? '' : 'hidden' ?>><?= cart_count() ?></span>
                    </a>
                    <div class="cart-panel" id="cart-panel"></div>
                </div>
            </nav>
        </div>
    </div>
    <?php if ($categories): ?>
    <nav class="cat-strip" aria-label="Categories">
        <div class="container cat-strip__inner">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= e(url('shop.php?category=' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>
</header>
<?php if ($flash): ?>
<div class="flash flash--<?= e($flash['type']) ?>">
    <div class="container"><?= e($flash['message']) ?></div>
</div>
<?php endif; ?>
<main id="main">
