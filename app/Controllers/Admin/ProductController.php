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
use App\Services\HtmlSanitizer;
use App\Services\InventoryService;
use App\Services\RedirectService;
use App\Validators\Validator;

final class ProductController extends AdminController
{
    private const RULES = [
        'name' => 'required|string|maxlen:190',
        'slug' => 'nullable|slug',
        'brand_id' => 'nullable|int',
        'product_type' => 'nullable|string|maxlen:80',
        'sku' => 'nullable|string|regex:/^[A-Za-z0-9_.-]{1,64}$/',
        'gtin' => 'nullable|regex:/^\d{8,14}$/',
        'short_description' => 'nullable|string|maxlen:500',
        'description' => 'nullable|string|maxlen:200000',
        'price' => 'required|numeric|min:0',
        'sale_price' => 'nullable|numeric|min:0',
        'sale_starts_at' => 'nullable|date',
        'sale_ends_at' => 'nullable|date',
        'status' => 'required|in:draft,published,archived',
        'is_featured' => 'nullable|bool',
        'is_bestseller' => 'nullable|bool',
        'is_new' => 'nullable|bool',
        'is_amazing' => 'nullable|bool',
        'is_universal_fit' => 'nullable|bool',
        'stock' => 'nullable|int|min:0|max:1000000',
        'low_stock_threshold' => 'nullable|int|min:0|max:100000',
        'category_ids' => 'nullable|array',
        'primary_category_id' => 'nullable|int',
        'images' => 'nullable|array|max:12',
        'attributes' => 'nullable|array|max:40',
        'variants' => 'nullable|array|max:30',
        'vehicle_model_ids' => 'nullable|array',
        'related_ids' => 'nullable|array|max:20',
        'complementary_ids' => 'nullable|array|max:20',
    ];

