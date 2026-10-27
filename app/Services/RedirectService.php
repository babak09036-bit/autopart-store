<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;

/** Managed 301/302 redirects with loop detection. */
final class RedirectService
{
    public static function match(string $path): ?Response
    {
        $row = Database::one('SELECT id, target_url, status_code FROM redirects WHERE source_path = :p AND is_active = 1', ['p' => self::normalize($path)]);
        if (!$row) {
            return null;
        }
        Database::query('UPDATE redirects SET hits = hits + 1, last_hit_at = UTC_TIMESTAMP() WHERE id = :id', ['id' => $row['id']]);
        $target = $row['target_url'];
        $location = preg_match('#^https?://#i', $target) ? $target : url(ltrim($target, '/'));
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs !== '' && !str_contains($location, '?')) {
            $location .= '?' . $qs;
        }
        return Response::redirect($location, (int) $row['status_code']);
    }

    public static function normalize(string $path): string
    {
        $path = (string) parse_url(trim($path), PHP_URL_PATH);
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** Throws when saving source → target would create a loop or a chain longer than 5 hops. */
    public static function assertNoLoop(string $source, string $target, ?int $ignoreId = null): void
    {
        if (preg_match('#^https?://#i', $target)) {
            return;
        }
        $source = self::normalize($source);
        $current = self::normalize($target);
        if ($current === $source) {
            throw new HttpException(422, 'مقصد ریدایرکت نمی‌تواند همان مبدأ باشد.', ['target_url' => 'ریدایرکت به خودش ایجاد حلقه می‌کند.']);
        }
        for ($i = 0; $i < 6; $i++) {
            $next = Database::one('SELECT id, target_url FROM redirects WHERE source_path = :p AND is_active = 1', ['p' => $current]);
            if (!$next || ($ignoreId && (int) $next['id'] === $ignoreId)) {
                return;
            }
            if (preg_match('#^https?://#i', $next['target_url'])) {
                return;
            }
            $current = self::normalize($next['target_url']);
            if ($current === $source) {
                throw new HttpException(422, 'این ریدایرکت یک حلقه ایجاد می‌کند.', ['target_url' => 'مسیر مقصد دوباره به مبدأ برمی‌گردد.']);
            }
        }
        throw new HttpException(422, 'زنجیره ریدایرکت بیش از حد طولانی است.', ['target_url' => 'بیش از ۵ ریدایرکت پشت‌سرهم مجاز نیست.']);
    }

    /** Called after a slug change so old URLs keep working. */
    public static function slugChanged(string $prefix, string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === $newSlug || $oldSlug === '') {
            return;
        }
        $source = self::normalize($prefix . '/' . $oldSlug);
        $target = self::normalize($prefix . '/' . $newSlug);
        // Point any existing redirects that targeted the old URL straight at the new one (no chains).
        Database::query('UPDATE redirects SET target_url = :t WHERE target_url IN (:a, :b)', ['t' => $target, 'a' => $source, 'b' => ltrim($source, '/')]);
        Database::query('DELETE FROM redirects WHERE source_path = :t', ['t' => $target]);
        Database::query(
            'INSERT INTO redirects (source_path, target_url, status_code, is_active, is_auto) VALUES (:s, :t, 301, 1, 1)
             ON DUPLICATE KEY UPDATE target_url = VALUES(target_url), status_code = 301, is_active = 1',
            ['s' => $source, 't' => $target]
        );
    }
}
