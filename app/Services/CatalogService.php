<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Read side of the catalogue: listing/filtering/search and product details. Only published products are public. */
final class CatalogService
{
    /** Price after an active, time-bound sale. */
    public const PRICE_SQL = "(CASE WHEN p.sale_price IS NOT NULL AND (p.sale_starts_at IS NULL OR p.sale_starts_at <= UTC_TIMESTAMP())
        AND (p.sale_ends_at IS NULL OR p.sale_ends_at > UTC_TIMESTAMP()) THEN p.sale_price ELSE p.price END)";

    /** Stock: sum of active variant rows when the product has variants, otherwise the product row. */
    public const STOCK_SQL = "(CASE WHEN EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1)
        THEN (SELECT COALESCE(SUM(i2.quantity), 0) FROM inventory i2 JOIN product_variants v2 ON v2.id = i2.variant_id AND v2.is_active = 1 WHERE i2.product_id = p.id)
        ELSE COALESCE((SELECT i0.quantity FROM inventory i0 WHERE i0.product_id = p.id AND i0.variant_id = 0), 0) END)";

    private const SELECT = "SELECT p.id, p.name, p.slug, p.product_type, p.price AS list_price, p.sale_price, p.status,
        p.is_featured, p.is_bestseller, p.is_new, p.is_amazing, p.is_universal_fit, p.rating_avg, p.rating_count, p.sales_count,
        p.published_at, p.updated_at, p.short_description,
        b.name AS brand_name, b.slug AS brand_slug,
        c.name AS category_name, c.short_name AS category_short, c.slug AS category_slug,
        (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image,
        (SELECT pi.alt FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_alt,
        " . self::PRICE_SQL . " AS final_price,
        " . self::STOCK_SQL . " AS stock
      FROM products p
      LEFT JOIN brands b ON b.id = p.brand_id
      LEFT JOIN product_categories pcp ON pcp.product_id = p.id AND pcp.is_primary = 1
      LEFT JOIN categories c ON c.id = pcp.category_id";

    public const SORTS = ['relevance', 'popular', 'best', 'new', 'cheap', 'expensive', 'discount'];

    /**
     * @param array $f q, cat[], brand[], min, max, vehicle, type, instock, discount, amazing, best, expert, new, ids[], exclude[], sort, page, per_page
     */
    public static function search(array $f, bool $withFacets = false): array
    {
        $where = ["p.status = 'published'"];
        $params = [];

        if (!empty($f['ids'])) {
            $where[] = 'p.id IN ' . Database::in('id', array_map('intval', $f['ids']), $params);
        }
        if (!empty($f['exclude'])) {
            $where[] = 'p.id NOT IN ' . Database::in('ex', array_map('intval', $f['exclude']), $params);
        }
        if (!empty($f['q'])) {
            $words = array_filter(preg_split('/\s+/u', trim((string) $f['q'])) ?: []);
            foreach (array_slice($words, 0, 6) as $i => $word) {
                $where[] = "p.search_text LIKE :q{$i}";
                $params["q{$i}"] = '%' . self::likeEscape(normalize_search($word)) . '%';
            }
            $params['qfull'] = '%' . self::likeEscape(normalize_search((string) $f['q'])) . '%';
        }
        if (!empty($f['cat'])) {
            $ids = self::categoryIdsWithChildren((array) $f['cat']);
            $where[] = $ids ? 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ' . Database::in('cat', $ids, $params) . ')' : '0 = 1';
        }
        if (!empty($f['brand'])) {
            $where[] = 'b.slug IN ' . Database::in('brand', (array) $f['brand'], $params);
        }
        if (!empty($f['vehicle'])) {
            $yearSql = '';
            if (!empty($f['year'])) {
                $yearSql = ' AND COALESCE(pvc.year_from, vm.year_from) <= :vy1 AND COALESCE(pvc.year_to, vm.year_to) >= :vy2';
                $params['vy1'] = $params['vy2'] = (int) $f['year'];
            }
            $where[] = '(p.is_universal_fit = 1 OR EXISTS (SELECT 1 FROM product_vehicle_compat pvc JOIN vehicle_models vm ON vm.id = pvc.vehicle_model_id WHERE pvc.product_id = p.id AND vm.slug = :vehicle' . $yearSql . '))';
            $params['vehicle'] = (string) $f['vehicle'];
        }
        if (!empty($f['type'])) {
            $where[] = 'p.product_type = :ptype';
            $params['ptype'] = (string) $f['type'];
        }
        foreach (['amazing' => 'is_amazing', 'best' => 'is_bestseller', 'expert' => 'is_featured', 'new' => 'is_new'] as $key => $col) {
            if (!empty($f[$key])) {
                $where[] = "p.{$col} = 1";
            }
        }
        if (!empty($f['discount'])) {
            $where[] = self::PRICE_SQL . ' < p.price';
        }
        if (isset($f['min']) && $f['min'] !== '' && $f['min'] !== null) {
            $where[] = self::PRICE_SQL . ' >= :pmin';
            $params['pmin'] = (float) $f['min'];
        }
        if (isset($f['max']) && $f['max'] !== '' && $f['max'] !== null) {
            $where[] = self::PRICE_SQL . ' <= :pmax';
            $params['pmax'] = (float) $f['max'];
        }
        $having = !empty($f['instock']) ? ' HAVING stock > 0' : '';

        $sort = in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : (!empty($f['q']) ? 'relevance' : 'popular');
        if ($sort === 'relevance' && empty($f['q'])) {
            $sort = 'popular';
        }
        $order = match ($sort) {
            'relevance' => 'CASE WHEN p.search_text LIKE :qfull THEN 0 ELSE 1 END, p.sales_count DESC',
            'best' => 'p.is_bestseller DESC, p.sales_count DESC',
            'new' => 'p.is_new DESC, p.published_at DESC',
            'cheap' => 'final_price ASC',
            'expensive' => 'final_price DESC',
            'discount' => '(1 - final_price / p.price) DESC',
            default => 'p.is_bestseller DESC, (p.rating_avg * LN(p.rating_count + 1)) DESC, p.sales_count DESC',
        };
        if ($sort !== 'relevance') {
            unset($params['qfull']);
        }
        if (!empty($f['ids']) && !empty($f['keep_order'])) {
            $order = 'FIELD(p.id, ' . implode(',', array_map('intval', $f['ids'])) . ')';
        }

        $base = self::SELECT . ' WHERE ' . implode(' AND ', $where) . $having;
        $countParams = $params;
        unset($countParams['qfull']);
        $total = (int) Database::value('SELECT COUNT(*) FROM (' . $base . ') t', $countParams);

        $perPage = max(1, min(60, (int) ($f['per_page'] ?? 24)));
        $page = max(1, (int) ($f['page'] ?? 1));
        $rows = Database::all($base . ' ORDER BY stock > 0 DESC, ' . $order . ', p.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

        $result = [
            'items' => array_map([self::class, 'dto'], $rows),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
            'sort' => $sort,
        ];
        if ($withFacets) {
            $result['facets'] = self::facets();
        }
        return $result;
    }

    public static function facets(): array
    {
        return [
            'categories' => Database::all("SELECT c.slug, COALESCE(c.short_name, c.name) AS name,
                (SELECT par.slug FROM categories par WHERE par.id = c.parent_id) AS parent,
                (SELECT COUNT(DISTINCT pc.product_id) FROM product_categories pc JOIN products p ON p.id = pc.product_id AND p.status = 'published'
                  WHERE pc.category_id = c.id OR pc.category_id IN (SELECT ch.id FROM categories ch WHERE ch.parent_id = c.id)) AS count
                FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name"),
            'brands' => Database::all("SELECT b.slug, b.name, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.status = 'published') AS count
                FROM brands b WHERE b.is_active = 1 ORDER BY b.sort_order, b.name"),
            'price_max' => (float) (Database::value("SELECT MAX(p.price) FROM products p WHERE p.status = 'published'") ?? 0),
            'vehicles' => VehicleService::tree(),
        ];
    }

    /** Category slugs → ids including all descendants. */
    public static function categoryIdsWithChildren(array $slugs): array
    {
        $params = [];
        $ids = array_map('intval', array_column(Database::all('SELECT id FROM categories WHERE is_active = 1 AND slug IN ' . Database::in('s', $slugs, $params), $params), 'id'));
        $frontier = $ids;
        for ($depth = 0; $frontier && $depth < 5; $depth++) {
            $p = [];
            $children = array_map('intval', array_column(Database::all('SELECT id FROM categories WHERE is_active = 1 AND parent_id IN ' . Database::in('c', $frontier, $p), $p), 'id'));
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $frontier);
        }
        return $ids;
    }

    /** Public product shape shared by every listing and card. */
    public static function dto(array $r): array
    {
        $list = (float) $r['list_price'];
        $final = (float) $r['final_price'];
        $stock = (int) $r['stock'];
        return [
            'id' => (int) $r['id'],
            'slug' => $r['slug'],
            'title' => $r['name'],
            'url' => 'products/' . $r['slug'],
            'type' => $r['product_type'],
            'brand' => $r['brand_slug'] ? ['slug' => $r['brand_slug'], 'name' => $r['brand_name']] : null,
            'category' => $r['category_slug'] ? ['slug' => $r['category_slug'], 'name' => $r['category_short'] ?: $r['category_name']] : null,
            'price' => $final,
            'oldPrice' => $final < $list ? $list : null,
            'discountPercent' => $final < $list && $list > 0 ? (int) round((1 - $final / $list) * 100) : 0,
            'stock' => $stock,
            'inStock' => $stock > 0,
            'rating' => round((float) $r['rating_avg'], 1),
            'reviewsCount' => (int) $r['rating_count'],
            'image' => $r['image'] ?: 'assets/images/ui/placeholder.svg',
            'imageAlt' => $r['image_alt'] ?: $r['name'],
            'isNew' => (bool) $r['is_new'],
            'isBestseller' => (bool) $r['is_bestseller'],
            'isAmazing' => (bool) $r['is_amazing'],
            'isExpertPick' => (bool) $r['is_featured'],
            'universalFit' => (bool) $r['is_universal_fit'],
        ];
    }

    public static function find(int|string $idOrSlug, bool $publishedOnly = true): ?array
    {
        $col = is_int($idOrSlug) ? 'p.id = :key' : 'p.slug = :key';
        $row = Database::one(self::SELECT . ' WHERE ' . $col . ($publishedOnly ? " AND p.status = 'published'" : ''), ['key' => $idOrSlug]);
        return $row;
    }

    /** Full product for the details page. */
    public static function details(array $row): array
    {
        $id = (int) $row['id'];
        $product = self::dto($row);
        $full = Database::one('SELECT sku, gtin, short_description, description, sale_ends_at FROM products WHERE id = :id', ['id' => $id]);
        $product += [
            'sku' => $full['sku'],
            'gtin' => $full['gtin'],
            'shortDescription' => $full['short_description'],
            'description' => $full['description'],
            'saleEndsAt' => $product['oldPrice'] ? $full['sale_ends_at'] : null,
            'updatedAt' => $row['updated_at'] ?: $row['published_at'],
        ];
        $product['images'] = Database::all('SELECT path, alt FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order, id', ['id' => $id]);
        $product['attributes'] = Database::all('SELECT a.name, v.value FROM product_attribute_values v JOIN product_attributes a ON a.id = v.attribute_id WHERE v.product_id = :id ORDER BY v.sort_order, a.sort_order', ['id' => $id]);
        $product['variants'] = array_map(static function ($v) use ($product) {
            return [
                'id' => (int) $v['id'], 'name' => $v['name'], 'sku' => $v['sku'],
                'price' => $v['price'] !== null ? (float) $v['price'] : $product['price'],
                'stock' => (int) $v['stock'], 'color' => $v['color_hex'],
            ];
        }, Database::all('SELECT v.id, v.name, v.sku, v.price, v.color_hex, COALESCE(i.quantity, 0) AS stock FROM product_variants v
            LEFT JOIN inventory i ON i.product_id = v.product_id AND i.variant_id = v.id
            WHERE v.product_id = :id AND v.is_active = 1 ORDER BY v.sort_order, v.id', ['id' => $id]));
        $product['vehicles'] = self::fitments($id);
        $product['breadcrumbs'] = self::breadcrumbs($row['category_slug']);
        $product['faqs'] = Database::all('SELECT question, answer FROM faqs WHERE product_id = :id AND is_active = 1 ORDER BY sort_order', ['id' => $id]);
        $product['related'] = self::relatedProducts($id, 'related', $row['category_slug']);
        $product['complementary'] = self::relatedProducts($id, 'complementary', null);
        return $product;
    }

    /** Vehicles a product fits, with the effective production years (the product's own range, else the model's). */
    public static function fitments(int $productId): array
    {
        return array_map(static fn ($v) => [
            'slug' => $v['slug'], 'name' => $v['name'], 'brand' => $v['brand'], 'brand_slug' => $v['brand_slug'],
            'year_from' => (int) $v['year_from'], 'year_to' => (int) $v['year_to'], 'note' => $v['note'],
        ], Database::all('SELECT vm.slug, vm.name, vb.name AS brand, vb.slug AS brand_slug, pvc.note,
                COALESCE(pvc.year_from, vm.year_from) AS year_from, COALESCE(pvc.year_to, vm.year_to) AS year_to
            FROM product_vehicle_compat pvc JOIN vehicle_models vm ON vm.id = pvc.vehicle_model_id JOIN vehicle_brands vb ON vb.id = vm.vehicle_brand_id
            WHERE pvc.product_id = :id ORDER BY vb.sort_order, vb.name, vm.sort_order, vm.name', ['id' => $productId]));
    }

    /**
     * Whether a product fits one vehicle (model slug + optional production year).
     * status: fits | universal | no | year (model fits, year does not) | unknown (no fitment data recorded)
     */
    public static function fitment(array $product, string $modelSlug, ?int $year): array
    {
        $vehicle = Database::one('SELECT vm.slug, vm.name, vm.year_from, vm.year_to, vb.name AS brand FROM vehicle_models vm
            JOIN vehicle_brands vb ON vb.id = vm.vehicle_brand_id WHERE vm.slug = :s', ['s' => $modelSlug]);
        if (!$vehicle) {
            return ['status' => 'invalid', 'vehicle' => null, 'match' => null];
        }
        $label = $vehicle['name'] . ($year ? ' مدل ' . fa_digits((string) $year) : '');
        $vehicle = ['slug' => $vehicle['slug'], 'name' => $vehicle['name'], 'brand' => $vehicle['brand'], 'label' => $label, 'year' => $year];
        if (!empty($product['universalFit'])) {
            return ['status' => 'universal', 'vehicle' => $vehicle, 'match' => null];
        }
        $all = self::fitments((int) $product['id']);
        if (!$all) {
            return ['status' => 'unknown', 'vehicle' => $vehicle, 'match' => null];
        }
        $match = array_values(array_filter($all, static fn ($v) => $v['slug'] === $modelSlug))[0] ?? null;
        if (!$match) {
            return ['status' => 'no', 'vehicle' => $vehicle, 'match' => null];
        }
        if ($year && ($year < $match['year_from'] || $year > $match['year_to'])) {
            return ['status' => 'year', 'vehicle' => $vehicle, 'match' => $match];
        }
        return ['status' => 'fits', 'vehicle' => $vehicle, 'match' => $match];
    }

    public static function breadcrumbs(?string $categorySlug): array
    {
        $chain = [];
        $cat = $categorySlug ? Database::one('SELECT id, parent_id, name, short_name, slug FROM categories WHERE slug = :s', ['s' => $categorySlug]) : null;
        for ($i = 0; $cat && $i < 5; $i++) {
            array_unshift($chain, ['name' => $cat['short_name'] ?: $cat['name'], 'url' => 'categories/' . $cat['slug']]);
            $cat = $cat['parent_id'] ? Database::one('SELECT id, parent_id, name, short_name, slug FROM categories WHERE id = :id', ['id' => $cat['parent_id']]) : null;
        }
        return $chain;
    }

    /** Manually linked products first; "related" falls back to the same category. */
    public static function relatedProducts(int $productId, string $type, ?string $categorySlug, int $limit = 10): array
    {
        $ids = array_map('intval', array_column(Database::all(
            'SELECT related_product_id FROM product_relations WHERE product_id = :id AND type = :t ORDER BY sort_order',
            ['id' => $productId, 't' => $type]), 'related_product_id'));
        $items = $ids ? self::search(['ids' => $ids, 'keep_order' => true, 'per_page' => $limit])['items'] : [];
        if ($type === 'related' && count($items) < $limit && $categorySlug) {
            $more = self::search(['cat' => [$categorySlug], 'exclude' => array_merge([$productId], $ids), 'per_page' => $limit - count($items), 'sort' => 'popular'])['items'];
            $items = array_merge($items, $more);
        }
        return $items;
    }

    private static function likeEscape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /** Recomputes the normalised search column for one product. */
    public static function refreshSearchText(int $productId): void
    {
        $row = Database::one('SELECT p.name, p.product_type, p.short_description, b.name AS brand, b.slug AS brand_slug,
            (SELECT GROUP_CONCAT(c.name SEPARATOR " ") FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id) AS cats
            FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id = :id', ['id' => $productId]);
        if ($row) {
            $text = normalize_search(implode(' ', array_filter([$row['name'], $row['brand'], $row['brand_slug'], $row['product_type'], $row['cats'], $row['short_description']])));
            Database::query('UPDATE products SET search_text = :t WHERE id = :id', ['t' => $text, 'id' => $productId]);
        }
    }

    /** Average and count from approved reviews only. */
    public static function refreshRating(int $productId): void
    {
        Database::query("UPDATE products p SET
            rating_avg = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM product_reviews r WHERE r.product_id = p.id AND r.status = 'approved'), 0),
            rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.status = 'approved')
            WHERE p.id = :id", ['id' => $productId]);
    }
}
