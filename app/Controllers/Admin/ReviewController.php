<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\CatalogService;
use App\Services\NotificationService;
use App\Services\Uploader;

final class ReviewController extends AdminController
{
    public function index(Request $request): Response
    {
        $sql = 'SELECT r.id, r.product_id, r.user_id, r.rating, r.title, r.body, r.status, r.is_verified_buyer, r.rejection_reason,
                r.admin_reply, r.admin_reply_visible, r.created_at, p.name AS product_name, u.full_name AS user_name,
                (SELECT COUNT(*) FROM product_review_images i WHERE i.review_id = r.id) AS images_count
            FROM product_reviews r JOIN products p ON p.id = r.product_id JOIN users u ON u.id = r.user_id WHERE 1 = 1';
        $params = [];
        if (in_array($s = (string) $request->query('status', ''), ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' AND r.status = :s';
            $params['s'] = $s;
        }
        foreach (['product_id' => 'r.product_id', 'user_id' => 'r.user_id', 'rating' => 'r.rating'] as $key => $col) {
            if (ctype_digit((string) $request->query($key, ''))) {
                $sql .= " AND {$col} = :{$key}";
                $params[$key] = (int) $request->query($key);
            }
        }
        if ($from = $request->query('from')) {
            $sql .= ' AND r.created_at >= :from';
            $params['from'] = gmdate('Y-m-d H:i:s', strtotime($from . ' 00:00 Asia/Tehran') ?: 0);
        }
        if ($to = $request->query('to')) {
            $sql .= ' AND r.created_at < :to';
            $params['to'] = gmdate('Y-m-d H:i:s', (strtotime($to . ' 00:00 Asia/Tehran') ?: time()) + 86400);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND (r.title LIKE :q1 OR r.body LIKE :q2 OR p.name LIKE :q3 OR u.full_name LIKE :q4)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q), 'q3' => $this->like($q), 'q4' => $this->like($q)];
        }
        $result = $this->paginate($sql, $params, $request, $this->order($request, ['created_at' => 'r.created_at', 'rating' => 'r.rating'], 'r.id DESC'));
        $result['items'] = array_map(static fn ($r) => $r + ['at' => fa_date($r['created_at'], true)], $result['items']);
        $result['counts'] = array_column(Database::all('SELECT status, COUNT(*) AS c FROM product_reviews GROUP BY status'), 'c', 'status');
        return $this->ok($result);
    }

    public function show(Request $request): Response
    {
        $r = $this->find($this->intParam($request, 'id'));
        $r['images'] = array_column(Database::all('SELECT path FROM product_review_images WHERE review_id = :id', ['id' => $r['id']]), 'path');
        $r['history'] = array_map(static fn ($h) => $h + ['at' => fa_date($h['created_at'], true)],
            Database::all("SELECT l.description, l.changes, l.created_at, a.full_name AS admin_name FROM admin_activity_logs l LEFT JOIN admin_users a ON a.id = l.admin_user_id
                WHERE l.entity_type = 'review' AND l.entity_id = :id ORDER BY l.id DESC", ['id' => $r['id']]));
        $r['at'] = fa_date($r['created_at'], true);
        return $this->ok($r);
    }

    /** Moderation, edit (history kept in the activity log) and official reply. */
    public function update(Request $request): Response
    {
        $this->requirePermission('reviews.manage');
        $review = $this->find($this->intParam($request, 'id'));
        $data = $this->validate($request->all(), [
            'status' => 'sometimes|in:pending,approved,rejected',
            'rejection_reason' => 'nullable|string|maxlen:255',
            'title' => 'sometimes|string|minlen:3|maxlen:120',
            'body' => 'sometimes|string|minlen:10|maxlen:2000',
            'admin_reply' => 'nullable|string|maxlen:2000',
            'admin_reply_visible' => 'nullable|bool',
        ]);
        if (($data['status'] ?? null) === 'rejected' && empty($data['rejection_reason']) && empty($review['rejection_reason'])) {
            throw new HttpException(422, 'دلیل رد نظر را بنویسید.', ['rejection_reason' => 'ثبت دلیل رد الزامی است.']);
        }
        $update = [];
        if (isset($data['status']) && $data['status'] !== $review['status']) {
            $update += ['status' => $data['status'], 'moderated_by' => AdminAuth::id(), 'moderated_at' => now_utc()];
        }
        if (array_key_exists('rejection_reason', $data)) {
            $update['rejection_reason'] = $data['rejection_reason'];
        }
        foreach (['title', 'body'] as $f) {
            if (isset($data[$f]) && $data[$f] !== $review[$f]) {
                $update[$f] = $data[$f];
                $update['edited_at'] = now_utc();
            }
        }
        if (array_key_exists('admin_reply', $data)) {
            $update['admin_reply'] = $data['admin_reply'] ?: null;
            $update['admin_reply_by'] = AdminAuth::id();
            $update['admin_reply_at'] = now_utc();
        }
        if (array_key_exists('admin_reply_visible', $data)) {
            $update['admin_reply_visible'] = $data['admin_reply_visible'] && !empty($update['admin_reply'] ?? $review['admin_reply']) ? 1 : 0;
        }
        if (!$update) {
            return $this->ok(null, 'تغییری برای ذخیره وجود نداشت.');
        }
        Database::transaction(static function () use ($review, $update) {
            Database::update('product_reviews', $update, 'id = :id', ['id' => $review['id']]);
            CatalogService::refreshRating((int) $review['product_id']);
        });
        $changes = ActivityLogger::diff($review, $update);
        unset($changes['moderated_at'], $changes['admin_reply_at'], $changes['edited_at']);
        $label = isset($update['status']) ? ['approved' => 'تأیید', 'rejected' => 'رد', 'pending' => 'بازگرداندن به انتظار'][$update['status']] . ' نظر' : 'ویرایش نظر';
        ActivityLogger::admin('review.' . ($update['status'] ?? 'edit'), 'review', (int) $review['id'], $label . ': ' . $review['title'], $changes);
        if (isset($update['status']) && $update['status'] !== 'pending') {
            NotificationService::user((int) $review['user_id'], 'review.status', 'نظر شما ' . ($update['status'] === 'approved' ? 'منتشر شد' : 'تأیید نشد'), $update['rejection_reason'] ?? null, 'account#reviews');
        }
        return $this->ok(null, 'نظر ذخیره شد.');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('reviews.manage');
        $review = $this->find($this->intParam($request, 'id'));
        $images = array_column(Database::all('SELECT path FROM product_review_images WHERE review_id = :id', ['id' => $review['id']]), 'path');
        Database::transaction(static function () use ($review) {
            Database::query('DELETE FROM product_reviews WHERE id = :id', ['id' => $review['id']]);
            CatalogService::refreshRating((int) $review['product_id']);
        });
        foreach ($images as $path) {
            Uploader::delete($path);
        }
        ActivityLogger::admin('review.delete', 'review', (int) $review['id'], 'حذف نظر: ' . $review['title'], ['title' => $review['title'], 'body' => $review['body'], 'rating' => $review['rating']]);
        return $this->ok(null, 'نظر حذف شد.');
    }

    private function find(int $id): array
    {
        $r = Database::one('SELECT r.*, p.name AS product_name, p.slug AS product_slug, u.full_name AS user_name FROM product_reviews r
            JOIN products p ON p.id = r.product_id JOIN users u ON u.id = r.user_id WHERE r.id = :id', ['id' => $id]);
        if (!$r) {
            throw new HttpException(404, 'نظر یافت نشد.');
        }
        return $r;
    }
}
