<main id="main" class="container">
  <?php $crumbs = [['name' => 'علاقه‌مندی‌ها', 'url' => null]]; require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <h1 class="page-title">علاقه‌مندی‌های من <small id="fav-count"></small></h1>
  <p class="field__hint" id="fav-guest-note" hidden>علاقه‌مندی‌ها فعلاً فقط در همین مرورگر ذخیره می‌شود. <a href="login">وارد شوید</a> تا در حساب شما نگه داشته شود.</p>
  <div class="fav-actions" id="fav-actions" hidden>
    <button type="button" class="btn btn--primary btn--sm" data-all-to-cart><?= icon('cart', 'icon--sm') ?>افزودن همه به سبد</button>
    <button type="button" class="btn btn--light btn--sm" data-clear-favs><?= icon('trash', 'icon--sm') ?>پاک کردن فهرست</button>
  </div>
  <div class="grid-products grid-products--wide" id="fav-grid"></div>
  <div class="empty-state empty-state--panel" id="fav-empty" hidden>
    <img src="assets/images/ui/no-results.svg" alt="" width="320" height="240">
    <h2>فهرست علاقه‌مندی‌های شما خالی است</h2>
    <p>با زدن آیکون قلب روی هر محصول، آن را برای بعد ذخیره کنید.</p>
    <a class="btn btn--primary" href="products">مشاهده محصولات</a>
  </div>
</main>
