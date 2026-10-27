<?php
declare(strict_types=1);

namespace App\Core;

/** Hardened PHP session: HttpOnly, SameSite=Lax, Secure on HTTPS, strict mode, id rotation on login. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (bool) Config::get('app.session.secure') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        session_name((string) Config::get('app.session.name', 'APSESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (Config::get('app.base_path') ?: '') . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /** New session id after privilege changes (login / logout) to prevent fixation. */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }
}
