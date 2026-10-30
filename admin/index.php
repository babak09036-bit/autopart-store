<?php
declare(strict_types=1);

// Admin panel entry: serves the login page or the single-page panel shell. All data goes through /api/admin/* (permission-checked in PHP).
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\AdminAuth;
use App\Core\Csrf;
use App\Core\Session;
use App\Services\SettingsService;

Session::start();
$admin = AdminAuth::admin();
$csrf = Csrf::token();
$store = (string) SettingsService::get('store_name', 'فروشگاه');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'");
$v = '?v=11';
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <base href="<?= e(url('')) ?>">
  <title>پنل مدیریت <?= e($store) ?></title>
  <link rel="icon" href="assets/images/ui/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="css/style.css<?= $v ?>">
  <link rel="stylesheet" href="admin/assets/admin.css<?= $v ?>">
  <script>window.ADMIN = <?= json_out(['csrf' => $csrf, 'base' => url(''), 'store' => $store, 'loggedIn' => (bool) $admin]) ?>;</script>
</head>
<body class="admin-body">
<?php if (!$admin): ?>
  <main class="admin-login">
    <form class="admin-login__card" id="admin-login" novalidate>
      <img src="assets/images/ui/logo.svg" alt="<?= e($store) ?>" width="176" height="45">
      <h1>ورود به پنل مدیریت</h1>
      <div class="a-alert a-alert--error" id="login-error" role="alert" hidden></div>
      <label class="a-field"><span>نام کاربری یا ایمیل</span><input class="a-input" name="username" autocomplete="username" dir="ltr" required></label>
      <label class="a-field"><span>رمز عبور</span><input class="a-input" name="password" type="password" autocomplete="current-password" dir="ltr" required></label>
      <button type="submit" class="a-btn a-btn--primary a-btn--block">ورود</button>
      <p class="a-hint">پس از ۵ تلاش ناموفق، ورود به مدت ۱۵ دقیقه مسدود می‌شود.</p>
    </form>
  </main>
  <script>
    (function () {
      var form = document.getElementById('admin-login');
      var err = document.getElementById('login-error');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button');
        btn.disabled = true;
        fetch('api/admin/auth/login', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': window.ADMIN.csrf },
          body: JSON.stringify({ username: form.username.value.trim(), password: form.password.value })
        }).then(function (r) { return r.json(); }).then(function (res) {
          if (res.success) { location.reload(); return; }
          btn.disabled = false;
          err.textContent = res.message || 'ورود ناموفق بود.';
          err.hidden = false;
        }).catch(function () {
          btn.disabled = false;
          err.textContent = 'ارتباط با سرور برقرار نشد.';
          err.hidden = false;
        });
      });
    })();
  </script>
<?php else: ?>
  <div class="admin" id="admin">
    <aside class="admin__side" id="admin-side" aria-label="منوی پنل مدیریت"></aside>
    <div class="admin__main">
      <header class="admin__top">
        <button type="button" class="a-icon-btn admin__menu-btn" id="admin-menu-btn" aria-label="باز کردن منو" aria-expanded="false"><svg class="icon"><use href="assets/icons/icons.svg#i-menu"></use></svg></button>
        <h1 class="admin__title" id="admin-title">پنل مدیریت</h1>
        <div class="admin__top-actions">
          <a class="a-btn a-btn--ghost a-btn--sm" href="" target="_blank" rel="noopener">مشاهده سایت</a>
          <a class="a-icon-btn admin__bell" href="admin#/notifications" aria-label="اعلان‌ها"><svg class="icon"><use href="assets/icons/icons.svg#i-alert"></use></svg><span class="a-count" id="admin-unread" hidden></span></a>
          <span class="admin__user" id="admin-user"></span>
          <button type="button" class="a-btn a-btn--light a-btn--sm" id="admin-logout">خروج</button>
        </div>
      </header>
      <main class="admin__content" id="admin-view" tabindex="-1" aria-live="polite"></main>
    </div>
  </div>
  <div class="a-toasts" id="a-toasts" role="status" aria-live="polite"></div>
  <script src="admin/assets/admin-core.js<?= $v ?>" defer></script>
  <script src="admin/assets/admin-editor.js<?= $v ?>" defer></script>
  <script src="admin/assets/admin-modules.js<?= $v ?>" defer></script>
<?php endif; ?>
</body>
</html>
