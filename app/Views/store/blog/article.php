<?php
/** @var array $post @var array $a BlogService::article() @var string $path @var array $crumbs @var bool $preview @var bool $commentsEnabled */
$pageUrl = url($path);
$share = rawurlencode(absolute_url($path));
$shareTitle = rawurlencode($post['title']);
$bc = array_slice($crumbs, 1);
$bc[count($bc) - 1]['url'] = null;
?>
<main id="main" class="container">
  <?php if ($preview): ?><div class="alert alert--error preview-bar"><?= icon('eye', 'icon--sm') ?>پیش‌نمایش مدیر — وضعیت: <?= e($post['status']) ?>. این صفحه برای دیگران قابل مشاهده نیست.</div><?php endif; ?>
  <?php $crumbs = $bc; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
  <div class="blog-layout blog-layout--article">
    <article class="article">
      <header class="article__head">
        <?php if ($a['categories']): ?><a class="post-card__cat" href="blog/category/<?= e($a['categories'][0]['slug']) ?>"><?= e($a['categories'][0]['name']) ?></a><?php endif; ?>
        <h1><?= e($post['title']) ?></h1>
        <?php if ($post['subtitle']): ?><p class="article__sub"><?= e($post['subtitle']) ?></p><?php endif; ?>
        <div class="article__meta">
          <?php if ($post['author_name']): ?><a href="blog/author/<?= e($post['author_slug']) ?>"><?= icon('user', 'icon--sm') ?><?= e($post['author_name']) ?></a><?php endif; ?>
          <span>انتشار: <time datetime="<?= e(gmdate('c', (int) strtotime((string) $post['published_at']))) ?>"><?= e(fa_date($post['published_at'])) ?></time></span>
          <?php if ($post['updated_at'] && strtotime((string) $post['updated_at']) > strtotime((string) $post['published_at']) + 86400): ?>
          <span>به‌روزرسانی: <time datetime="<?= e(gmdate('c', (int) strtotime((string) $post['updated_at']))) ?>"><?= e(fa_date($post['updated_at'])) ?></time></span>
          <?php endif; ?>
          <span><?= icon('clock', 'icon--sm') ?><?= e(fa_digits($post['reading_minutes'])) ?> دقیقه مطالعه</span>
        </div>
      </header>
      <?php if ($post['featured_image']): ?>
      <figure class="article__figure">
        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['featured_image_alt'] ?: $post['title']) ?>" width="1200" height="675" fetchpriority="high">
        <?php if ($post['featured_image_caption']): ?><figcaption><?= e($post['featured_image_caption']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endif; ?>

      <?php if (count($a['toc']) >= 2): ?>
      <nav class="toc card-surface" aria-labelledby="toc-title">
        <h2 id="toc-title">فهرست مطالب</h2>
        <ol>
          <?php foreach ($a['toc'] as $t): ?><li class="toc__l<?= (int) $t['level'] ?>"><a href="<?= e($pageUrl) ?>#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?>
        </ol>
      </nav>
      <?php endif; ?>

      <div class="rich-text article__body"><?= $a['html'] ?></div>

      <?php if ($a['tags']): ?>
      <div class="sub-chips article__tags" aria-label="برچسب‌ها"><?php foreach ($a['tags'] as $t): ?><a class="chip" href="blog/tag/<?= e($t['slug']) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?></div>
      <?php endif; ?>

      <div class="share" aria-label="اشتراک‌گذاری">
        <span>اشتراک‌گذاری:</span>
        <a class="social" href="https://t.me/share/url?url=<?= $share ?>&amp;text=<?= $shareTitle ?>" target="_blank" rel="noopener noreferrer" aria-label="اشتراک در تلگرام"><?= icon('telegram') ?></a>
        <a class="social" href="https://wa.me/?text=<?= $shareTitle ?>%20<?= $share ?>" target="_blank" rel="noopener noreferrer" aria-label="اشتراک در واتس‌اپ"><?= icon('whatsapp') ?></a>
        <a class="social" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share ?>" target="_blank" rel="noopener noreferrer" aria-label="اشتراک در لینکدین"><?= icon('linkedin') ?></a>
        <a class="social" href="https://twitter.com/intent/tweet?url=<?= $share ?>&amp;text=<?= $shareTitle ?>" target="_blank" rel="noopener noreferrer" aria-label="اشتراک در ایکس"><?= icon('x-social') ?></a>
        <button type="button" class="social" data-copy="<?= e(absolute_url($path)) ?>" aria-label="کپی پیوند مقاله"><?= icon('check') ?></button>
      </div>

      <?php if ($post['author_name']): ?>
      <section class="author-box card-surface" aria-label="درباره نویسنده">
        <?php if ($post['author_avatar']): ?><img src="<?= e($post['author_avatar']) ?>" alt="" width="72" height="72"><?php endif; ?>
        <div>
          <h2><a href="blog/author/<?= e($post['author_slug']) ?>"><?= e($post['author_name']) ?></a></h2>
          <?php if ($post['author_expertise']): ?><p class="blog-hero__role"><?= e($post['author_expertise']) ?></p><?php endif; ?>
          <?php if ($post['author_bio']): ?><p><?= e($post['author_bio']) ?></p><?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($a['products']): ?>
      <section class="section" aria-labelledby="art-products">
        <div class="section-head"><h2 id="art-products" class="section-head__title">محصولات مرتبط با این مقاله</h2></div>
        <div class="grid-products grid-products--4"><?php foreach ($a['products'] as $p) { require dirname(__DIR__) . '/partials/product-card.php'; } ?></div>
      </section>
      <?php endif; ?>

      <?php if ($a['related']): ?>
      <section class="section" aria-labelledby="art-related">
        <div class="section-head"><h2 id="art-related" class="section-head__title">مقالات مرتبط</h2></div>
        <div class="post-grid post-grid--3"><?php foreach ($a['related'] as $p2) { $post_ = $post; $post = $p2; require dirname(__DIR__) . '/partials/blog-card.php'; $post = $post_; } ?></div>
      </section>
      <?php endif; ?>

      <?php if ($commentsEnabled): ?>
      <section class="comments card-surface" id="comments" aria-labelledby="comments-title">
        <h2 id="comments-title">دیدگاه‌ها (<?= e(fa_digits(count($a['comments']))) ?>)</h2>
        <?php foreach ($a['comments'] as $c): ?>
        <div class="comment<?= $c['is_official'] ? ' comment--official' : '' ?>">
          <div class="comment__head"><strong><?= e($c['author']) ?></strong><span><?= e($c['at']) ?></span></div>
          <p><?= nl2br(e($c['body'])) ?></p>
          <div class="comment__actions"><button type="button" class="link-btn" data-reply="<?= (int) $c['id'] ?>">پاسخ</button><button type="button" class="link-btn" data-report="<?= (int) $c['id'] ?>">گزارش</button></div>
          <?php foreach ($c['replies'] as $r): ?>
          <div class="comment comment--reply<?= $r['is_official'] ? ' comment--official' : '' ?>"><div class="comment__head"><strong><?= e($r['author']) ?></strong><span><?= e($r['at']) ?></span></div><p><?= nl2br(e($r['body'])) ?></p></div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <form class="comment-form" id="comment-form" data-slug="<?= e($post['slug']) ?>" novalidate>
          <h3>دیدگاه خود را بنویسید</h3>
          <p class="field__hint">دیدگاه‌ها پس از بررسی منتشر می‌شوند. ایمیل شما نمایش داده نمی‌شود.</p>
          <input type="hidden" name="parent_id" value="">
          <p class="comment-form__replying" hidden>در پاسخ به یک دیدگاه — <button type="button" class="link-btn" data-cancel-reply>لغو</button></p>
          <div class="comment-form__row">
            <div class="field"><label for="cm-name">نام</label><input class="input" id="cm-name" name="author_name" type="text" autocomplete="name"><p class="field__error" data-error-for="author_name"></p></div>
            <div class="field"><label for="cm-email">ایمیل (اختیاری)</label><input class="input" id="cm-email" name="author_email" type="email" dir="ltr" autocomplete="email"><p class="field__error" data-error-for="author_email"></p></div>
          </div>
          <div class="field"><label for="cm-body">دیدگاه</label><textarea class="textarea" id="cm-body" name="body" rows="4"></textarea><p class="field__error" data-error-for="body"></p></div>
          <div class="hp-field" aria-hidden="true"><label for="cm-website">وب‌سایت</label><input id="cm-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
          <div class="alert" role="status" hidden></div>
          <button type="submit" class="btn btn--primary">ارسال دیدگاه</button>
        </form>
      </section>
      <?php endif; ?>
    </article>
    <?php
      $sidebar = ['popular' => \App\Services\BlogService::list(['sort' => 'popular', 'per_page' => 4, 'exclude' => [(int) $post['id']]])['items'], 'tags' => [], 'categories' => []];
      require __DIR__ . '/sidebar.php';
    ?>
  </div>
</main>
