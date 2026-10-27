<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Server-side cart. Logged-in users have one cart (carts.user_id); guests are identified by a random
 * cookie whose SHA-256 is stored (carts.token_hash). Prices are always read from the database.
 */
final class CartService
{
    private const COOKIE = 'ap_cart';

    public static function current(bool $create = false): ?array
    {
        $userId = Auth::id();
        if ($userId) {
            $cart = Database::one('SELECT * FROM carts WHERE user_id = :u', ['u' => $userId]);
            if (!$cart && $create) {
                Database::insert('carts', ['user_id' => $userId]);
                $cart = Database::one('SELECT * FROM carts WHERE user_id = :u', ['u' => $userId]);
            }
            return $cart;
        }
        $token = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $cart = Database::one('SELECT * FROM carts WHERE token_hash = :t AND user_id IS NULL', ['t' => hash('sha256', $token)]);
            if ($cart) {
                return $cart;
            }
        }
        if (!$create) {
            return null;
        }
        $token = random_token();
        Database::insert('carts', ['token_hash' => hash('sha256', $token)]);
        self::setCookie($token);
        return Database::one('SELECT * FROM carts WHERE token_hash = :t', ['t' => hash('sha256', $token)]);
    }

    private static function setCookie(string $token): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            $_COOKIE[self::COOKIE] = $token;
            return;
        }
        setcookie(self::COOKIE, $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => (Config::get('app.base_path') ?: '') . '/',
            'secure' => (bool) Config::get('app.session.secure') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
    }

    /** Moves a guest cart into the user's cart after login. */
    public static function mergeGuestCart(int $userId): void
    {
        $token = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return;
        }
        $guest = Database::one('SELECT * FROM carts WHERE token_hash = :t AND user_id IS NULL', ['t' => hash('sha256', $token)]);
        if (!$guest) {
            return;
        }
        Database::transaction(static function () use ($guest, $userId) {
            $userCart = Database::one('SELECT * FROM carts WHERE user_id = :u', ['u' => $userId]);
            if (!$userCart) {
                Database::query('UPDATE carts SET user_id = :u, token_hash = NULL WHERE id = :id', ['u' => $userId, 'id' => $guest['id']]);
                return;
            }
            foreach (Database::all('SELECT product_id, variant_id, quantity FROM cart_items WHERE cart_id = :c', ['c' => $guest['id']]) as $item) {
                Database::query('INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (:c, :p, :v, :q)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)',
                    ['c' => $userCart['id'], 'p' => $item['product_id'], 'v' => $item['variant_id'], 'q' => $item['quantity']]);
            }
            Database::query('DELETE FROM carts WHERE id = :id', ['id' => $guest['id']]);
        });
    }

    /** Current stock for a product (variant 0 = product level). */
    public static function stockOf(int $productId, int $variantId): int
    {
        return (int) (Database::value('SELECT quantity FROM inventory WHERE product_id = :p AND variant_id = :v', ['p' => $productId, 'v' => $variantId]) ?? 0);
    }

    private static function assertPurchasable(int $productId, int $variantId): array
    {
        $product = Database::one("SELECT id, name FROM products WHERE id = :id AND status = 'published'", ['id' => $productId]);
        if (!$product) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        $hasVariants = (bool) Database::value('SELECT 1 FROM product_variants WHERE product_id = :p AND is_active = 1 LIMIT 1', ['p' => $productId]);
        if ($hasVariants && !$variantId) {
            throw new HttpException(422, 'لطفاً نوع محصول را انتخاب کنید.', ['variant_id' => 'انتخاب نوع محصول الزامی است.']);
        }
        if ($variantId && !Database::value('SELECT 1 FROM product_variants WHERE id = :v AND product_id = :p AND is_active = 1', ['v' => $variantId, 'p' => $productId])) {
            throw new HttpException(422, 'نوع انتخاب‌شده معتبر نیست.');
        }
        return $product;
    }

    public static function add(int $productId, int $variantId, int $qty): array
    {
        $product = self::assertPurchasable($productId, $variantId);
        $cart = self::current(true);
        $existing = (int) (Database::value('SELECT quantity FROM cart_items WHERE cart_id = :c AND product_id = :p AND variant_id = :v',
            ['c' => $cart['id'], 'p' => $productId, 'v' => $variantId]) ?? 0);
        $stock = self::stockOf($productId, $variantId);
        if ($stock < 1) {
            throw new HttpException(409, 'این کالا در حال حاضر ناموجود است.');
        }
        if ($existing >= $stock) {
            throw new HttpException(409, 'بیشتر از موجودی انبار (' . fa_number($stock) . ' عدد) قابل سفارش نیست.');
        }
        $newQty = min($existing + $qty, $stock);
        Database::query('INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (:c, :p, :v, :q)
            ON DUPLICATE KEY UPDATE quantity = :q2',
            ['c' => $cart['id'], 'p' => $productId, 'v' => $variantId, 'q' => $newQty, 'q2' => $newQty]);
        return ['message' => '«' . $product['name'] . '» به سبد خرید اضافه شد.', 'adjusted' => $newQty < $existing + $qty];
    }

    public static function update(int $itemId, int $qty): void
    {
        $item = self::ownedItem($itemId);
        $stock = self::stockOf((int) $item['product_id'], (int) $item['variant_id']);
        if ($qty > $stock) {
            throw new HttpException(409, 'حداکثر موجودی این کالا ' . fa_number($stock) . ' عدد است.');
        }
        Database::query('UPDATE cart_items SET quantity = :q WHERE id = :id', ['q' => $qty, 'id' => $itemId]);
    }

    public static function remove(int $itemId): void
    {
        self::ownedItem($itemId);
        Database::query('DELETE FROM cart_items WHERE id = :id', ['id' => $itemId]);
    }

    /** Cart items are only reachable through the caller's own cart (ownership check). */
    private static function ownedItem(int $itemId): array
    {
        $cart = self::current();
        $item = $cart ? Database::one('SELECT * FROM cart_items WHERE id = :id AND cart_id = :c', ['id' => $itemId, 'c' => $cart['id']]) : null;
        if (!$item) {
            throw new HttpException(404, 'این کالا در سبد خرید شما نیست.');
        }
        return $item;
    }

    public static function applyCoupon(string $code): array
    {
        $cart = self::current(true);
        $summary = self::summary($cart);
        if (!$summary['items']) {
            throw new HttpException(422, 'سبد خرید خالی است.');
        }
        $coupon = CouponService::findByCode($code);
        $result = CouponService::evaluate($coupon, $summary['items'], $summary['totals']['subtotal'], Auth::id());
        if (!$result['ok']) {
            throw new HttpException(422, $result['message'], ['code' => $result['message']]);
        }
        Database::query('UPDATE carts SET coupon_id = :c WHERE id = :id', ['c' => $coupon['id'], 'id' => $cart['id']]);
        return $result;
    }

    public static function removeCoupon(): void
    {
        $cart = self::current();
        if ($cart) {
            Database::query('UPDATE carts SET coupon_id = NULL WHERE id = :id', ['id' => $cart['id']]);
        }
    }

    public static function clear(int $cartId): void
    {
        Database::query('DELETE FROM cart_items WHERE cart_id = :c', ['c' => $cartId]);
        Database::query('UPDATE carts SET coupon_id = NULL WHERE id = :c', ['c' => $cartId]);
    }

    /**
     * Lines and totals computed from current DB prices and stock.
     * Lines whose stock dropped are clamped; unavailable lines are flagged.
     */
    public static function summary(?array $cart = null, bool $lockStock = false): array
    {
        $cart ??= self::current();
        $empty = ['id' => null, 'items' => [], 'coupon' => null, 'notices' => [], 'totals' => self::totals(0, 0, 0)];
        if (!$cart) {
            return $empty;
        }
        $rows = Database::all('SELECT ci.id AS item_id, ci.product_id, ci.variant_id, ci.quantity,
                p.name, p.slug, p.status, p.price AS list_price, ' . CatalogService::PRICE_SQL . ' AS final_price,
                v.name AS variant_name, v.price AS variant_price, v.is_active AS variant_active,
                (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS image,
                b.name AS brand_name
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            LEFT JOIN product_variants v ON v.id = ci.variant_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE ci.cart_id = :c ORDER BY ci.id', ['c' => $cart['id']]);

        $items = [];
        $notices = [];
        $subtotal = 0.0;
        $subtotalOld = 0.0;
        foreach ($rows as $r) {
            $stock = (int) (Database::value('SELECT quantity FROM inventory WHERE product_id = :p AND variant_id = :v' . ($lockStock ? ' FOR UPDATE' : ''),
                ['p' => $r['product_id'], 'v' => $r['variant_id']]) ?? 0);
            $available = $r['status'] === 'published' && $stock > 0 && (!$r['variant_id'] || (int) $r['variant_active'] === 1);
            $qty = (int) $r['quantity'];
            if ($available && $qty > $stock) {
                $notices[] = 'تعداد «' . $r['name'] . '» به موجودی انبار (' . fa_number($stock) . ') کاهش یافت.';
                $qty = $stock;
                if (!$lockStock) {
                    Database::query('UPDATE cart_items SET quantity = :q WHERE id = :id', ['q' => $qty, 'id' => $r['item_id']]);
                }
            }
            if (!$available) {
                $notices[] = '«' . $r['name'] . '» در حال حاضر قابل خرید نیست و در جمع سفارش حساب نمی‌شود.';
            }
            $unit = $r['variant_price'] !== null ? (float) $r['variant_price'] : (float) $r['final_price'];
            $original = $r['variant_price'] !== null ? (float) $r['variant_price'] : (float) $r['list_price'];
            $line = $available ? $unit * $qty : 0.0;
            $subtotal += $line;
            $subtotalOld += $available ? $original * $qty : 0.0;
            $items[] = [
                'id' => (int) $r['item_id'],
                'product_id' => (int) $r['product_id'],
                'variant_id' => (int) $r['variant_id'],
                'variant_name' => $r['variant_name'],
                'title' => $r['name'],
                'url' => 'products/' . $r['slug'],
                'brand' => $r['brand_name'],
                'image' => $r['image'] ?: 'assets/images/ui/placeholder.svg',
                'quantity' => $qty,
                'stock' => $stock,
                'available' => $available,
                'unit_price' => $unit,
                'original_price' => $original,
                'line_total' => $line,
                'line_original' => $available ? $original * $qty : 0.0,
            ];
        }

        $couponInfo = null;
        $couponDiscount = 0.0;
        if ($cart['coupon_id']) {
            $coupon = Database::one('SELECT * FROM coupons WHERE id = :id', ['id' => $cart['coupon_id']]);
            $result = CouponService::evaluate($coupon, array_filter($items, static fn ($i) => $i['available']), $subtotal, Auth::id());
            if ($result['ok']) {
                $couponDiscount = $result['discount'];
                $couponInfo = ['code' => $coupon['code'], 'discount' => $couponDiscount, 'description' => $coupon['description']];
            } else {
                $notices[] = 'کد تخفیف حذف شد: ' . $result['message'];
                Database::query('UPDATE carts SET coupon_id = NULL WHERE id = :id', ['id' => $cart['id']]);
            }
        }

        $totals = self::totals($subtotal, $subtotalOld, $couponDiscount);
        $totals['count'] = array_sum(array_map(static fn ($i) => $i['available'] ? $i['quantity'] : 0, $items));
        return [
            'id' => (int) $cart['id'],
            'items' => $items,
            'coupon' => $couponInfo,
            'notices' => $notices,
            'totals' => $totals,
        ];
    }

    public static function totals(float $subtotal, float $subtotalOld, float $couponDiscount): array
    {
        $settings = SettingsService::all();
        $threshold = (float) ($settings['free_shipping_threshold'] ?? 0);
        $afterDiscount = max(0, $subtotal - $couponDiscount);
        $shipping = $subtotal <= 0 || ($threshold > 0 && $afterDiscount >= $threshold) ? 0.0 : (float) ($settings['shipping_cost'] ?? 0);
        $minOrder = (float) ($settings['min_order_amount'] ?? 0);
        return [
            'subtotal_original' => $subtotalOld,
            'product_discount' => max(0, $subtotalOld - $subtotal),
            'subtotal' => $subtotal,
            'coupon_discount' => $couponDiscount,
            'shipping' => $shipping,
            'grand_total' => $afterDiscount + $shipping,
            'free_shipping_threshold' => $threshold,
            'min_order_amount' => $minOrder,
            'meets_minimum' => $subtotal >= $minOrder,
            'count' => 0,
        ];
    }
}
