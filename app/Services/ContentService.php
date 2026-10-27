<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Public site content: banners, menus, pages and resolved homepage sections. */
final class ContentService
{
    public const BANNER_PLACEMENTS = [
        'hero' => 'بنر اصلی (اسلایدر)', 'promo' => 'بنرهای تبلیغاتی میانی', 'category' => 'بنر دسته‌بندی',
        'special_offer' => 'بنر پیشنهاد ویژه', 'product' => 'بنر صفحه محصول', 'blog' => 'بنر وبلاگ', 'mobile' => 'بنر موبایل',
    ];

    /** Active banners inside their display window. */
    public static function banners(string $placement, int $limit = 10): array
    {
        $rows = Database::all("SELECT id, placement, title, eyebrow, description, button_text, button_url, image_desktop, image_mobile, image_alt, bg_color, text_theme
            FROM banners WHERE placement = :p AND is_active = 1
              AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())
            ORDER BY sort_order, id LIMIT {$limit}", ['p' => $placement]);
        return array_map(static fn ($b) => [
            'id' => (int) $b['id'], 'title' => $b['title'], 'eyebrow' => $b['eyebrow'], 'description' => $b['description'],
            'button_text' => $b['button_text'], 'button_url' => $b['button_url'],
            'image' => $b['image_desktop'], 'image_mobile' => $b['image_mobile'] ?: $b['image_desktop'],
            'alt' => $b['image_alt'] ?? '', 'bg_color' => $b['bg_color'], 'theme' => $b['text_theme'],
        ], $rows);
    }

