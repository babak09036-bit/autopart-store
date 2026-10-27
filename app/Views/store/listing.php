<?php
/** @var string $mode products|category|brand|search  @var string $title  @var array $items  @var array $crumbs */
$category = $category ?? null;
$brand = $brand ?? null;
?>
<main id="main" class="container">
  <?php require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <h1 class="page-title" id="listing-title"><?= e($title) ?></h1>

  <?php if ($mode === 'category' && $category): ?>
  <div class="cat-hero">
    <div>
      <p class="badge badge--soft"><?= e(fa_number($total ?? count($items))) ?> کالا در این دسته</p>
      <?php if ($category['description']): ?><p><?= e($category['description']) ?></p><?php endif; ?>
      <?php if (!empty($children)): ?>
      <div class="sub-chips" role="list" aria-label="زیردسته‌ها">
        <?php foreach ($children as $child): ?><a role="listitem" class="chip" href="categories/<?= e($child['slug']) ?>"><?= e($child['short_name'] ?: $child['name']) ?></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="sub-chips" id="type-chips" role="group" aria-label="نوع کالا"></div>
    </div>
    <?php if ($category['image_path']): ?><img src="<?= e($category['image_path']) ?>" alt="" width="240" height="240"><?php endif; ?>
  </div>
  <?php elseif ($mode === 'brand' && $brand): ?>
  <div class="cat-hero">
    <div><?php if ($brand['description']): ?><p><?= e($brand['description']) ?></p><?php endif; ?></div>
    <?php if ($brand['logo_path']): ?><img class="cat-hero__logo" src="<?= e($brand['logo_path']) ?>" alt="لوگوی <?= e($brand['name']) ?>" width="240" height="120"><?php endif; ?>
  </div>
  <?php elseif ($mode === 'search'): ?>
  <form class="search-head" id="search-page-form" role="search" action="search">
    <label class="visually-hidden" for="search-page-q">عبارت جستجو</label>
    <input class="input" id="search-page-q" name="q" type="search" value="<?= e($q ?? '') ?>" placeholder="مثلاً: روغن موتور، دوربین، واکس" autocomplete="off">
    <button type="submit" class="btn btn--primary"><?= icon('search', 'icon--sm') ?>جستجو</button>
  </form>
  <?php endif; ?>

  <div class="listing">
    <aside class="filters" id="filters" aria-label="فیلترهای محصولات">
      <div class="filters__head">
        <h2><?= icon('filter', 'icon--sm') ?>فیلترها</h2>
        <button type="button" class="filters__reset" data-remove="all">حذف فیلترها</button>
        <button type="button" class="icon-btn filters__close" data-filters-close aria-label="بستن فیلترها"><?= icon('close') ?></button>
      </div>
      <form id="filter-form" novalidate></form>
    </aside>

    <section class="listing__main" aria-label="نتایج">
      <div class="listing__bar">
        <button type="button" class="btn btn--light btn--sm listing__filter-btn" data-filters-open aria-controls="filters" aria-expanded="false"><?= icon('filter', 'icon--sm') ?>فیلترها</button>
        <span class="listing__bar-label"><?= icon('sort', 'icon--sm') ?>مرتب‌سازی:</span>
        <div class="sort-options" id="sort-options" role="group" aria-label="مرتب‌سازی"></div>
        <span class="listing__count" id="result-count" aria-live="polite"></span>
      </div>
      <div class="active-filters" id="active-filters"></div>
      <div class="grid-products" id="results">
        <?php foreach ($items as $p) { require __DIR__ . '/partials/product-card.php'; } ?>
      </div>
      <div class="empty-state empty-state--panel" id="empty" hidden></div>
      <nav class="pager" id="pager" aria-label="صفحه‌بندی" hidden></nav>
    </section>
  </div>
</main>
