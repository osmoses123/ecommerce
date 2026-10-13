<?php
declare(strict_types=1);
$settings = $settings ?? get_settings();
$categories = $categories ?? [];
$logoSrc = logo_url($settings['logo_path'] ?? null);
$footerAbout = trim((string) ($settings['footer_about'] ?? $settings['site_description'] ?? ''));
$helpLines = footer_help_lines($settings['footer_help'] ?? null);
$copy = trim((string) ($settings['footer_copyright'] ?? ''));
?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <?php if ($logoSrc): ?>
                <img src="<?= e($logoSrc) ?>" alt="<?= e($settings['site_title']) ?>" class="footer-logo">
            <?php else: ?>
                <strong class="footer-word"><?= e($settings['site_title']) ?></strong>
            <?php endif; ?>
            <?php if ($footerAbout !== ''): ?>
                <p><?= e($footerAbout) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h3><?= e($settings['footer_shop_heading'] ?? 'Shop') ?></h3>
            <ul>
                <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                    <li><a href="<?= e(url('shop.php?category=' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h3><?= e($settings['footer_account_heading'] ?? 'Account') ?></h3>
            <ul>
                <li><a href="<?= e(url('account.php')) ?>">My account</a></li>
                <li><a href="<?= e(url('account.php')) ?>">Orders</a></li>
                <li><a href="<?= e(url('track.php')) ?>">Track order</a></li>
                <li><a href="<?= e(url('login.php')) ?>">Sign in</a></li>
            </ul>
        </div>
        <div>
            <h3><?= e($settings['footer_help_heading'] ?? 'Help') ?></h3>
            <ul>
                <?php foreach ($helpLines as $line): ?>
                    <li><?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">&copy; <?= date('Y') ?><?= $copy !== '' ? ' ' . e($copy) : ' ' . e($settings['site_title']) ?></div>
    </div>
</footer>
<script src="<?= e(url('assets/js/store.js')) ?>"></script>
</body>
</html>
