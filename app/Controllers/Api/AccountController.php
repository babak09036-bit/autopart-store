<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\OrderService;

/** Everything here runs behind the "auth" middleware and is scoped to Auth::id() (ownership). */
final class AccountController extends Controller
{
    public function profile(): Response
    {
        $u = Auth::user();
        return $this->ok([
            'full_name' => $u['full_name'], 'mobile' => $u['mobile'], 'email' => $u['email'],
            'member_since' => fa_date($u['created_at']),
            'orders_count' => (int) Database::value('SELECT COUNT(*) FROM orders WHERE user_id = :u', ['u' => $u['id']]),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $id = (int) Auth::id();
        $data = $this->validate($request->all(), ['full_name' => 'required|string|minlen:3|maxlen:120', 'email' => 'nullable|email']);
        if (!empty($data['email']) && Database::value('SELECT 1 FROM users WHERE email = :e AND id <> :id', ['e' => $data['email'], 'id' => $id])) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', ['email' => 'این ایمیل قبلاً ثبت شده است.']);
        }
        Database::update('users', ['full_name' => $data['full_name'], 'email' => $data['email'] ?? null], 'id = :id', ['id' => $id]);
        return $this->ok(null, 'اطلاعات حساب به‌روزرسانی شد.');
    }

    public function changePassword(Request $request): Response
    {
        $id = (int) Auth::id();
        $data = $this->validate($request->all(), [
            'current_password' => 'required|string', 'password' => 'required|string|minlen:8|maxlen:128', 'password_confirmation' => 'required|string',
        ]);
        $hash = (string) Database::value('SELECT password_hash FROM users WHERE id = :id', ['id' => $id]);
        if (!password_verify($data['current_password'], $hash)) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', ['current_password' => 'رمز عبور فعلی درست نیست.']);
        }
        if ($data['password'] !== $data['password_confirmation']) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', ['password_confirmation' => 'تکرار رمز عبور با رمز عبور یکسان نیست.']);
        }
        Database::update('users', ['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT)], 'id = :id', ['id' => $id]);
        return $this->ok(null, 'رمز عبور تغییر کرد.');
    }

    /* ---------------- Addresses ---------------- */

    private const ADDRESS_RULES = [
        'title' => 'required|string|maxlen:60',
        'receiver_name' => 'required|string|minlen:3|maxlen:120',
        'receiver_mobile' => 'required|mobile',
        'province' => 'required|string|maxlen:60',
        'city' => 'required|string|maxlen:60',
        'postal_code' => 'nullable|regex:/^\d{10}$/',
        'address_line' => 'required|string|minlen:10|maxlen:500',
        'is_default' => 'nullable|bool',
    ];

    public function addresses(): Response
    {
        return $this->ok(Database::all('SELECT id, title, receiver_name, receiver_mobile, province, city, postal_code, address_line, is_default
            FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC', ['u' => Auth::id()]));
    }

    public function storeAddress(Request $request): Response
    {
        $data = $this->validate($request->all(), self::ADDRESS_RULES);
        $userId = (int) Auth::id();
        if ((int) Database::value('SELECT COUNT(*) FROM user_addresses WHERE user_id = :u', ['u' => $userId]) >= 10) {
            throw new HttpException(422, 'حداکثر ۱۰ نشانی قابل ثبت است.');
        }
        $data['is_default'] = !empty($data['is_default']) ? 1 : 0;
        if ($data['is_default']) {
            Database::query('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u', ['u' => $userId]);
        }
        $id = Database::insert('user_addresses', $data + ['user_id' => $userId]);
        return $this->ok(['id' => $id], 'نشانی ثبت شد.', 201);
    }

    public function updateAddress(Request $request): Response
    {
        $id = $this->ownedAddress($request);
        $data = $this->validate($request->all(), self::ADDRESS_RULES);
        $data['is_default'] = !empty($data['is_default']) ? 1 : 0;
        if ($data['is_default']) {
            Database::query('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u', ['u' => Auth::id()]);
        }
        Database::update('user_addresses', $data, 'id = :id AND user_id = :u', ['id' => $id, 'u' => Auth::id()]);
        return $this->ok(null, 'نشانی به‌روزرسانی شد.');
    }

    public function deleteAddress(Request $request): Response
    {
        $id = $this->ownedAddress($request);
        Database::query('DELETE FROM user_addresses WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => Auth::id()]);
        return $this->ok(null, 'نشانی حذف شد.');
    }

    private function ownedAddress(Request $request): int
    {
        $id = $this->intParam($request, 'id');
        if (!Database::value('SELECT 1 FROM user_addresses WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => Auth::id()])) {
            throw new HttpException(404, 'نشانی یافت نشد.');
        }
        return $id;
    }

    /* ---------------- Orders, reviews, notifications ---------------- */

    public function orders(): Response
    {
        return $this->ok(array_map([OrderService::class, 'publicOrder'],
            Database::all('SELECT * FROM orders WHERE user_id = :u ORDER BY placed_at DESC LIMIT 100', ['u' => Auth::id()])));
    }

    public function order(Request $request): Response
    {
        $order = Database::one('SELECT * FROM orders WHERE order_number = :n AND user_id = :u', ['n' => (string) $request->param('number'), 'u' => Auth::id()]);
        if (!$order) {
            throw new HttpException(404, 'سفارش یافت نشد.');
        }
        return $this->ok(OrderService::publicOrder($order, true));
    }

    public function reviews(): Response
    {
        $labels = ['pending' => 'در انتظار بررسی', 'approved' => 'منتشرشده', 'rejected' => 'ردشده'];
        return $this->ok(array_map(static fn ($r) => [
            'product' => ['title' => $r['name'], 'url' => 'products/' . $r['slug']], 'rating' => (int) $r['rating'], 'title' => $r['title'],
            'status' => $r['status'], 'status_label' => $labels[$r['status']], 'rejection_reason' => $r['status'] === 'rejected' ? $r['rejection_reason'] : null,
            'created_at_fa' => fa_date($r['created_at']),
        ], Database::all('SELECT r.rating, r.title, r.status, r.rejection_reason, r.created_at, p.name, p.slug FROM product_reviews r
            JOIN products p ON p.id = r.product_id WHERE r.user_id = :u ORDER BY r.created_at DESC', ['u' => Auth::id()])));
    }

    public function notifications(): Response
    {
        $userId = (int) Auth::id();
        $items = Database::all("SELECT id, title, body, link, read_at, created_at FROM notifications WHERE recipient_type = 'user' AND recipient_id = :u ORDER BY id DESC LIMIT 30", ['u' => $userId]);
        Database::query("UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE recipient_type = 'user' AND recipient_id = :u AND read_at IS NULL", ['u' => $userId]);
        return $this->ok(array_map(static fn ($n) => ['title' => $n['title'], 'body' => $n['body'], 'link' => $n['link'], 'unread' => $n['read_at'] === null, 'at' => fa_date($n['created_at'], true)], $items));
    }

    /* ---------------- Wishlist ---------------- */

    private function wishlistId(): int
    {
        $userId = (int) Auth::id();
        Database::query('INSERT IGNORE INTO wishlists (user_id) VALUES (:u)', ['u' => $userId]);
        return (int) Database::value('SELECT id FROM wishlists WHERE user_id = :u', ['u' => $userId]);
    }

    public function wishlist(): Response
    {
        $ids = array_map('intval', array_column(Database::all('SELECT product_id FROM wishlist_items WHERE wishlist_id = :w ORDER BY created_at DESC', ['w' => $this->wishlistId()]), 'product_id'));
        $items = $ids ? CatalogService::search(['ids' => $ids, 'keep_order' => true, 'per_page' => 60])['items'] : [];
        return $this->ok(['ids' => $ids, 'items' => $items]);
    }

    public function addWishlist(Request $request): Response
    {
        $data = $this->validate($request->all(), ['product_id' => 'required|int']);
        if (!CatalogService::find((int) $data['product_id'])) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        Database::query('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (:w, :p)', ['w' => $this->wishlistId(), 'p' => $data['product_id']]);
        return $this->ok(null, 'به علاقه‌مندی‌ها اضافه شد.');
    }

    public function removeWishlist(Request $request): Response
    {
        Database::query('DELETE FROM wishlist_items WHERE wishlist_id = :w AND product_id = :p', ['w' => $this->wishlistId(), 'p' => $this->intParam($request, 'productId')]);
        return $this->ok(null, 'از علاقه‌مندی‌ها حذف شد.');
    }

    /** Merges favourites saved in the browser before login. */
    public function syncWishlist(Request $request): Response
    {
        $ids = array_slice(array_values(array_filter((array) $request->input('ids', []), static fn ($v) => is_numeric($v))), 0, 100);
        $wishlist = $this->wishlistId();
        foreach ($ids as $id) {
            if (CatalogService::find((int) $id)) {
                Database::query('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (:w, :p)', ['w' => $wishlist, 'p' => (int) $id]);
            }
        }
        return $this->wishlist();
    }
}
