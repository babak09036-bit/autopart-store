<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Server-side coupon validation and discount calculation. */
final class CouponService
{
    /**
     * @param array $lines cart lines: [product_id, line_total]
     * @return array{ok:bool,message:string,discount:float,coupon:?array}
     */
    public static function evaluate(?array $coupon, array $lines, float $subtotal, ?int $userId, ?string $mobile = null): array
    {
        $fail = static fn (string $m) => ['ok' => false, 'message' => $m, 'discount' => 0.0, 'coupon' => $coupon];
        if (!$coupon || !(int) $coupon['is_active']) {
            return $fail('کد تخفیف معتبر نیست.');
        }
        $now = now_utc();
        if ($coupon['starts_at'] && $coupon['starts_at'] > $now) {
            return $fail('زمان استفاده از این کد تخفیف هنوز شروع نشده است.');
        }
        if ($coupon['ends_at'] && $coupon['ends_at'] <= $now) {
            return $fail('مهلت استفاده از این کد تخفیف به پایان رسیده است.');
        }
        if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
            return $fail('ظرفیت استفاده از این کد تخفیف تمام شده است.');
        }
        if ($coupon['per_user_limit'] !== null) {
            if (!$userId && !$mobile) {
                return $fail('برای استفاده از این کد تخفیف وارد حساب کاربری شوید.');
            }
            $used = (int) Database::value(
                'SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = :c AND (' . ($userId ? 'user_id = :u' : '0 = 1') . ($mobile ? ' OR customer_mobile = :m' : '') . ')',
                array_filter(['c' => (int) $coupon['id'], 'u' => $userId, 'm' => $mobile], static fn ($v) => $v !== null)
            );
            if ($used >= (int) $coupon['per_user_limit']) {
                return $fail('سقف استفاده شما از این کد تخفیف پر شده است.');
            }
        }
        if ($subtotal < (float) $coupon['min_order_amount']) {
            return $fail('حداقل مبلغ سفارش برای این کد ' . fa_number($coupon['min_order_amount']) . ' تومان است.');
        }

        $eligible = self::eligibleTotal((int) $coupon['id'], $lines);
        if ($eligible <= 0) {
            return $fail('این کد تخفیف برای کالاهای سبد شما قابل استفاده نیست.');
        }
        $discount = $coupon['type'] === 'percent'
            ? $eligible * (float) $coupon['value'] / 100
            : min((float) $coupon['value'], $eligible);
        if ($coupon['max_discount_amount'] !== null) {
            $discount = min($discount, (float) $coupon['max_discount_amount']);
        }
        $discount = floor(min($discount, $subtotal));
        return ['ok' => true, 'message' => 'کد تخفیف اعمال شد.', 'discount' => $discount, 'coupon' => $coupon];
    }

    /** Sum of lines the coupon applies to (all lines when it has no product/category restriction). */
    private static function eligibleTotal(int $couponId, array $lines): float
    {
        $products = array_map('intval', array_column(Database::all('SELECT product_id FROM coupon_products WHERE coupon_id = :c', ['c' => $couponId]), 'product_id'));
        $categories = array_map('intval', array_column(Database::all('SELECT category_id FROM coupon_categories WHERE coupon_id = :c', ['c' => $couponId]), 'category_id'));
        if (!$products && !$categories) {
            return array_sum(array_column($lines, 'line_total'));
        }
        if ($categories) {
            $p = [];
            $children = array_map('intval', array_column(Database::all('SELECT id FROM categories WHERE parent_id IN ' . Database::in('c', $categories, $p), $p), 'id'));
            $categories = array_merge($categories, $children);
        }
        $total = 0.0;
        foreach ($lines as $line) {
            $pid = (int) $line['product_id'];
            $inCat = $categories && (bool) Database::value(
                'SELECT 1 FROM product_categories WHERE product_id = :p AND category_id IN (' . implode(',', array_map('intval', $categories)) . ') LIMIT 1', ['p' => $pid]);
            if (in_array($pid, $products, true) || $inCat) {
                $total += (float) $line['line_total'];
            }
        }
        return $total;
    }

    public static function findByCode(string $code, bool $forUpdate = false): ?array
    {
        return Database::one('SELECT * FROM coupons WHERE code = :c' . ($forUpdate ? ' FOR UPDATE' : ''), ['c' => mb_strtoupper(trim(to_en_digits($code)))]);
    }
}
