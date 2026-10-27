<main id="main" class="container">
  <?php $crumbs = [['name' => 'سبد خرید', 'url' => null]]; require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <h1 class="page-title">سبد خرید <small id="cart-count"></small></h1>
  <div id="cart-root" aria-live="polite"><p class="field__hint">در حال بارگذاری سبد خرید…</p></div>
  <section class="section" id="cart-suggest" aria-labelledby="suggest-title" hidden>
    <div class="section-head"><h2 id="suggest-title" class="section-head__title">شاید این‌ها را هم لازم داشته باشید</h2></div>
    <div class="carousel" data-carousel>
      <button type="button" class="carousel__btn carousel__btn--prev" data-prev aria-label="قبلی"><?= icon('chevron-right') ?></button>
      <div class="carousel__track" id="suggest-track"></div>
      <button type="button" class="carousel__btn carousel__btn--next" data-next aria-label="بعدی"><?= icon('chevron-left') ?></button>
    </div>
  </section>
</main>
