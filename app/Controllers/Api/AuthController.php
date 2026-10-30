<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;
use App\Services\Mail\LogMailer;

final class AuthController extends Controller
{
    public function register(Request $request): Response
    {
        $data = $this->validate($request->all(), [
            'full_name' => 'required|string|minlen:3|maxlen:120',
            'mobile' => 'required|mobile',
            'email' => 'nullable|email',
            'password' => 'required|string|minlen:8|maxlen:128',
            'password_confirmation' => 'required|string',
            'terms' => 'required|bool',
        ]);
        $errors = [];
        if ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'تکرار رمز عبور با رمز عبور یکسان نیست.';
        }
        if (!$data['terms']) {
            $errors['terms'] = 'برای ثبت‌نام باید قوانین را بپذیرید.';
        }
        if (Database::value('SELECT 1 FROM users WHERE mobile = :m', ['m' => $data['mobile']])) {
            $errors['mobile'] = 'با این شماره قبلاً حساب ساخته شده است.';
        }
        if (!empty($data['email']) && Database::value('SELECT 1 FROM users WHERE email = :e', ['e' => $data['email']])) {
            $errors['email'] = 'این ایمیل قبلاً ثبت شده است.';
        }
        if ($errors) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', $errors);
        }
        $id = Database::insert('users', [
            'full_name' => $data['full_name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
        CartService::mergeGuestCart($id);
        Auth::login($id);
        return $this->ok(['user' => ['name' => $data['full_name']], 'csrf' => Csrf::token()], 'حساب کاربری شما ساخته شد.', 201);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request->all(), ['mobile' => 'required|string|maxlen:190', 'password' => 'required|string|maxlen:128']);
        $identifier = mb_strtolower(to_en_digits($data['mobile']));
        $this->assertNotLocked('user', $identifier, $request->ip());

        $user = Database::one('SELECT id, full_name, password_hash, status FROM users WHERE mobile = :m OR email = :e', ['m' => $identifier, 'e' => $identifier]);
        // password_verify against a dummy hash keeps timing similar when the user does not exist.
        $valid = password_verify($data['password'], $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltua8vQbfBKqgYJu1GfJ1ExAGYJN6nC.');
        Database::insert('login_attempts', ['scope' => 'user', 'identifier' => $identifier, 'ip' => $request->ip(), 'success' => $valid && $user ? 1 : 0]);
        if (!$user || !$valid) {
            throw new HttpException(422, 'شماره موبایل یا رمز عبور اشتباه است.', ['password' => 'شماره موبایل یا رمز عبور اشتباه است.']);
        }
        if ($user['status'] !== 'active') {
            throw new HttpException(403, 'حساب کاربری شما غیرفعال شده است؛ با پشتیبانی تماس بگیرید.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => password_hash($data['password'], PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
        CartService::mergeGuestCart((int) $user['id']);
        Auth::login((int) $user['id']);
        return $this->ok(['user' => ['name' => $user['full_name']], 'csrf' => Csrf::token()], 'با موفقیت وارد شدید.');
    }

    public function logout(): Response
    {
        Auth::logout();
        return $this->ok(['csrf' => Csrf::token()], 'از حساب خود خارج شدید.');
    }

    /** Always answers the same way so the endpoint cannot be used to discover accounts. */
    public function forgotPassword(Request $request): Response
    {
        $data = $this->validate($request->all(), ['email' => 'required|email']);
        $user = Database::one("SELECT id, full_name, email FROM users WHERE email = :e AND status = 'active'", ['e' => $data['email']]);
        if ($user) {
            $token = random_token();
            $minutes = (int) Config::get('app.security.password_reset_minutes', 30);
            Database::query('DELETE FROM password_reset_tokens WHERE user_id = :u', ['u' => $user['id']]);
            Database::insert('password_reset_tokens', [
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + $minutes * 60),
            ]);
            LogMailer::make()->send($user['email'], 'بازیابی رمز عبور لوکس جوکر',
                "سلام {$user['full_name']}\nبرای تعیین رمز عبور جدید روی پیوند زیر بزنید (تا {$minutes} دقیقه معتبر است):\n" . absolute_url('reset-password?token=' . $token));
        }
        return $this->ok(null, 'اگر حسابی با این ایمیل وجود داشته باشد، پیوند بازیابی برای آن ارسال می‌شود.');
    }

    public function resetPassword(Request $request): Response
    {
        $data = $this->validate($request->all(), [
            'token' => 'required|regex:/^[a-f0-9]{64}$/',
            'password' => 'required|string|minlen:8|maxlen:128',
            'password_confirmation' => 'required|string',
        ]);
        if ($data['password'] !== $data['password_confirmation']) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', ['password_confirmation' => 'تکرار رمز عبور با رمز عبور یکسان نیست.']);
        }
        $row = Database::one('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :h AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()', ['h' => hash('sha256', $data['token'])]);
        if (!$row) {
            throw new HttpException(422, 'پیوند بازیابی نامعتبر است یا منقضی شده است.');
        }
        Database::transaction(static function () use ($row, $data) {
            Database::query('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => password_hash($data['password'], PASSWORD_DEFAULT), 'id' => $row['user_id']]);
            Database::query('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id', ['id' => $row['id']]);
        });
        return $this->ok(null, 'رمز عبور شما تغییر کرد؛ اکنون می‌توانید وارد شوید.');
    }

    /** Lockout after repeated failures per identifier and per IP. */
    public static function assertNotLocked(string $scope, string $identifier, string $ip): void
    {
        $max = (int) Config::get('app.security.login_max_attempts', 5);
        $window = (int) Config::get('app.security.login_window_minutes', 15);
        $byId = (int) Database::value('SELECT COUNT(*) FROM login_attempts WHERE scope = :s AND identifier = :i AND success = 0 AND created_at > UTC_TIMESTAMP() - INTERVAL :w MINUTE',
            ['s' => $scope, 'i' => $identifier, 'w' => $window]);
        $byIp = (int) Database::value('SELECT COUNT(*) FROM login_attempts WHERE scope = :s AND ip = :ip AND success = 0 AND created_at > UTC_TIMESTAMP() - INTERVAL :w MINUTE',
            ['s' => $scope, 'ip' => $ip, 'w' => $window]);
        if ($byId >= $max || $byIp >= $max * 4) {
            throw new HttpException(429, 'به دلیل تلاش‌های ناموفق متعدد، ورود موقتاً مسدود است. ' . fa_digits($window) . ' دقیقه دیگر تلاش کنید.');
        }
    }
}
