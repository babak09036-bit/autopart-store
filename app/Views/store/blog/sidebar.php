<?php /** @var array $sidebar */ $banner = $banner ?? null; ?>
<aside class="blog-sidebar" aria-label="ستون کناری مجله">
  <?php if ($sidebar['popular']): ?>
  <section class="card-surface blog-widget">
    <h2>محبوب‌ترین مقالات</h2>
    <ol class="blog-popular">
      <?php foreach ($sidebar['popular'] as $p): ?><li><a href="<?= e($p['url']) ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?>
    </ol>
  </section>
  <?php endif; ?>
  <?php if ($banner): ?>
  <a class="blog-banner" href="<?= e($banner['button_url'] ?: 'blog') ?>"><img src="<?= e($banner['image']) ?>" alt="<?= e($banner['alt'] ?: $banner['title']) ?>" width="640" height="360" loading="lazy"><span><?= e($banner['title']) ?></span></a>
  <?php endif; ?>
  <?php if ($sidebar['tags']): ?>
  <section class="card-surface blog-widget">
    <h2>برچسب‌ها</h2>
    <div class="sub-chips"><?php foreach ($sidebar['tags'] as $t): ?><a class="chip" href="blog/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
  <section class="card-surface blog-widget newsletter-box">
    <h2>عضویت در خبرنامه</h2>
    <p>تازه‌ترین راهنماها و تخفیف‌ها را در ایمیل خود دریافت کنید.</p>
    <form class="newsletter-mini" data-newsletter novalidate>
      <label class="visually-hidden" for="side-email">ایمیل</label>
      <input class="input" id="side-email" name="email" type="email" dir="ltr" placeholder="you@example.com" autocomplete="email">
      <button type="submit" class="btn btn--dark btn--sm">عضویت</button>
      <p class="newsletter__msg" role="status"></p>
    </form>
  </section>
</aside>
