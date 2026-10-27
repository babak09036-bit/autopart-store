<?php
/** @var string $title @var ?string $intro @var ?array $featured @var array $list @var array $sections @var array $sidebar @var ?array $banner */
$crumbs = [['name' => $title, 'url' => null]];
$basePath = 'blog';
?>
<main id="main" class="container">
  <?php require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
  <header class="blog-hero">
    <div>
      <h1><?= e($title) ?></h1>
      <?php if ($intro): ?><p><?= e($intro) ?></p><?php endif; ?>
    </div>
    <form class="blog-search" action="blog/search" role="search">
      <label class="visually-hidden" for="blog-q">جستجو در مقالات</label>
      <input class="input" id="blog-q" name="q" type="search" placeholder="جستجو در مقالات…">
      <button type="submit" class="btn btn--primary" aria-label="جستجو"><?= icon('search', 'icon--sm') ?></button>
    </form>
  </header>

  <nav class="sub-chips blog-cats" aria-label="دسته‌بندی‌های مجله">
    <?php foreach ($sidebar['categories'] as $c): ?><a class="chip" href="blog/category/<?= e($c['slug']) ?>"><?= e($c['name']) ?> <small>(<?= e(fa_digits($c['n'])) ?>)</small></a><?php endforeach; ?>
  </nav>

  <?php if ($featured): ?>
  <article class="post-featured">
    <a class="post-featured__media" href="<?= e($featured['url']) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($featured['image']) ?>" alt="<?= e($featured['image_alt']) ?>" width="640" height="360"></a>
    <div class="post-featured__body">
      <span class="badge badge--red">مقاله ویژه</span>
      <h2><a href="<?= e($featured['url']) ?>"><?= e($featured['title']) ?></a></h2>
      <?php if ($featured['subtitle']): ?><p class="post-featured__sub"><?= e($featured['subtitle']) ?></p><?php endif; ?>
      <?php if ($featured['excerpt']): ?><p><?= e($featured['excerpt']) ?></p><?php endif; ?>
      <div class="post-card__meta"><?php if ($featured['author']): ?><a href="<?= e($featured['author']['url']) ?>"><?= e($featured['author']['name']) ?></a><?php endif; ?><span><?= e($featured['published_at_fa']) ?></span></div>
      <a class="btn btn--primary btn--sm" href="<?= e($featured['url']) ?>">خواندن مقاله</a>
    </div>
  </article>
  <?php endif; ?>

  <div class="blog-layout">
    <div>
      <section aria-labelledby="latest-title">
        <div class="section-head"><h2 id="latest-title" class="section-head__title">جدیدترین مقالات</h2></div>
        <div class="post-grid"><?php foreach ($list['items'] as $post) { require dirname(__DIR__) . '/partials/blog-card.php'; } ?></div>
        <?php $pagination = $list['pagination']; require dirname(__DIR__) . '/partials/pagination.php'; ?>
      </section>
      <?php foreach ($sections as $s): ?>
      <section class="section" aria-labelledby="sec-<?= e($s['slug']) ?>">
        <div class="section-head"><h2 id="sec-<?= e($s['slug']) ?>" class="section-head__title"><?= e($s['name']) ?></h2><a class="link-more" href="blog/category/<?= e($s['slug']) ?>">همه مقالات<?= icon('chevron-left') ?></a></div>
        <div class="post-grid post-grid--3"><?php foreach ($s['posts'] as $post) { require dirname(__DIR__) . '/partials/blog-card.php'; } ?></div>
      </section>
      <?php endforeach; ?>
    </div>
    <?php require __DIR__ . '/sidebar.php'; ?>
  </div>
</main>
