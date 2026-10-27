<?php
/** Server-rendered product card (same markup as AP.card in js/main.js). @var array $p product DTO */
$off = $p['discountPercent'];
?>
<article class="pcard pcard--default">
  <div class="pcard__media">
    <a href="<?= e($p['url']) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($p['image']) ?>" alt="<?= e($p['imageAlt']) ?>" width="400" height="400" loading="lazy" decoding="async"></a>
    <div class="pcard__badges"><?php if ($p['isNew']): ?><span class="badge badge--new">جدید</span><?php endif; ?></div>
    <button type="button" class="fav-btn" data-fav="<?= (int) $p['id'] ?>" aria-pressed="false" aria-label="افزودن به علاقه‌مندی‌ها"><?= icon('heart') ?></button>
  </div>
  <div class="pcard__body">
    <?php if ($p['brand']): ?><a class="pcard__brand" href="brands/<?= e($p['brand']['slug']) ?>"><?= e($p['brand']['name']) ?></a><?php endif; ?>
    <h3 class="pcard__title"><a href="<?= e($p['url']) ?>"><?= e($p['title']) ?></a></h3>
    <?php if ($p['reviewsCount'] > 0): ?>
    <div class="pcard__rating"><?= icon('star-filled', 'icon--sm') ?><span><?= e(fa_digits(number_format($p['rating'], 1))) ?></span><span class="pcard__reviews">(<?= e(fa_number($p['reviewsCount'])) ?> نظر)</span></div>
    <?php else: ?>
    <div class="pcard__rating pcard__rating--none">بدون امتیاز</div>
    <?php endif; ?>
    <?php if (!$p['inStock']): ?><span class="stock stock--out">ناموجود</span>
    <?php elseif ($p['stock'] <= 10): ?><span class="stock stock--low">تنها <?= e(fa_number($p['stock'])) ?> عدد در انبار</span>
    <?php else: ?><span class="stock stock--in"><?= icon('check', 'icon--sm') ?>موجود در انبار</span><?php endif; ?>
    <div class="price">
      <?php if ($off): ?><div class="price__old"><span class="visually-hidden">قیمت قبلی:</span><del><?= e(fa_number($p['oldPrice'])) ?></del><span class="badge badge--red"><?= e(fa_number($off)) ?>٪</span></div>
      <?php else: ?><div class="price__old" aria-hidden="true"></div><?php endif; ?>
      <div class="price__now"><strong><?= e(fa_number($p['price'])) ?></strong><span>تومان</span></div>
    </div>
    <?php if ($p['inStock']): ?>
    <button type="button" class="btn btn--primary btn--block btn--sm" data-add="<?= (int) $p['id'] ?>" aria-label="افزودن <?= e($p['title']) ?> به سبد خرید"><?= icon('cart', 'icon--sm') ?><span>افزودن به سبد</span></button>
    <?php else: ?>
    <button type="button" class="btn btn--block btn--sm" disabled>ناموجود</button>
    <?php endif; ?>
  </div>
</article>
