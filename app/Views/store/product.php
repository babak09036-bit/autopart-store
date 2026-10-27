<?php
/** @var array $p CatalogService::details()  @var ?array $banner */
$crumbs = array_merge($p['breadcrumbs'], [['name' => $p['title'], 'url' => null]]);
$images = $p['images'] ?: [['path' => $p['image'], 'alt' => $p['title']]];
?>
<main id="main" class="container">
  <?php require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <article class="product" itemscope>
    <div class="gallery">
      <div class="gallery__main" id="gallery-main">
        <img id="gallery-img" src="<?= e($images[0]['path']) ?>" alt="<?= e($images[0]['alt'] ?: $p['title']) ?>" width="400" height="400" fetchpriority="high">
      </div>
      <?php if (count($images) > 1): ?>
      <div class="gallery__thumbs" role="group" aria-label="تصاویر محصول">
        <?php foreach ($images as $i => $img): ?>
        <button type="button" class="gallery__thumb" data-src="<?= e($img['path']) ?>" data-alt="<?= e($img['alt'] ?: $p['title']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="تصویر <?= e(fa_digits($i + 1)) ?>">
          <img src="<?= e($img['path']) ?>" alt="" width="72" height="72" loading="lazy">
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="product__info">
      <?php if ($p['brand']): ?><a class="product__brand" href="brands/<?= e($p['brand']['slug']) ?>"><?= e($p['brand']['name']) ?></a><?php endif; ?>
      <h1><?= e($p['title']) ?></h1>
      <div class="product__meta">
        <?php if ($p['reviewsCount'] > 0): ?>
        <a href="<?= e($p['url']) ?>#reviews"><?= e(fa_digits(number_format($p['rating'], 1))) ?> از ۵ — <?= e(fa_number($p['reviewsCount'])) ?> نظر</a>
        <?php else: ?><span>هنوز نظری ثبت نشده است</span><?php endif; ?>
        <?php if ($p['sku']): ?><span>کد کالا: <span dir="ltr"><?= e($p['sku']) ?></span></span><?php endif; ?>
        <?php if ($p['category']): ?><a href="categories/<?= e($p['category']['slug']) ?>"><?= e($p['category']['name']) ?></a><?php endif; ?>
        <?php if ($p['isNew']): ?><span class="badge badge--new">جدید</span><?php endif; ?>
        <?php if ($p['isExpertPick']): ?><span class="badge badge--expert"><?= icon('award', 'icon--sm') ?>منتخب کارشناس</span><?php endif; ?>
      </div>
      <?php if ($p['shortDescription']): ?><p><?= e($p['shortDescription']) ?></p><?php endif; ?>
      <?php if ($p['attributes']): ?>
      <h2 class="visually-hidden">ویژگی‌های اصلی</h2>
      <ul class="spec-list">
        <?php foreach (array_slice($p['attributes'], 0, 4) as $a): ?><li><span><?= e($a['name']) ?>:</span> <?= e($a['value']) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="compat">
        <?php if ($p['universalFit']): ?>
        <strong>سازگاری</strong><?= icon('check', 'icon--sm') ?> مناسب همه خودروهای سواری
        <?php elseif ($p['vehicles']): ?>
        <strong>خودروهای سازگار</strong>
        <?php foreach ($p['vehicles'] as $v): ?><a class="chip" href="products?vehicle=<?= e($v['slug']) ?>" title="<?= e($v['note'] ?? '') ?>"><?= e($v['brand'] . ' ' . $v['name']) ?>
          <small><?= e(fa_digits($v['year_from'] === $v['year_to'] ? (string) $v['year_from'] : $v['year_from'] . '–' . $v['year_to'])) ?></small></a><?php endforeach; ?>
        <?php else: ?><strong>سازگاری</strong> اطلاعات سازگاری این کالا ثبت نشده است؛ پیش از خرید با کارشناسان ما مشورت کنید.<?php endif; ?>
      </div>
      <form class="fitment" id="fitment-form" data-product="<?= e((string) $p['id']) ?>" novalidate>
        <p class="fitment__title"><?= icon('car', 'icon--sm') ?> آیا این کالا با خودروی من سازگار است؟</p>
        <div class="fitment__fields">
          <label class="visually-hidden" for="fit-brand">برند خودرو</label><select class="select" id="fit-brand" name="brand" disabled><option>در حال بارگذاری…</option></select>
          <label class="visually-hidden" for="fit-model">مدل خودرو</label><select class="select" id="fit-model" name="model" disabled></select>
          <label class="visually-hidden" for="fit-year">سال تولید</label><select class="select" id="fit-year" name="year" disabled></select>
          <button type="submit" class="btn btn--dark btn--sm">بررسی</button>
        </div>
        <p class="form-error" role="alert"></p>
        <div class="fitment__result" aria-live="polite"></div>
      </form>
    </div>

    <aside class="buybox" aria-label="خرید">
      <?php if (!$p['inStock']): ?><span class="stock stock--out">ناموجود</span>
      <?php elseif ($p['stock'] <= 10): ?><span class="stock stock--low">تنها <?= e(fa_number($p['stock'])) ?> عدد در انبار باقی مانده</span>
      <?php else: ?><span class="stock stock--in"><?= icon('check', 'icon--sm') ?>موجود در انبار</span><?php endif; ?>

      <?php if ($p['variants']): ?>
      <div class="field">
        <label for="variant">نوع محصول</label>
        <select class="select" id="variant">
          <?php foreach ($p['variants'] as $v): ?>
          <option value="<?= (int) $v['id'] ?>" data-price="<?= e((string) $v['price']) ?>" data-stock="<?= (int) $v['stock'] ?>"<?= $v['stock'] < 1 ? ' disabled' : '' ?>><?= e($v['name']) ?><?= $v['stock'] < 1 ? ' (ناموجود)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="price" id="buy-price">
        <?php if ($p['oldPrice']): ?><div class="price__old"><span class="visually-hidden">قیمت قبلی:</span><del><?= e(fa_number($p['oldPrice'])) ?></del><span class="badge badge--red"><?= e(fa_number($p['discountPercent'])) ?>٪ تخفیف</span></div><?php endif; ?>
        <div class="price__now"><strong id="buy-price-now"><?= e(fa_number($p['variants'] ? $p['variants'][0]['price'] : $p['price'])) ?></strong><span>تومان</span></div>
      </div>
      <?php if ($p['inStock']): ?>
      <div class="buybox__qty"><span>تعداد:</span>
        <div class="qty">
          <button type="button" data-step="1" aria-label="افزایش تعداد"><?= icon('plus', 'icon--sm') ?></button>
          <input id="qty" type="text" inputmode="numeric" value="۱" aria-label="تعداد">
          <button type="button" data-step="-1" aria-label="کاهش تعداد"><?= icon('minus', 'icon--sm') ?></button>
        </div>
      </div>
      <button type="button" class="btn btn--primary btn--block" id="add-to-cart" data-product="<?= (int) $p['id'] ?>"><?= icon('cart', 'icon--sm') ?>افزودن به سبد خرید</button>
      <?php else: ?>
      <button type="button" class="btn btn--block" disabled>ناموجود</button>
      <?php endif; ?>
      <button type="button" class="btn btn--light btn--block" data-fav="<?= (int) $p['id'] ?>" aria-pressed="false"><?= icon('heart', 'icon--sm') ?><span>علاقه‌مندی</span></button>
      <div class="buybox__row"><?= icon('truck') ?>ارسال رایگان برای خرید بالای <?= e(fa_number((int) \App\Services\SettingsService::get('free_shipping_threshold'))) ?> تومان</div>
      <div class="buybox__row"><?= icon('shield') ?>ضمانت اصالت و سلامت فیزیکی کالا</div>
      <div class="buybox__row"><?= icon('return') ?><a href="pages/returns">۷ روز ضمانت بازگشت طبق شرایط</a></div>
    </aside>
  </article>

  <?php if ($banner): ?>
  <a class="inline-banner" href="<?= e($banner['button_url'] ?: '') ?>" style="--banner-bg: <?= e($banner['bg_color'] ?: '#F5F6F8') ?>">
    <span><strong><?= e($banner['title']) ?></strong><?php if ($banner['description']): ?> — <?= e($banner['description']) ?><?php endif; ?></span>
    <?php if ($banner['button_text']): ?><span class="btn btn--sm btn--primary"><?= e($banner['button_text']) ?></span><?php endif; ?>
  </a>
  <?php endif; ?>

  <section class="tabs-panel" aria-label="اطلاعات تکمیلی">
    <div class="tabs-panel__list" role="tablist">
      <button type="button" class="tabs-panel__tab" role="tab" id="tab-desc" aria-controls="panel-desc" aria-selected="true">توضیحات</button>
      <button type="button" class="tabs-panel__tab" role="tab" id="tab-specs" aria-controls="panel-specs" aria-selected="false" tabindex="-1">مشخصات فنی</button>
      <button type="button" class="tabs-panel__tab" role="tab" id="tab-reviews" aria-controls="panel-reviews" aria-selected="false" tabindex="-1">نظرات (<?= e(fa_number($p['reviewsCount'])) ?>)</button>
      <button type="button" class="tabs-panel__tab" role="tab" id="tab-questions" aria-controls="panel-questions" aria-selected="false" tabindex="-1">پرسش و پاسخ</button>
    </div>
    <div class="tabs-panel__body rich-text" role="tabpanel" id="panel-desc" aria-labelledby="tab-desc">
      <?= $p['description'] ?: '<p>' . e($p['shortDescription'] ?? '') . '</p>' ?>
      <?php if ($p['faqs']): ?>
      <h2>پرسش‌های متداول</h2>
      <div class="faq"><?php foreach ($p['faqs'] as $f): ?><details><summary><?= e($f['question']) ?></summary><p><?= e($f['answer']) ?></p></details><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <div class="tabs-panel__body" role="tabpanel" id="panel-specs" aria-labelledby="tab-specs" hidden>
      <table class="spec-table"><tbody>
        <?php if ($p['brand']): ?><tr><th scope="row">برند</th><td><?= e($p['brand']['name']) ?></td></tr><?php endif; ?>
        <?php foreach ($p['attributes'] as $a): ?><tr><th scope="row"><?= e($a['name']) ?></th><td><?= e($a['value']) ?></td></tr><?php endforeach; ?>
        <?php if ($p['sku']): ?><tr><th scope="row">SKU</th><td dir="ltr"><?= e($p['sku']) ?></td></tr><?php endif; ?>
        <?php if ($p['gtin']): ?><tr><th scope="row">GTIN</th><td dir="ltr"><?= e($p['gtin']) ?></td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="tabs-panel__body" role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" hidden>
      <div id="reviews" class="reviews" data-product="<?= (int) $p['id'] ?>"><p class="field__hint">در حال بارگذاری نظرات…</p></div>
    </div>
    <div class="tabs-panel__body" role="tabpanel" id="panel-questions" aria-labelledby="tab-questions" hidden>
      <div id="questions" data-product="<?= (int) $p['id'] ?>"></div>
    </div>
  </section>

  <?php foreach (['complementary' => 'محصولات مکمل', 'related' => 'محصولات مرتبط'] as $key => $label): if (!$p[$key]) { continue; } ?>
  <section class="section" aria-labelledby="<?= $key ?>-title">
    <div class="section-head"><h2 id="<?= $key ?>-title" class="section-head__title"><?= e($label) ?></h2></div>
    <div class="carousel" data-carousel>
      <button type="button" class="carousel__btn carousel__btn--prev" data-prev aria-label="قبلی"><?= icon('chevron-right') ?></button>
      <div class="carousel__track"><?php foreach ($p[$key] as $rp) { $p_ = $p; $p = $rp; require __DIR__ . '/partials/product-card.php'; $p = $p_; } ?></div>
      <button type="button" class="carousel__btn carousel__btn--next" data-next aria-label="بعدی"><?= icon('chevron-left') ?></button>
    </div>
  </section>
  <?php endforeach; ?>
</main>
