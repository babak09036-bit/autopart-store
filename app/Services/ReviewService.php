<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Validators\Validator;

final class ReviewService
{
    public const SORTS = ['newest' => 'r.created_at DESC', 'oldest' => 'r.created_at ASC', 'highest' => 'r.rating DESC, r.created_at DESC', 'lowest' => 'r.rating ASC, r.created_at DESC'];

    /** Approved reviews only, with summary and distribution. */
    public static function listForProduct(int $productId, array $q, ?int $userId): array
    {
        $params = ['p' => $productId];
        $where = "r.product_id = :p AND r.status = 'approved'";
        $rating = (int) ($q['rating'] ?? 0);
        if ($rating >= 1 && $rating <= 5) {
            $where .= ' AND r.rating = :rating';
            $params['rating'] = $rating;
        }
        $order = self::SORTS[$q['sort'] ?? 'newest'] ?? self::SORTS['newest'];
        $perPage = 5;
        $page = max(1, (int) ($q['page'] ?? 1));
        $total = (int) Database::value("SELECT COUNT(*) FROM product_reviews r WHERE {$where}", $params);
        $rows = Database::all("SELECT r.id, r.rating, r.title, r.body, r.is_verified_buyer, r.created_at, r.admin_reply, r.admin_reply_visible, r.admin_reply_at, u.full_name
            FROM product_reviews r JOIN users u ON u.id = r.user_id
            WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), $params);

        $images = [];
        if ($rows) {
            $p = [];
            foreach (Database::all('SELECT review_id, path FROM product_review_images WHERE review_id IN ' . Database::in('r', array_column($rows, 'id'), $p), $p) as $img) {
                $images[(int) $img['review_id']][] = $img['path'];
            }
        }
        $dist = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach (Database::all("SELECT rating, COUNT(*) AS c FROM product_reviews WHERE product_id = :p AND status = 'approved' GROUP BY rating", ['p' => $productId]) as $d) {
            $dist[(int) $d['rating']] = (int) $d['c'];
        }
        $count = array_sum($dist);
        $avg = $count ? array_sum(array_map(static fn ($k, $v) => $k * $v, array_keys($dist), $dist)) / $count : 0;

        $mine = null;
        if ($userId) {
            $m = Database::one('SELECT id, status, rejection_reason, rating, title, created_at FROM product_reviews WHERE product_id = :p AND user_id = :u', ['p' => $productId, 'u' => $userId]);
            if ($m) {
                $mine = ['status' => $m['status'], 'rejection_reason' => $m['rejection_reason'], 'title' => $m['title'], 'rating' => (int) $m['rating'], 'created_at_fa' => fa_date($m['created_at'])];
            }
        }

        return [
            'summary' => ['average' => round($avg, 1), 'count' => $count, 'distribution' => $dist],
            'items' => array_map(static fn ($r) => [
                'id' => (int) $r['id'],
                'rating' => (int) $r['rating'],
                'title' => $r['title'],
                'body' => $r['body'],
                'author' => self::displayName($r['full_name']),
                'verified_buyer' => (bool) $r['is_verified_buyer'],
                'created_at_fa' => fa_date($r['created_at']),
                'images' => $images[(int) $r['id']] ?? [],
                'reply' => $r['admin_reply'] && $r['admin_reply_visible'] ? ['body' => $r['admin_reply'], 'at' => fa_date($r['admin_reply_at'])] : null,
            ], $rows),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
            'mine' => $mine,
        ];
    }

    /** "علی رضایی" → "علی ر." — never exposes full names or contact details. */
    public static function displayName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        if (count($parts) < 2) {
            return $parts[0] ?? 'کاربر';
        }
        return $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.';
    }

    /** Verified buyer = at least one delivered order of this user containing the product. Never taken from client input. */
    public static function isVerifiedBuyer(int $userId, int $productId): bool
    {
        return (bool) Database::value("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = :u AND oi.product_id = :p AND o.status = 'delivered' LIMIT 1", ['u' => $userId, 'p' => $productId]);
    }

    public static function create(int $productId, int $userId, array $input, array $files, string $ip): array
    {
        if (!Database::value("SELECT 1 FROM products WHERE id = :id AND status = 'published'", ['id' => $productId])) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        RateLimiter::enforce('review:user:' . $userId, 5, 3600);
        RateLimiter::enforce('review:ip:' . $ip, 10, 3600);

        $data = Validator::validate($input, [
            'rating' => 'required|int|min:1|max:5',
            'title' => 'required|string|minlen:3|maxlen:120',
            'body' => 'required|string|minlen:10|maxlen:2000',
        ]);
        if (Database::value('SELECT 1 FROM product_reviews WHERE product_id = :p AND user_id = :u', ['p' => $productId, 'u' => $userId])) {
            throw new HttpException(409, 'شما قبلاً برای این محصول نظر ثبت کرده‌اید.');
        }
        $verified = self::isVerifiedBuyer($userId, $productId);
        if (SettingsService::get('reviews_require_purchase') && !$verified) {
            throw new HttpException(403, 'ثبت نظر فقط برای خریداران این محصول امکان‌پذیر است.');
        }

        $uploads = self::normaliseFiles($files['images'] ?? null);
        if (count($uploads) > 3) {
            throw new HttpException(422, 'حداکثر ۳ تصویر قابل ارسال است.', ['images' => 'حداکثر ۳ تصویر.']);
        }
        $saved = [];
        try {
            foreach ($uploads as $file) {
                $saved[] = Uploader::image($file, 'reviews', (int) \App\Core\Config::get('app.uploads.review_max_bytes'))['path'];
            }
            $id = Database::transaction(static function () use ($productId, $userId, $data, $verified, $ip, $saved) {
                $id = Database::insert('product_reviews', [
                    'product_id' => $productId,
                    'user_id' => $userId,
                    'rating' => $data['rating'],
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'status' => 'pending',
                    'is_verified_buyer' => $verified ? 1 : 0,
                    'ip' => $ip,
                ]);
                foreach ($saved as $path) {
                    Database::insert('product_review_images', ['review_id' => $id, 'path' => $path]);
                }
                return $id;
            });
        } catch (\Throwable $e) {
            foreach ($saved as $path) {
                Uploader::delete($path);
            }
            if ($e instanceof \PDOException && str_contains($e->getMessage(), 'uq_review_once')) {
                throw new HttpException(409, 'شما قبلاً برای این محصول نظر ثبت کرده‌اید.');
            }
            throw $e;
        }
        NotificationService::admins('review.new', 'نظر جدید در انتظار تأیید', mb_substr($data['title'], 0, 120), '#/reviews?status=pending');
        return ['id' => $id, 'status' => 'pending', 'verified_buyer' => $verified];
    }

    private static function normaliseFiles(mixed $field): array
    {
        if (!is_array($field) || !isset($field['name'])) {
            return [];
        }
        if (!is_array($field['name'])) {
            return $field['error'] === UPLOAD_ERR_NO_FILE ? [] : [$field];
        }
        $out = [];
        foreach ($field['name'] as $i => $name) {
            if (($field['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $name, 'type' => $field['type'][$i], 'tmp_name' => $field['tmp_name'][$i], 'error' => $field['error'][$i], 'size' => $field['size'][$i]];
        }
        return $out;
    }
}
