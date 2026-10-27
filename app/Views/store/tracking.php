<main id="main" class="container">
  <?php $crumbs = [['name' => 'پیگیری سفارش', 'url' => null]]; require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <div class="track">
    <h1 class="page-title">پیگیری سفارش</h1>
    <form class="track__form track__form--two" id="track-form" novalidate>
      <div class="field">
        <label for="track-code">کد سفارش</label>
        <input class="input" id="track-code" name="number" type="text" dir="ltr" placeholder="AP-204518" autocomplete="off">
      </div>
      <div class="field">
        <label for="track-mobile">شماره موبایل ثبت‌شده در سفارش</label>
        <input class="input" id="track-mobile" name="mobile" type="tel" inputmode="numeric" dir="ltr" placeholder="09xxxxxxxxx" autocomplete="tel">
      </div>
      <button type="submit" class="btn btn--primary"><?= icon('search', 'icon--sm') ?>پیگیری</button>
      <p class="form-error" id="track-error" role="alert"></p>
      <p class="field__hint track__note">برای حفظ حریم خصوصی، وضعیت سفارش فقط با کد سفارش و شماره موبایل خریدار نمایش داده می‌شود. اگر وارد حساب شده‌اید، همه سفارش‌ها در <a href="account">حساب کاربری</a> قابل مشاهده است.</p>
    </form>
    <section class="track__result" id="track-result" aria-live="polite"></section>
  </div>
</main>
