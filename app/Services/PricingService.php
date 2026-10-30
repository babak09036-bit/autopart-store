<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Bulk price changes by category, brand and vehicle.
 * The plan is always recomputed on the server (the preview is informational only), applied in one
 * transaction, and every changed price is stored in price_change_items so the batch can be undone.
 */
final class PricingService
{
    public const ROUNDING = [0, 100, 1000, 10000];
    private const MIN_PRICE = 1000;

    /**
     * scope: category_ids[] (children included), brand_ids[], vehicle_model_ids[], include_universal, status (published|all)
     * Dimensions combine with AND; values inside one dimension combine with OR.
     * @return int[] product ids
     */
    public static function productIds(array $scope): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($scope['status'] ?? 'published') === 'published') {
            $where[] = "p.status = 'published'";
        } else {
            $where[] = "p.status <> 'archived'";
        }
        $cats = self::ints($scope['category_ids'] ?? []);
        if ($cats) {
            $p2 = [];
            $sql = 'SELECT id FROM categories WHERE parent_id IN ' . Database::in('pc', $cats, $p2);
            $children = array_map('intval', array_column(Database::all($sql, $p2), 'id'));
            $all = array_values(array_unique(array_merge($cats, $children)));
            $where[] = 'EXISTS (SELECT 1 FROM product_categories x WHERE x.product_id = p.id AND x.category_id IN ' . Database::in('cat', $all, $params) . ')';
        }
        $brands = self::ints($scope['brand_ids'] ?? []);
        if ($brands) {
            $where[] = 'p.brand_id IN ' . Database::in('brand', $brands, $params);
        }
        $models = self::ints($scope['vehicle_model_ids'] ?? []);
        if ($models) {
            $fit = 'EXISTS (SELECT 1 FROM product_vehicle_compat c WHERE c.product_id = p.id AND c.vehicle_model_id IN ' . Database::in('vm', $models, $params) . ')';
            $where[] = !empty($scope['include_universal']) ? '(' . $fit . ' OR p.is_universal_fit = 1)' : $fit;
        }
        return array_map('intval', array_column(Database::all('SELECT p.id FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id', $params), 'id'));
    }

    /** New value for one price under the rule; null stays null. */
    public static function calc(?float $old, array $rule): ?float
    {
        if ($old === null) {
            return null;
        }
        $value = (float) $rule['value'];
        $delta = $rule['type'] === 'percent' ? $old * $value / 100 : $value;
        $new = $rule['direction'] === 'decrease' ? $old - $delta : $old + $delta;
        $step = (int) ($rule['rounding'] ?? 0);
        if ($step > 0) {
            $new = round($new / $step) * $step;
        }
        return round($new, 2);
    }

    /**
     * Builds the change list for the given products.
     * @return array{rows: array, products: int, changes: int, skipped: int, sale_cleared: int}
     */
    public static function plan(array $productIds, array $rule, bool $lock = false): array
    {
        $rows = [];
        $skipped = $saleCleared = 0;
        if (!$productIds) {
            return ['rows' => [], 'products' => 0, 'changes' => 0, 'skipped' => 0, 'sale_cleared' => 0];
        }
        $params = [];
        $products = Database::all('SELECT id, name, price, sale_price FROM products WHERE id IN ' . Database::in('id', $productIds, $params) . ' ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), $params);
        $target = $rule['target'];
        foreach ($products as $p) {
            $oldPrice = (float) $p['price'];
            $oldSale = $p['sale_price'] !== null ? (float) $p['sale_price'] : null;
            $newPrice = $target === 'sale' ? $oldPrice : self::calc($oldPrice, $rule);
            $newSale = $target === 'price' ? $oldSale : self::calc($oldSale, $rule);
            $note = null;
            if ($newPrice < self::MIN_PRICE || ($newSale !== null && $newSale < self::MIN_PRICE)) {
                $skipped++;
                $rows[] = ['product_id' => (int) $p['id'], 'variant_id' => 0, 'name' => $p['name'], 'old_price' => $oldPrice, 'new_price' => $oldPrice,
                    'old_sale_price' => $oldSale, 'new_sale_price' => $oldSale, 'skip' => true, 'note' => 'قیمت جدید کمتر از ' . fa_number(self::MIN_PRICE) . ' تومان می‌شد؛ تغییر نکرد.'];
                continue;
            }
            if ($newSale !== null && $newSale >= $newPrice) {
                $newSale = null;
                $saleCleared++;
                $note = 'قیمت تخفیف‌دار از قیمت اصلی بیشتر یا برابر می‌شد؛ تخفیف برداشته شد.';
            }
            if ($newPrice !== $oldPrice || $newSale !== $oldSale) {
                $rows[] = ['product_id' => (int) $p['id'], 'variant_id' => 0, 'name' => $p['name'], 'old_price' => $oldPrice, 'new_price' => $newPrice,
                    'old_sale_price' => $oldSale, 'new_sale_price' => $newSale, 'skip' => false, 'note' => $note];
            }
        }
        // Variants with their own price follow the base price rule.
        if (!empty($rule['variants']) && $target !== 'sale') {
            $params = [];
            $variants = Database::all('SELECT v.id, v.product_id, v.name, v.price, p.name AS product_name FROM product_variants v JOIN products p ON p.id = v.product_id
                WHERE v.price IS NOT NULL AND v.product_id IN ' . Database::in('id', $productIds, $params) . ' ORDER BY v.product_id, v.sort_order' . ($lock ? ' FOR UPDATE' : ''), $params);
            foreach ($variants as $v) {
                $old = (float) $v['price'];
                $new = self::calc($old, $rule);
                if ($new < self::MIN_PRICE) {
                    $skipped++;
                    continue;
                }
                if ($new !== $old) {
                    $rows[] = ['product_id' => (int) $v['product_id'], 'variant_id' => (int) $v['id'], 'name' => $v['product_name'] . ' — ' . $v['name'],
                        'old_price' => $old, 'new_price' => $new, 'old_sale_price' => null, 'new_sale_price' => null, 'skip' => false, 'note' => null];
                }
            }
        }
        $changes = count(array_filter($rows, static fn ($r) => !$r['skip']));
        return ['rows' => $rows, 'products' => count($products), 'changes' => $changes, 'skipped' => $skipped, 'sale_cleared' => $saleCleared];
    }

    /** Applies the rule to the scope in one transaction and records the batch. */
    public static function apply(array $scope, array $rule, ?int $adminId, string $description): array
    {
        return Database::transaction(static function () use ($scope, $rule, $adminId, $description) {
            $ids = self::productIds($scope);
            $plan = self::plan($ids, $rule, true);
            $rows = array_values(array_filter($plan['rows'], static fn ($r) => !$r['skip']));
            if (!$rows) {
                throw new HttpException(422, 'با این شرایط قیمتی تغییر نمی‌کند.');
            }
            $batchId = Database::insert('price_change_batches', [
                'admin_user_id' => $adminId, 'description' => mb_substr($description, 0, 255),
                'rule_json' => json_encode(['scope' => $scope, 'rule' => $rule], JSON_UNESCAPED_UNICODE),
                'products_count' => count(array_unique(array_column($rows, 'product_id'))), 'items_count' => count($rows),
            ]);
            foreach ($rows as $r) {
                Database::insert('price_change_items', ['batch_id' => $batchId, 'product_id' => $r['product_id'], 'variant_id' => $r['variant_id'],
                    'old_price' => $r['old_price'], 'new_price' => $r['new_price'], 'old_sale_price' => $r['old_sale_price'], 'new_sale_price' => $r['new_sale_price']]);
                if ($r['variant_id']) {
                    Database::update('product_variants', ['price' => $r['new_price']], 'id = :id', ['id' => $r['variant_id']]);
                } else {
                    Database::update('products', ['price' => $r['new_price'], 'sale_price' => $r['new_sale_price']]
                        + ($r['new_sale_price'] === null ? ['sale_starts_at' => null, 'sale_ends_at' => null] : []), 'id = :id', ['id' => $r['product_id']]);
                }
            }
            return ['batch_id' => $batchId, 'changes' => count($rows), 'products' => count(array_unique(array_column($rows, 'product_id'))),
                'skipped' => $plan['skipped'], 'sale_cleared' => $plan['sale_cleared']];
        });
    }

    /**
     * Restores the old prices of a batch. A price edited after the batch is left alone (and reported),
     * so a later manual change is never overwritten.
     */
    public static function revert(int $batchId, ?int $adminId): array
    {
        return Database::transaction(static function () use ($batchId, $adminId) {
            $batch = Database::one('SELECT * FROM price_change_batches WHERE id = :id FOR UPDATE', ['id' => $batchId]);
            if (!$batch) {
                throw new HttpException(404, 'این تغییر گروهی یافت نشد.');
            }
            if ($batch['reverted_at']) {
                throw new HttpException(409, 'این تغییر قبلاً بازگردانده شده است.');
            }
            $restored = $kept = 0;
            foreach (Database::all('SELECT * FROM price_change_items WHERE batch_id = :b', ['b' => $batchId]) as $it) {
                if ((int) $it['variant_id']) {
                    $cur = Database::value('SELECT price FROM product_variants WHERE id = :id FOR UPDATE', ['id' => $it['variant_id']]);
                    if ($cur === null || (float) $cur !== (float) $it['new_price']) {
                        $kept++;
                        continue;
                    }
                    Database::update('product_variants', ['price' => $it['old_price']], 'id = :id', ['id' => $it['variant_id']]);
                } else {
                    $cur = Database::one('SELECT price, sale_price FROM products WHERE id = :id FOR UPDATE', ['id' => $it['product_id']]);
                    $same = $cur && (float) $cur['price'] === (float) $it['new_price']
                        && ($cur['sale_price'] === null ? $it['new_sale_price'] === null : $it['new_sale_price'] !== null && (float) $cur['sale_price'] === (float) $it['new_sale_price']);
                    if (!$same) {
                        $kept++;
                        continue;
                    }
                    Database::update('products', ['price' => $it['old_price'], 'sale_price' => $it['old_sale_price']], 'id = :id', ['id' => $it['product_id']]);
                }
                $restored++;
            }
            Database::update('price_change_batches', ['reverted_at' => now_utc(), 'reverted_by' => $adminId], 'id = :id', ['id' => $batchId]);
            return ['restored' => $restored, 'kept' => $kept, 'description' => $batch['description']];
        });
    }

    /** Human summary, e.g. «افزایش ۱۰٪ قیمت اصلی و تخفیف‌دار — دسته: فیلتر». */
    public static function describe(array $scope, array $rule): string
    {
        $amount = $rule['type'] === 'percent' ? fa_number((float) $rule['value']) . '٪' : fa_number((float) $rule['value']) . ' تومان';
        $target = ['both' => 'قیمت اصلی و تخفیف‌دار', 'price' => 'قیمت اصلی', 'sale' => 'قیمت تخفیف‌دار'][$rule['target']];
        $parts = [];
        foreach (['category_ids' => ['categories', 'name', 'دسته'], 'brand_ids' => ['brands', 'name', 'برند'], 'vehicle_model_ids' => ['vehicle_models', 'name', 'خودرو']] as $key => [$table, $col, $label]) {
            $ids = self::ints($scope[$key] ?? []);
            if ($ids) {
                $params = [];
                $names = array_column(Database::all('SELECT ' . Database::ident($col) . ' AS n FROM ' . Database::ident($table) . ' WHERE id IN ' . Database::in('i', $ids, $params), $params), 'n');
                $parts[] = $label . ': ' . implode('، ', $names);
            }
        }
        return ($rule['direction'] === 'decrease' ? 'کاهش ' : 'افزایش ') . $amount . ' ' . $target . ' — ' . ($parts ? implode(' | ', $parts) : 'همه محصولات');
    }

    private static function ints(mixed $list): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $list), static fn ($v) => $v > 0)));
    }
}
