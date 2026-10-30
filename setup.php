<?php
declare(strict_types=1);

/*
 * One-time creation of the first (super) administrator, for hosts without a terminal.
 * It only works while BOTH are true:
 *   1. .env has SETUP_TOKEN set to a random value of at least 32 characters, and
 *   2. there is no administrator in the database yet.
 * Otherwise it answers 404. After the first admin is created it switches itself off;
 * remove SETUP_TOKEN from .env afterwards. With a terminal, `php bin/console.php admin:create` does the same.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\RateLimiter;
use App\Core\Session;

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

/** Short plain-text answer for the cases where the setup form must not be shown. */
function setup_stop(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$token = (string) Env::get('SETUP_TOKEN', '');
if ($token === '') {
    setup_stop(404, 'Not found');
}
try {
    $hasAdmin = (int) Database::value('SELECT COUNT(*) FROM admin_users') > 0;
} catch (\Throwable $e) {
    setup_stop(503, 'دیتابیس هنوز آماده نیست: جدول‌ها وارد نشده‌اند یا اطلاعات اتصال در .env درست نیست.');
}
if ($hasAdmin) {
    setup_stop(404, 'Not found');
}
if (strlen($token) < 32) {
    setup_stop(503, 'مقدار SETUP_TOKEN در فایل .env کوتاه است؛ یک متن تصادفی حداقل ۳۲ کاراکتری بگذارید و صفحه را دوباره باز کنید.');
}

Session::start();
$errors = [];
$done = false;
$old = ['full_name' => '', 'username' => '', 'email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $old = ['full_name' => trim((string) ($_POST['full_name'] ?? '')), 'username' => strtolower(trim((string) ($_POST['username'] ?? ''))), 'email' => strtolower(trim((string) ($_POST['email'] ?? '')))];
    $password = (string) ($_POST['password'] ?? '');
    if (!RateLimiter::hit('setup:' . $ip, 10, 3600)) {
        $errors[] = 'تعداد تلاش‌ها بیش از حد مجاز است؛ یک ساعت بعد دوباره امتحان کنید.';
    } elseif (!Csrf::verify((string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.';
    } elseif (!hash_equals($token, (string) ($_POST['setup_token'] ?? ''))) {
        $errors[] = 'کد راه‌اندازی درست نیست.';
    } else {
        if (mb_strlen($old['full_name']) < 3) {
            $errors[] = 'نام کامل را بنویسید.';
        }
        if (!preg_match('/^[a-z0-9_.-]{3,60}$/', $old['username'])) {
            $errors[] = 'نام کاربری فقط حروف کوچک انگلیسی، عدد، نقطه، - و _ (۳ تا ۶۰ کاراکتر).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ایمیل معتبر نیست.';
        }
        if (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'رمز عبور باید حداقل ۱۲ کاراکتر و شامل حرف و عدد باشد.';
        } elseif (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) {
            $errors[] = 'تکرار رمز عبور یکسان نیست.';
        }
    }
    if (!$errors) {
        Database::transaction(static function () use ($old, $password) {
            // Re-checked inside the transaction so two simultaneous submissions cannot both create an admin.
            if ((int) Database::value('SELECT COUNT(*) FROM admin_users FOR UPDATE') > 0) {
                return;
            }
            $id = Database::insert('admin_users', ['full_name' => $old['full_name'], 'username' => $old['username'], 'email' => $old['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active']);
            Database::query("INSERT INTO admin_user_roles (admin_user_id, role_id) SELECT :a, id FROM roles WHERE slug = 'super_admin'", ['a' => $id]);
            Database::insert('admin_activity_logs', ['admin_user_id' => $id, 'action' => 'admin.create', 'entity_type' => 'admin_user', 'entity_id' => $id,
                'description' => 'ایجاد مدیر کل از صفحه راه‌اندازی', 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '')]);
        });
        Csrf::rotate();
        $done = true;
    }
}
$csrf = Csrf::token();
$base = url('');
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>راه‌اندازی مدیر اصلی</title>
  <link rel="stylesheet" href="<?= e($base) ?>css/style.css">
  <link rel="stylesheet" href="<?= e($base) ?>admin/assets/admin.css">
</head>
<body class="admin-body">
  <main class="admin-login">
    <?php if ($done): ?>
    <div class="admin-login__card">
      <h1>مدیر اصلی ساخته شد</h1>
      <div class="a-alert a-alert--success">حساب «<?= e($old['username']) ?>» با نقش مدیر کل ساخته شد و این صفحه از این به بعد غیرفعال است.</div>
      <p class="a-alert a-alert--warn">برای امنیت بیشتر، خط <b dir="ltr">SETUP_TOKEN</b> را از فایل <b dir="ltr">.env</b> روی هاست حذف کنید.</p>
      <a class="a-btn a-btn--primary a-btn--block" href="<?= e($base) ?>admin">ورود به پنل مدیریت</a>
    </div>
    <?php else: ?>
    <form class="admin-login__card" method="post" action="" autocomplete="off" novalidate>
      <h1>ساخت مدیر اصلی فروشگاه</h1>
      <p class="a-hint">این صفحه فقط یک بار و فقط با کد راه‌اندازی (مقدار SETUP_TOKEN در فایل .env) کار می‌کند.</p>
      <?php if ($errors): ?><div class="a-alert a-alert--error" role="alert"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="a-field"><span>کد راه‌اندازی</span><input class="a-input" name="setup_token" type="password" dir="ltr" required autocomplete="off"></label>
      <label class="a-field"><span>نام کامل</span><input class="a-input" name="full_name" value="<?= e($old['full_name']) ?>" required></label>
      <label class="a-field"><span>نام کاربری (انگلیسی)</span><input class="a-input" name="username" dir="ltr" value="<?= e($old['username']) ?>" required autocomplete="username"></label>
      <label class="a-field"><span>ایمیل</span><input class="a-input" name="email" type="email" dir="ltr" value="<?= e($old['email']) ?>" required></label>
      <label class="a-field"><span>رمز عبور (حداقل ۱۲ کاراکتر، حرف و عدد)</span><input class="a-input" name="password" type="password" dir="ltr" required autocomplete="new-password"></label>
      <label class="a-field"><span>تکرار رمز عبور</span><input class="a-input" name="password_confirmation" type="password" dir="ltr" required autocomplete="new-password"></label>
      <button type="submit" class="a-btn a-btn--primary a-btn--block">ساخت مدیر اصلی</button>
    </form>
    <?php endif; ?>
  </main>
</body>
</html>
