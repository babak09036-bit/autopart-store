<?php
/**
 * Storefront layout.
 * @var array $seo    from SeoService::meta()
 * @var string $page  data-page value used by the JS
 * @var string $content rendered page body
 * @var array $scripts page-specific scripts (relative to js/)
 * @var array $boot   data passed to the JS (no secrets)
 */
$settings = \App\Services\SettingsService::all(true);
$v = '?v=11';
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= e(url('')) ?>">
  <title><?= e($seo['title']) ?></title>
<?php if ($seo['description'] !== ''): ?>
  <meta name="description" content="<?= e($seo['description']) ?>">
<?php endif; ?>
  <meta name="robots" content="<?= e($seo['robots']) ?>">
  <link rel="canonical" href="<?= e($seo['canonical']) ?>">
  <meta property="og:type" content="<?= e($seo['og']['type']) ?>">
  <meta property="og:site_name" content="<?= e($seo['og']['site_name']) ?>">
  <meta property="og:locale" content="fa_IR">
  <meta property="og:title" content="<?= e($seo['og']['title']) ?>">
<?php if ($seo['og']['description'] !== ''): ?>
  <meta property="og:description" content="<?= e($seo['og']['description']) ?>">
<?php endif; ?>
  <meta property="og:url" content="<?= e($seo['canonical']) ?>">
<?php if ($seo['og']['image']): ?>
  <meta property="og:image" content="<?= e($seo['og']['image']) ?>">
<?php endif; ?>
  <meta name="twitter:card" content="<?= e($seo['twitter']['card']) ?>">
  <meta name="twitter:title" content="<?= e($seo['twitter']['title']) ?>">
<?php if ($seo['twitter']['description'] !== ''): ?>
  <meta name="twitter:description" content="<?= e($seo['twitter']['description']) ?>">
<?php endif; ?>
<?php if ($seo['twitter']['image']): ?>
  <meta name="twitter:image" content="<?= e($seo['twitter']['image']) ?>">
<?php endif; ?>
  <meta name="theme-color" content="#D71920">
  <link rel="icon" href="<?= e($settings['favicon'] ?? 'assets/images/ui/favicon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="css/style.css<?= $v ?>">
  <link rel="stylesheet" href="css/components.css<?= $v ?>">
  <link rel="stylesheet" href="css/pages.css<?= $v ?>">
  <link rel="stylesheet" href="css/store-backend.css<?= $v ?>">
  <link rel="stylesheet" href="css/responsive.css<?= $v ?>">
<?php foreach ($seo['jsonld'] as $ld): ?>
  <script type="application/ld+json"><?= json_out($ld) ?></script>
<?php endforeach; ?>
  <script>window.AP_BOOT = <?= json_out($boot) ?>;</script>
  <script src="js/utils.js<?= $v ?>" defer></script>
  <script src="js/api.js<?= $v ?>" defer></script>
  <script src="js/cart.js<?= $v ?>" defer></script>
  <script src="js/favorites.js<?= $v ?>" defer></script>
  <script src="js/search.js<?= $v ?>" defer></script>
  <script src="js/sliders.js<?= $v ?>" defer></script>
  <script src="js/main.js<?= $v ?>" defer></script>
<?php foreach ($scripts as $script): ?>
  <script src="js/<?= e($script) ?><?= $v ?>" defer></script>
<?php endforeach; ?>
</head>
<body data-page="<?= e($page) ?>">
  <a class="skip-link" href="<?= e((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?>#main">پرش به محتوای اصلی</a>
  <header id="site-header" class="site-header"></header>
  <?= $content ?>
  <footer id="site-footer" class="site-footer"></footer>
</body>
</html>