    public function index(Request $request): Response
    {
        $sql = 'SELECT p.id, p.name, p.slug, p.sku, p.status, p.price, p.sale_price, p.is_featured, p.is_bestseller, p.is_new, p.is_amazing,
                p.rating_avg, p.rating_count, p.sales_count, p.updated_at, p.created_at, b.name AS brand_name,
                ' . CatalogService::PRICE_SQL . ' AS final_price, ' . CatalogService::STOCK_SQL . ' AS stock,
                COALESCE((SELECT low_stock_threshold FROM inventory i WHERE i.product_id = p.id AND i.variant_id = 0), 5) AS low_threshold,
                (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS image,
                (SELECT c.name FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id ORDER BY pc.is_primary DESC LIMIT 1) AS category_name
            FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE 1 = 1';
        $params = [];
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR p.slug LIKE :q3)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q), 'q3' => $this->like($q)];
        }
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));
        if ($ids) {
            $sql .= ' AND p.id IN ' . Database::in('ids', $ids, $params);
        }
        if (in_array($status = (string) $request->query('status', ''), ['draft', 'published', 'archived'], true)) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        if (ctype_digit((string) $request->query('brand_id', ''))) {
            $sql .= ' AND p.brand_id = :brand';
            $params['brand'] = (int) $request->query('brand_id');
        }
        if (ctype_digit((string) $request->query('category_id', ''))) {
            $sql .= ' AND EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = :cat)';
            $params['cat'] = (int) $request->query('category_id');
        }
        foreach (['is_featured', 'is_bestseller', 'is_new', 'is_amazing'] as $flag) {
            if ($request->query($flag) === '1') {
                $sql .= " AND p.{$flag} = 1";
            }
        }
        $stockFilter = (string) $request->query('stock', '');
        $wrapped = 'SELECT * FROM (' . $sql . ') t' . match ($stockFilter) {
            'out' => ' WHERE t.stock = 0',
            'low' => ' WHERE t.stock > 0 AND t.stock <= t.low_threshold',
            'in' => ' WHERE t.stock > 0',
            default => '',
        };
        $order = $this->order($request, ['id' => 't.id', 'name' => 't.name', 'price' => 't.final_price', 'stock' => 't.stock', 'sales' => 't.sales_count', 'updated' => 't.updated_at'], 't.id DESC');
        return $this->ok($this->paginate($wrapped, $params, $request, $order));
    }

    public function show(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $p = Database::one('SELECT * FROM products WHERE id = :id', ['id' => $id]);
        if (!$p) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        $inv = Database::one('SELECT quantity, low_stock_threshold FROM inventory WHERE product_id = :id AND variant_id = 0', ['id' => $id]);
        $p['stock'] = (int) ($inv['quantity'] ?? 0);
        $p['low_stock_threshold'] = (int) ($inv['low_stock_threshold'] ?? 5);
        $p['sale_starts_at_local'] = Validator::toLocalInput($p['sale_starts_at']);
        $p['sale_ends_at_local'] = Validator::toLocalInput($p['sale_ends_at']);
        $p['category_ids'] = array_map('intval', array_column(Database::all('SELECT category_id FROM product_categories WHERE product_id = :id', ['id' => $id]), 'category_id'));
        $p['primary_category_id'] = (int) (Database::value('SELECT category_id FROM product_categories WHERE product_id = :id AND is_primary = 1', ['id' => $id]) ?? 0) ?: null;
        $p['images'] = Database::all('SELECT path, alt, is_primary FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order, id', ['id' => $id]);
        $p['attributes'] = Database::all('SELECT a.name, v.value FROM product_attribute_values v JOIN product_attributes a ON a.id = v.attribute_id WHERE v.product_id = :id ORDER BY v.sort_order', ['id' => $id]);
        $p['variants'] = Database::all('SELECT v.id, v.name, v.sku, v.price, v.is_active, COALESCE(i.quantity, 0) AS stock FROM product_variants v
            LEFT JOIN inventory i ON i.product_id = v.product_id AND i.variant_id = v.id WHERE v.product_id = :id ORDER BY v.sort_order, v.id', ['id' => $id]);
        $p['vehicle_model_ids'] = array_map('intval', array_column(Database::all('SELECT vehicle_model_id FROM product_vehicle_compat WHERE product_id = :id', ['id' => $id]), 'vehicle_model_id'));
        $p['related_ids'] = array_map('intval', array_column(Database::all("SELECT related_product_id FROM product_relations WHERE product_id = :id AND type = 'related' ORDER BY sort_order", ['id' => $id]), 'related_product_id'));
        $p['complementary_ids'] = array_map('intval', array_column(Database::all("SELECT related_product_id FROM product_relations WHERE product_id = :id AND type = 'complementary' ORDER BY sort_order", ['id' => $id]), 'related_product_id'));
        $p['movements'] = array_map(static fn ($m) => $m + ['at' => fa_date($m['created_at'], true)], Database::all('SELECT change_qty, quantity_after, reason, note, created_at FROM inventory_movements WHERE product_id = :id ORDER BY id DESC LIMIT 15', ['id' => $id]));
        return $this->ok($p);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Database::transaction(function () use ($data) {
            $id = Database::insert('products', $this->columns($data, null));
            $this->saveRelations($id, $data, null);
            InventoryService::set($id, 0, (int) ($data['stock'] ?? 0), AdminAuth::id(), 'موجودی اولیه');
            Database::query('UPDATE inventory SET low_stock_threshold = :t WHERE product_id = :id AND variant_id = 0', ['t' => (int) ($data['low_stock_threshold'] ?? 5), 'id' => $id]);
            CatalogService::refreshSearchText($id);
            return $id;
        });
        ActivityLogger::admin('create', 'product', $id, 'ایجاد محصول: ' . $data['name'], ['price' => $data['price'], 'sale_price' => $data['sale_price'] ?? null, 'stock' => $data['stock'] ?? 0]);
        return $this->ok(['id' => $id], 'محصول ایجاد شد.', 201);
    }

    public function update(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $before = Database::one('SELECT * FROM products WHERE id = :id', ['id' => $id]);
        if (!$before) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        $data = $this->validated($request, $before);
        $cols = $this->columns($data, $before);
        Database::transaction(function () use ($id, $data, $cols, $before) {
            Database::update('products', $cols, 'id = :id', ['id' => $id]);
            $this->saveRelations($id, $data, $before);
            if (array_key_exists('stock', $data) && !$data['variants']) {
                InventoryService::set($id, 0, (int) $data['stock'], AdminAuth::id());
            }
            if (array_key_exists('low_stock_threshold', $data)) {
                Database::query('UPDATE inventory SET low_stock_threshold = :t WHERE product_id = :id AND variant_id = 0', ['t' => (int) $data['low_stock_threshold'], 'id' => $id]);
            }
            if ($cols['slug'] !== $before['slug']) {
                RedirectService::slugChanged('products', $before['slug'], $cols['slug']);
            }
            CatalogService::refreshSearchText($id);
        });
        $diff = ActivityLogger::diff($before, $cols);
        unset($diff['description'], $diff['search_text']);
        $priceChanged = isset($diff['price']) || isset($diff['sale_price']);
        ActivityLogger::admin($priceChanged ? 'price.change' : 'update', 'product', $id, ($priceChanged ? 'تغییر قیمت محصول: ' : 'ویرایش محصول: ') . $cols['name'], $diff);
        return $this->ok(null, 'محصول ذخیره شد.');
    }

    public function destroy(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $row = Database::one('SELECT id, name, slug FROM products WHERE id = :id', ['id' => $id]);
        if (!$row) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        if (Database::value('SELECT 1 FROM order_items WHERE product_id = :id LIMIT 1', ['id' => $id])) {
            // Keep order history intact: archive instead of deleting.
            Database::query("UPDATE products SET status = 'archived' WHERE id = :id", ['id' => $id]);
            ActivityLogger::admin('archive', 'product', $id, 'بایگانی محصول دارای سفارش: ' . $row['name']);
            return $this->ok(['archived' => true], 'این محصول در سفارش‌ها استفاده شده است؛ به جای حذف، بایگانی شد.');
        }
        Database::query('DELETE FROM products WHERE id = :id', ['id' => $id]);
        Database::query("DELETE FROM seo_metadata WHERE entity_type = 'product' AND entity_id = :id", ['id' => $id]);
        ActivityLogger::admin('delete', 'product', $id, 'حذف محصول: ' . $row['name'], $row);
        return $this->ok(null, 'محصول حذف شد.');
    }

    /** Quick inventory adjustment from the list. */
    public function adjustStock(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), ['variant_id' => 'nullable|int|min:0', 'change' => 'required|int|min:-100000|max:100000', 'note' => 'nullable|string|maxlen:255']);
        if (!Database::value('SELECT 1 FROM products WHERE id = :id', ['id' => $id])) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        $after = Database::transaction(fn () => InventoryService::adjust($id, (int) ($data['variant_id'] ?? 0), (int) $data['change'], 'adjustment', null, null, $data['note'] ?? null, AdminAuth::id()));
        ActivityLogger::admin('stock.change', 'product', $id, 'تغییر موجودی (' . fa_digits((string) $data['change']) . ')', ['change' => $data['change'], 'after' => $after]);
        return $this->ok(['stock' => $after], 'موجودی به‌روزرسانی شد.');
    }

    /** Toggle flags (featured / bestseller / new / amazing) or status from the list. */
    public function flags(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), ['is_featured' => 'sometimes|bool', 'is_bestseller' => 'sometimes|bool', 'is_new' => 'sometimes|bool', 'is_amazing' => 'sometimes|bool', 'status' => 'sometimes|in:draft,published,archived']);
        foreach (['is_featured', 'is_bestseller', 'is_new', 'is_amazing'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = $data[$f] ? 1 : 0;
            }
        }
        if (($data['status'] ?? null) === 'published') {
            Database::query('UPDATE products SET published_at = COALESCE(published_at, UTC_TIMESTAMP()) WHERE id = :id', ['id' => $id]);
        }
        Database::update('products', $data, 'id = :id', ['id' => $id]);
        ActivityLogger::admin('update', 'product', $id, 'تغییر وضعیت/برچسب محصول', $data);
        return $this->ok(null, 'ذخیره شد.');
    }

    private function validated(Request $request, ?array $before): array
    {
        $data = Validator::validate($request->all(), self::RULES);
        $errors = [];
        if (isset($data['sale_price']) && $data['sale_price'] !== null && $data['sale_price'] >= $data['price']) {
            $errors['sale_price'] = 'قیمت تخفیف باید کمتر از قیمت اصلی باشد.';
        }
        if (!empty($data['sale_starts_at']) && !empty($data['sale_ends_at']) && $data['sale_ends_at'] <= $data['sale_starts_at']) {
            $errors['sale_ends_at'] = 'پایان تخفیف باید بعد از شروع آن باشد.';
        }
        if (!empty($data['brand_id']) && !Database::value('SELECT 1 FROM brands WHERE id = :id', ['id' => $data['brand_id']])) {
            $errors['brand_id'] = 'برند انتخاب‌شده وجود ندارد.';
        }
        if (empty($data['category_ids'])) {
            $errors['category_ids'] = 'حداقل یک دسته‌بندی انتخاب کنید.';
        }
        if (!empty($data['sku']) && Database::value('SELECT 1 FROM products WHERE sku = :s' . ($before ? ' AND id <> :id' : ''), $before ? ['s' => $data['sku'], 'id' => $before['id']] : ['s' => $data['sku']])) {
            $errors['sku'] = 'این SKU برای محصول دیگری ثبت شده است.';
        }
        foreach ((array) ($data['images'] ?? []) as $i => $img) {
            if (!is_array($img) || !Validator::isLocalPath((string) ($img['path'] ?? ''))) {
                $errors['images'] = 'مسیر تصویر شماره ' . fa_digits($i + 1) . ' معتبر نیست.';
            }
        }
        foreach ((array) ($data['variants'] ?? []) as $i => $v) {
            if (!is_array($v) || trim((string) ($v['name'] ?? '')) === '' || (isset($v['price']) && $v['price'] !== '' && $v['price'] !== null && !is_numeric($v['price']))) {
                $errors['variants'] = 'اطلاعات تنوع شماره ' . fa_digits($i + 1) . ' کامل نیست.';
            }
        }
        if ($errors) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', $errors);
        }
        $slug = $data['slug'] ?: slugify($data['name']);
        $data['slug'] = $this->uniqueSlug($slug, $before ? (int) $before['id'] : null);
        $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        return $data;
    }

    private function uniqueSlug(string $slug, ?int $ignore): string
    {
        $candidate = $slug;
        for ($i = 2; Database::value('SELECT 1 FROM products WHERE slug = :s' . ($ignore ? ' AND id <> :id' : ''), $ignore ? ['s' => $candidate, 'id' => $ignore] : ['s' => $candidate]); $i++) {
            $candidate = $slug . '-' . $i;
        }
        return $candidate;
    }

    /** Whitelisted product columns. */
    private function columns(array $d, ?array $before): array
    {
        $cols = [
            'name' => $d['name'], 'slug' => $d['slug'], 'brand_id' => $d['brand_id'] ?: null, 'product_type' => $d['product_type'] ?? null,
            'sku' => $d['sku'] ?: null, 'gtin' => $d['gtin'] ?: null, 'short_description' => $d['short_description'] ?? null, 'description' => $d['description'],
            'price' => $d['price'], 'sale_price' => isset($d['sale_price']) && $d['sale_price'] !== null && $d['sale_price'] !== '' ? $d['sale_price'] : null,
            'sale_starts_at' => $d['sale_starts_at'] ?? null, 'sale_ends_at' => $d['sale_ends_at'] ?? null, 'status' => $d['status'],
        ];
        foreach (['is_featured', 'is_bestseller', 'is_new', 'is_amazing', 'is_universal_fit'] as $flag) {
            $cols[$flag] = !empty($d[$flag]) ? 1 : 0;
        }
        if ($d['status'] === 'published') {
            $cols['published_at'] = $before['published_at'] ?? now_utc();
        }
        return $cols;
    }

    private function saveRelations(int $id, array $d, ?array $before): void
    {
        // Categories
        Database::query('DELETE FROM product_categories WHERE product_id = :id', ['id' => $id]);
        $primary = (int) ($d['primary_category_id'] ?? 0) ?: (int) $d['category_ids'][0];
        foreach (array_unique(array_map('intval', $d['category_ids'])) as $cat) {
            if (Database::value('SELECT 1 FROM categories WHERE id = :c', ['c' => $cat])) {
                Database::insert('product_categories', ['product_id' => $id, 'category_id' => $cat, 'is_primary' => $cat === $primary ? 1 : 0]);
            }
        }
        // Images
        Database::query('DELETE FROM product_images WHERE product_id = :id', ['id' => $id]);
        foreach (array_values((array) ($d['images'] ?? [])) as $i => $img) {
            Database::insert('product_images', ['product_id' => $id, 'path' => $img['path'], 'alt' => mb_substr(trim((string) ($img['alt'] ?? '')), 0, 255) ?: null,
                'sort_order' => $i, 'is_primary' => $i === 0 ? 1 : 0]);
        }
        // Attributes (name → value)
        Database::query('DELETE FROM product_attribute_values WHERE product_id = :id', ['id' => $id]);
        foreach (array_values((array) ($d['attributes'] ?? [])) as $i => $attr) {
            $name = mb_substr(trim((string) ($attr['name'] ?? '')), 0, 120);
            $value = mb_substr(trim((string) ($attr['value'] ?? '')), 0, 255);
            if ($name === '' || $value === '') {
                continue;
            }
            Database::query('INSERT IGNORE INTO product_attributes (name) VALUES (:n)', ['n' => $name]);
            $attrId = (int) Database::value('SELECT id FROM product_attributes WHERE name = :n', ['n' => $name]);
            Database::query('INSERT IGNORE INTO product_attribute_values (product_id, attribute_id, value, sort_order) VALUES (:p, :a, :v, :s)', ['p' => $id, 'a' => $attrId, 'v' => $value, 's' => $i]);
        }
        // Variants (+ their stock)
        $keep = [];
        foreach (array_values((array) ($d['variants'] ?? [])) as $i => $v) {
            $row = ['name' => mb_substr(trim((string) $v['name']), 0, 120), 'sku' => trim((string) ($v['sku'] ?? '')) ?: null,
                'price' => isset($v['price']) && $v['price'] !== '' && $v['price'] !== null ? (float) to_en_digits((string) $v['price']) : null,
                'is_active' => !empty($v['is_active']) || !isset($v['is_active']) ? 1 : 0, 'sort_order' => $i];
            $vid = isset($v['id']) && ctype_digit((string) $v['id']) && Database::value('SELECT 1 FROM product_variants WHERE id = :v AND product_id = :p', ['v' => (int) $v['id'], 'p' => $id]) ? (int) $v['id'] : 0;
            if ($vid) {
                Database::update('product_variants', $row, 'id = :id', ['id' => $vid]);
            } else {
                $vid = Database::insert('product_variants', $row + ['product_id' => $id]);
            }
            $keep[] = $vid;
            if (isset($v['stock']) && is_numeric(to_en_digits((string) $v['stock']))) {
                InventoryService::set($id, $vid, max(0, (int) to_en_digits((string) $v['stock'])), AdminAuth::id());
            }
        }
        $params = ['id' => $id];
        Database::query('DELETE FROM product_variants WHERE product_id = :id' . ($keep ? ' AND id NOT IN ' . Database::in('k', $keep, $params) : ''), $params);
        $params = ['id' => $id];
        Database::query('DELETE FROM inventory WHERE product_id = :id AND variant_id > 0' . ($keep ? ' AND variant_id NOT IN ' . Database::in('k', $keep, $params) : ''), $params);
        // Vehicle compatibility
        Database::query('DELETE FROM product_vehicle_compat WHERE product_id = :id', ['id' => $id]);
        foreach (array_unique(array_map('intval', (array) ($d['vehicle_model_ids'] ?? []))) as $m) {
            Database::query('INSERT IGNORE INTO product_vehicle_compat (product_id, vehicle_model_id) SELECT :p, id FROM vehicle_models WHERE id = :m', ['p' => $id, 'm' => $m]);
        }
        // Related / complementary
        Database::query('DELETE FROM product_relations WHERE product_id = :id', ['id' => $id]);
        foreach (['related_ids' => 'related', 'complementary_ids' => 'complementary'] as $field => $type) {
            foreach (array_values(array_unique(array_map('intval', (array) ($d[$field] ?? [])))) as $i => $rid) {
                if ($rid !== $id) {
                    Database::query('INSERT IGNORE INTO product_relations (product_id, related_product_id, type, sort_order) SELECT :p, id, :t, :s FROM products WHERE id = :r',
                        ['p' => $id, 't' => $type, 's' => $i, 'r' => $rid]);
                }
            }
        }
    }
}
