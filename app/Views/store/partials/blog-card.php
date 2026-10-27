<?php /** @var array $post BlogService::card() */ ?>
<article class="post-card">
  <a class="post-card__media" href="<?= e($post['url']) ?>" tabindex="-1" aria-hidden="true">
    <img src="<?= e($post['image']) ?>" alt="<?= e($post['image_alt']) ?>" width="640" height="360" loading="lazy">
  </a>
  <div class="post-card__body">
    <?php if ($post['category']): ?><a class="post-card__cat" href="<?= e($post['category']['url']) ?>"><?= e($post['category']['name']) ?></a><?php endif; ?>
    <h3 class="post-card__title"><a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a></h3>
    <?php if ($post['excerpt']): ?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
    <div class="post-card__meta">
      <time datetime="<?= e(gmdate('c', (int) strtotime((string) $post['published_at']))) ?>"><?= e($post['published_at_fa']) ?></time>
      <span><?= icon('clock', 'icon--sm') ?><?= e(fa_digits($post['reading_minutes'])) ?> دقیقه مطالعه</span>
    </div>
  </div>
</article>
