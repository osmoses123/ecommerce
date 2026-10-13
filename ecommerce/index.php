<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$cats = get_categories();
$flashDeals = db()->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_flash = 1 AND p.status = 'active' ORDER BY p.id ASC LIMIT 12")->fetchAll();
$featured = db()->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_featured = 1 AND p.status = 'active' ORDER BY p.id ASC LIMIT 8")->fetchAll();
$more = db()->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_featured = 0 AND p.status = 'active' ORDER BY CASE WHEN p.compare_price IS NOT NULL AND p.compare_price > p.price THEN (p.compare_price - p.price) / p.compare_price ELSE 0 END DESC, p.price ASC LIMIT 8")->fetchAll();
$heroes = db()->query("SELECT * FROM banners WHERE slot = 'hero' AND is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
$sides = db()->query("SELECT * FROM banners WHERE slot = 'side' AND is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
$columns = db()->query('SELECT * FROM home_columns ORDER BY position ASC')->fetchAll();

$pageTitle = get_settings()['site_title'] . ' — Shop Nigeria';
require __DIR__ . '/includes/header.php';
?>
<div class="page-pad">
  <div class="container">
    <section class="hero-market<?= $sides ? '' : ' hero-market--noside' ?>" aria-label="Marketplace hero">
      <nav class="hero-cats" aria-label="Shop by category">
        <?php foreach ($cats as $cat): ?>
          <a href="<?= e(url('shop.php?category=' . $cat['slug'])) ?>">
            <span class="icon"><?= e($cat['icon_label']) ?></span>
            <?= e($cat['name']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="hero-slider" data-slider>
        <?php if (!$heroes): ?>
          <a class="hero-slide is-active" href="<?= e(url('shop.php')) ?>">
            <div class="hero-promo__caption">
              <h1><?= e(get_settings()['site_title']) ?></h1>
              <p><?= e(get_settings()['site_description']) ?></p>
            </div>
          </a>
        <?php else: ?>
          <?php foreach ($heroes as $i => $slide): ?>
            <a class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" href="<?= e(public_href($slide['link_url'])) ?>">
              <img src="<?= e(product_image_url($slide['image'])) ?>" alt="<?= e($slide['title'] !== '' ? $slide['title'] : 'Promo') ?>">
              <?php if ($slide['title'] !== '' || $slide['subtitle'] !== '' || $slide['button_label'] !== ''): ?>
              <div class="hero-promo__caption">
                <?php if ($slide['title'] !== ''): ?><h1><?= e($slide['title']) ?></h1><?php endif; ?>
                <?php if ($slide['subtitle'] !== ''): ?><p><?= e($slide['subtitle']) ?></p><?php endif; ?>
                <?php if ($slide['button_label'] !== ''): ?><span class="btn btn-brand btn-sm"><?= e($slide['button_label']) ?></span><?php endif; ?>
              </div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
          <?php if (count($heroes) > 1): ?>
            <button type="button" class="hero-slider__nav hero-slider__prev" aria-label="Previous slide">‹</button>
            <button type="button" class="hero-slider__nav hero-slider__next" aria-label="Next slide">›</button>
            <div class="hero-slider__dots">
              <?php foreach ($heroes as $i => $slide): ?>
                <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($sides): ?>
      <div class="hero-side">
        <?php foreach ($sides as $tile): ?>
          <a class="hero-tile" href="<?= e(public_href($tile['link_url'])) ?>">
            <img src="<?= e(product_image_url($tile['image'])) ?>" alt="<?= e($tile['title'] !== '' ? $tile['title'] : 'Offer') ?>">
            <?php if ($tile['title'] !== ''): ?><span><?= e($tile['title']) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <?php if ($flashDeals): ?>
    <div class="section-head">
      <h2>Flash deals</h2>
      <div class="countdown">Ends in <span id="flash-countdown"></span></div>
    </div>
    <div class="flash-rail">
      <?php foreach ($flashDeals as $p): ?>
        <?= render_product_card($p) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section-head">
      <h2>Shop categories</h2>
      <a href="<?= e(url('shop.php')) ?>">See all</a>
    </div>
    <div class="cat-circles">
      <?php foreach ($cats as $cat): ?>
        <a class="cat-circle" href="<?= e(url('shop.php?category=' . $cat['slug'])) ?>">
          <span class="cat-circle__icon"><?= e($cat['icon_label']) ?></span>
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="section-head">
      <h2>Top picks</h2>
      <a href="<?= e(url('shop.php')) ?>">Browse shop</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?>
        <?= render_product_card($p) ?>
      <?php endforeach; ?>
    </div>

    <?php if ($columns): ?>
    <section class="home-split" aria-label="Featured aisles">
      <?php foreach ($columns as $col): ?>
        <a class="home-split__card" href="<?= e(public_href($col['link_url'])) ?>">
          <?php if (!empty($col['image'])): ?>
            <img src="<?= e(product_image_url($col['image'])) ?>" alt="">
          <?php endif; ?>
          <?php if ($col['title'] !== '' || $col['body'] !== '' || $col['button_label'] !== ''): ?>
          <span class="home-split__copy">
            <?php if ($col['title'] !== ''): ?><strong><?= e($col['title']) ?></strong><?php endif; ?>
            <?php if ($col['body'] !== ''): ?><em><?= e($col['body']) ?></em><?php endif; ?>
            <?php if ($col['button_label'] !== ''): ?><b><?= e($col['button_label']) ?></b><?php endif; ?>
          </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <div class="section-head">
      <h2>More to love</h2>
    </div>
    <div class="product-grid">
      <?php foreach ($more as $p): ?>
        <?= render_product_card($p) ?>
      <?php endforeach; ?>
    </div>

    <div class="trust-row">
      <div class="trust-item"><strong>Pay on delivery</strong><span>Cash when your parcel arrives</span></div>
      <div class="trust-item"><strong>Easy returns</strong><span>7-day return on eligible items</span></div>
      <div class="trust-item"><strong>Official warranty</strong><span>Covered electronics &amp; appliances</span></div>
      <div class="trust-item"><strong>Fast Lagos dispatch</strong><span>Same-week delivery in major cities</span></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
