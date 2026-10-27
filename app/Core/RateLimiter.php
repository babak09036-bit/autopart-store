<?php
declare(strict_types=1);

namespace App\Core;

/** Fixed-window limiter backed by the rate_limits table. */
final class RateLimiter
{
    /** Registers a hit; returns false when the bucket is over its limit. */
    public static function hit(string $key, int $max, int $seconds): bool
    {
        $bucket = hash('sha256', $key);
        Database::query(
            'INSERT INTO rate_limits (bucket, hits, reset_at) VALUES (:b, 1, UTC_TIMESTAMP() + INTERVAL :s SECOND)
             ON DUPLICATE KEY UPDATE
               hits = IF(reset_at <= UTC_TIMESTAMP(), 1, hits + 1),
               reset_at = IF(reset_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL :s2 SECOND, reset_at)',
            ['b' => $bucket, 's' => $seconds, 's2' => $seconds]
        );
        $hits = (int) Database::value('SELECT hits FROM rate_limits WHERE bucket = :b', ['b' => $bucket]);
        if (random_int(1, 200) === 1) {
            Database::query('DELETE FROM rate_limits WHERE reset_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
        }
        return $hits <= $max;
    }

    public static function enforce(string $key, int $max, int $seconds): void
    {
        if (!self::hit($key, $max, $seconds)) {
            throw new HttpException(429, 'تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.');
        }
    }
}
