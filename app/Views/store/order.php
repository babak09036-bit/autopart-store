<main id="main" class="container">
  <?php $crumbs = [['name' => 'سفارش ' . $number, 'url' => null]]; require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <h1 class="page-title">سفارش <span dir="ltr"><?= e($number) ?></span></h1>
  <div id="order-root" data-number="<?= e($number) ?>" aria-live="polite"><p class="field__hint">در حال بارگذاری…</p></div>
</main>
