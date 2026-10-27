<?php
declare(strict_types=1);

namespace App\Core;

/** Synchroniser-token CSRF protection; the token travels in the X-CSRF-Token header. */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get('_csrf');
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }

    public static function rotate(): string
    {
        Session::forget('_csrf');
        return self::token();
    }
}
