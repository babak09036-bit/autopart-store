<?php /** @var int $status @var string $message @var array $popular @var array $categories */ ?>
<main id="main" class="container">
  <section class="error-page">
    <img src="assets/images/ui/no-results.svg" alt="" width="320" height="240">
    <p class="error-page__code"><?= e(fa_digits($status)) ?></p>
    <h1><?= $status === 404 ? 'صفحه‌ای که دنبالش بودید پیدا نشد' : 'مشکلی پیش آمد' ?></h1>
    <p><?= e($message) ?></p>
    <?php if ($status === 404): ?>
    <form class="search-head error-page__search" action="search" role="search">
      <label class="visually-hidden" for="nf-q">جستجوی محصولات</label>
      <input class="input" id="nf-q" name="q" type="search" placeholder="جستجو در محصولات…">
      <button type="submit" class="btn btn--primary"><?= icon('search', 'icon--sm') ?>جستجو</button>
    </form>
    <?php if ($categories): ?>
    <div class="sub-chips error-page__cats"><?php foreach ($categories as $c): ?><a class="chip" href="categories/<?= e($c['slug']) ?>"><?= e($c['short_name'] ?: $c['name']) ?></a><?php endforeach; ?></div>
    <?php endif; ?>
    <?php endif; ?>
    <a class="btn btn--dark" href=""><?= icon('home', 'icon--sm') ?>بازگشت به صفحه اصلی</a>
  </section>
  <?php if ($popular): ?>
  <section class="section" aria-labelledby="popular-title">
    <div class="section-head"><h2 id="popular-title" class="section-head__title">محصولات محبوب</h2></div>
    <div class="grid-products"><?php foreach ($popular as $p) { require __DIR__ . '/partials/product-card.php'; } ?></div>
  </section>
  <?php endif; ?>
</main>