    /** location → nested items (one level of children). */
    public static function menus(): array
    {
        $menus = [];
        $rows = Database::all('SELECT m.location, mi.id, mi.parent_id, mi.title, mi.url, mi.image_path, mi.is_highlight
            FROM menu_items mi JOIN menus m ON m.id = mi.menu_id
            WHERE m.is_active = 1 AND mi.is_active = 1 ORDER BY mi.sort_order, mi.id');
        $children = [];
        foreach ($rows as $r) {
            if ($r['parent_id']) {
                $children[(int) $r['parent_id']][] = ['title' => $r['title'], 'url' => $r['url']];
            }
        }
        foreach ($rows as $r) {
            if (!$r['parent_id']) {
                $menus[$r['location']][] = [
                    'title' => $r['title'], 'url' => $r['url'], 'image' => $r['image_path'],
                    'highlight' => (bool) $r['is_highlight'], 'children' => $children[(int) $r['id']] ?? [],
                ];
            }
        }
        return $menus;
    }

    public static function page(string $slug): ?array
    {
        return Database::one("SELECT id, title, slug, excerpt, content, featured_image, template, published_at, updated_at FROM pages WHERE slug = :s AND status = 'published'", ['s' => $slug]);
    }

    public static function faqs(): array
    {
        return Database::all('SELECT question, answer FROM faqs WHERE product_id IS NULL AND is_active = 1 ORDER BY sort_order, id');
    }

    /** Homepage sections in order, each with the data it needs. */
    public static function homepage(): array
    {
        $sections = Database::all('SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order, id');
        $out = [];
        foreach ($sections as $s) {
            $settings = $s['settings'] ? (json_decode($s['settings'], true) ?: []) : [];
            $items = Database::all('SELECT item_type, item_id, payload FROM homepage_section_items WHERE section_id = :id ORDER BY sort_order, id', ['id' => $s['id']]);
            $data = self::resolve($s['type'], $settings, $items);
            if ($data === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $s['id'],
                'type' => $s['type'],
                'title' => $s['title'],
                'subtitle' => $s['subtitle'],
                'bg_color' => $s['bg_color'],
                'settings' => array_intersect_key($settings, array_flip(['layout', 'link', 'action', 'columns', 'image', 'interval', 'body', 'items'])),
                'data' => $data,
            ];
        }
        return $out;
    }

    private static function productFilters(array $settings, array $items): array
    {
        $limit = max(1, min(24, (int) ($settings['limit'] ?? 12)));
        $source = $settings['source'] ?? 'latest';
        return match ($source) {
            'amazing' => ['amazing' => 1, 'per_page' => $limit, 'sort' => 'discount'],
            'bestseller' => ['best' => 1, 'per_page' => $limit, 'sort' => 'best'],
            'new' => ['new' => 1, 'per_page' => $limit, 'sort' => 'new'],
            'featured' => ['expert' => 1, 'per_page' => $limit],
            'discount' => ['discount' => 1, 'per_page' => $limit, 'sort' => 'discount'],
            'category' => ['cat' => [$settings['category'] ?? ''], 'per_page' => $limit],
            'manual' => ['ids' => array_map(static fn ($i) => (int) $i['item_id'], array_filter($items, static fn ($i) => $i['item_type'] === 'product')),
                'keep_order' => true, 'per_page' => 24],
            default => ['per_page' => $limit, 'sort' => 'new'],
        };
    }

    private static function resolve(string $type, array $settings, array $items): ?array
    {
        switch ($type) {
            case 'hero_slider':
            case 'promo_banners':
            case 'big_banner':
            case 'two_banners':
                $banners = self::banners($settings['banner_placement'] ?? ($type === 'hero_slider' ? 'hero' : 'promo'), $type === 'big_banner' ? 1 : ($type === 'two_banners' ? 2 : 10));
                return $banners ? ['banners' => $banners] : null;

            case 'quick_categories':
            case 'visual_categories':
                $ids = array_map(static fn ($i) => (int) $i['item_id'], array_filter($items, static fn ($i) => $i['item_type'] === 'category'));
                if (!$ids) {
                    return null;
                }
                $p = [];
                $cats = Database::all('SELECT id, name, short_name, slug, description, image_path FROM categories WHERE is_active = 1 AND id IN ' . Database::in('c', $ids, $p), $p);
                $byId = array_column($cats, null, 'id');
                $list = [];
                foreach ($items as $i) {
                    $c = $byId[(int) $i['item_id']] ?? null;
                    if (!$c) {
                        continue;
                    }
                    $payload = $i['payload'] ? (json_decode($i['payload'], true) ?: []) : [];
                    $list[] = ['name' => $c['short_name'] ?: $c['name'], 'full_name' => $c['name'], 'slug' => $c['slug'], 'url' => 'categories/' . $c['slug'],
                        'image' => $payload['image'] ?? $c['image_path'], 'description' => $payload['description'] ?? null];
                }
                return ['categories' => $list];

            case 'amazing':
            case 'ranked_products':
            case 'product_grid':
            case 'product_slider':
            case 'compact_grid':
            case 'mosaic':
                $products = CatalogService::search(self::productFilters($settings, $items))['items'];
                return $products ? ['products' => $products] : null;

            case 'discounted':
                $products = CatalogService::search(['discount' => 1, 'per_page' => (int) ($settings['limit'] ?? 12), 'sort' => 'discount'])['items'];
                return $products ? ['products' => $products, 'banner' => self::banners($settings['banner_placement'] ?? 'special_offer', 1)[0] ?? null] : null;

            case 'tabbed_products':
                $tabs = [];
                foreach ($settings['tabs'] ?? [] as $tab) {
                    $products = CatalogService::search(['cat' => [$tab['category'] ?? ''], 'per_page' => (int) ($settings['limit'] ?? 8)])['items'];
                    if ($products) {
                        $tabs[] = ['label' => $tab['label'] ?? '', 'products' => $products];
                    }
                }
                return $tabs ? ['tabs' => $tabs] : null;

            case 'brands':
                return ['brands' => Database::all("SELECT b.name, b.slug, b.logo_path AS logo, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.status = 'published') AS count
                    FROM brands b WHERE b.is_active = 1 ORDER BY b.sort_order, b.name")];

            case 'vehicle_selector':
                return ['vehicles' => VehicleService::tree()];

            case 'blog_posts':
                $posts = BlogService::list(['category' => ($settings['source'] ?? '') === 'category' ? ($settings['category'] ?? null) : null, 'per_page' => (int) ($settings['limit'] ?? 4)])['items'];
                return $posts ? ['posts' => $posts] : null;

            case 'testimonials':
                $list = [];
                foreach ($items as $i) {
                    $t = $i['payload'] ? (json_decode($i['payload'], true) ?: []) : [];
                    if (!$t) {
                        continue;
                    }
                    $product = !empty($t['product_id']) ? CatalogService::find((int) $t['product_id']) : null;
                    $list[] = ['name' => $t['name'] ?? '', 'city' => $t['city'] ?? '', 'avatar' => $t['avatar'] ?? null, 'rating' => (float) ($t['rating'] ?? 5),
                        'text' => $t['text'] ?? '', 'sample' => !empty($t['sample']), 'product' => $product ? ['title' => $product['name'], 'url' => 'products/' . $product['slug']] : null];
                }
                return $list ? ['testimonials' => $list] : null;

            case 'benefits':
            case 'newsletter':
            case 'text':
                return [];
        }
        return null;
    }
}
