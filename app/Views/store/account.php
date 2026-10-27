<main id="main" class="container">
  <?php $crumbs = [['name' => 'حساب کاربری', 'url' => null]]; require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <h1 class="page-title">حساب کاربری</h1>
  <div class="account">
    <nav class="account__nav" aria-label="بخش‌های حساب">
      <button type="button" data-tab="orders" aria-pressed="true"><?= icon('package-track', 'icon--sm') ?>سفارش‌ها</button>
      <button type="button" data-tab="profile" aria-pressed="false"><?= icon('user', 'icon--sm') ?>اطلاعات حساب</button>
      <button type="button" data-tab="addresses" aria-pressed="false"><?= icon('location', 'icon--sm') ?>نشانی‌ها</button>
      <button type="button" data-tab="reviews" aria-pressed="false"><?= icon('star', 'icon--sm') ?>نظرات من</button>
      <button type="button" data-tab="notifications" aria-pressed="false"><?= icon('alert', 'icon--sm') ?>اعلان‌ها</button>
      <a href="favorites"><?= icon('heart', 'icon--sm') ?>علاقه‌مندی‌ها</a>
      <button type="button" data-logout><?= icon('logout', 'icon--sm') ?>خروج</button>
    </nav>
    <section class="account__panel" id="account-panel" aria-live="polite"></section>
  </div>
</main>
