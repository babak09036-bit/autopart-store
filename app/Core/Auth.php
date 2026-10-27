<?php
declare(strict_types=1);

namespace App\Core;

/** Customer authentication (session key "user_id"). */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if ($id) {
                self::$user = Database::one("SELECT id, full_name, mobile, email, status, created_at FROM users WHERE id = :id AND status = 'active'", ['id' => (int) $id]);
                if (!self::$user) {
                    Session::forget('user_id');
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Csrf::rotate();
        self::$loaded = false;
        Database::query('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id', ['id' => $userId]);
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::regenerate();
        Csrf::rotate();
        self::$user = null;
        self::$loaded = true;
    }
}
