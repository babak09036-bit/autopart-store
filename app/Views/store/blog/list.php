<?php
/** Blog category / tag / author / search listing. */
$crumbs = [['name' => \App\Services\SettingsService::get('blog_title', 'وبلاگ'), 'url' => 'blog'], ['name' => $title, 'url' => null]];
?>
<main id="main" class="container">
  <?php require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
  <header class="blog-hero<?= !empty($author) ? ' blog-hero--author' : '' ?>">
    <?php if (!empty($author) && $author['avatar_path']): ?><img class="blog-hero__avatar" src="<?= e($author['avatar_path']) ?>" alt="" width="96" height="96"><?php endif; ?>
    <div>
      <h1><?= e($title) ?></h1>
      <?php if (!empty($author['expertise'])): ?><p class="blog-hero__role"><?= e($author['expertise']) ?></p><?php endif; ?>
      <?php if ($description): ?><p><?= e($description) ?></p><?php endif; ?>
    </div>
    <form class="blog-search" action="blog/search" role="search">
      <label class="visually-hidden" for="blog-q">جستجو در مقالات</label>
      <input class="input" id="blog-q" name="q" type="search" value="<?= e($q ?? '') ?>" placeholder="جستجو در مقالات…">
      <button type="submit" class="btn btn--primary" aria-label="جستجو"><?= icon('search', 'icon--sm') ?></button>
    </form>
  </header>
  <div class="blog-layout">
    <div>
      <?php if ($list['items']): ?>
      <div class="post-grid"><?php foreach ($list['items'] as $post) { require dirname(__DIR__) . '/partials/blog-card.php'; } ?></div>
      <?php $pagination = $list['pagination']; require dirname(__DIR__) . '/partials/pagination.php'; ?>
      <?php else: ?>
      <div class="empty-state empty-state--panel">
        <img src="assets/images/ui/no-results.svg" alt="" width="320" height="240">
        <h2>مقاله‌ای پیدا نشد</h2>
        <p>عبارت دیگری را جستجو کنید یا دسته‌بندی‌های مجله را ببینید.</p>
        <a class="btn btn--primary" href="blog">صفحه اصلی مجله</a>
      </div>
      <?php endif; ?>
    </div>
    <?php require __DIR__ . '/sidebar.php'; ?>
  </div>
</main>
