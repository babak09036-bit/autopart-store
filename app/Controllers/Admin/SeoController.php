<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Validators\Validator;

/** SEO overview and per-content SEO fields (seo_metadata). */
final class SeoController extends AdminController
{
    /** entity type → [table, title column, URL prefix] */
    private const ENTITIES = [
        'product' => ['products', 'name', 'products/'],
        'category' => ['categories', 'name', 'categories/'],
        'brand' => ['brands', 'name', 'brands/'],
        'page' => ['pages', 'title', 'pages/'],
        'post' => ['blog_posts', 'title', 'blog/'],
        'blog_category' => ['blog_categories', 'name', 'blog/category/'],
        'blog_tag' => ['blog_tags', 'name', 'blog/tag/'],
        'author' => ['blog_authors', 'name', 'blog/author/'],
    ];
    private const STATIC_ROUTES = ['home' => ['صفحه اصلی', ''], 'products' => ['همه محصولات', 'products'], 'blog' => ['صفحه اصلی وبلاگ', 'blog']];

    /** Lists content with its SEO status so missing titles/descriptions are easy to find. */
    public function index(Request $request): Response
    {
        $type = (string) $request->query('type', 'product');
        if ($type === 'static') {
            $items = [];
            foreach (self::STATIC_ROUTES as $key => [$label, $path]) {
                $seo = Database::one('SELECT seo_title, meta_description, is_indexable, canonical_url FROM seo_metadata WHERE route_key = :r', ['r' => $key]);
                $items[] = ['id' => $key, 'title' => $label, 'url' => $path, 'seo_title' => $seo['seo_title'] ?? null, 'meta_description' => $seo['meta_description'] ?? null,
                    'is_indexable' => $seo ? (int) $seo['is_indexable'] : 1, 'canonical_url' => $seo['canonical_url'] ?? null];
            }
            return $this->ok(['items' => $items, 'pagination' => ['page' => 1, 'pages' => 1, 'total' => count($items), 'per_page' => count($items)]]);
        }
        if (!isset(self::ENTITIES[$type])) {
            throw new HttpException(422, 'نوع محتوا معتبر نیست.');
        }
        [$table, $titleCol, $prefix] = self::ENTITIES[$type];
        $sql = 'SELECT e.id, e.' . Database::ident($titleCol) . ' AS title, e.slug, s.seo_title, s.meta_description, s.canonical_url, COALESCE(s.is_indexable, 1) AS is_indexable
            FROM ' . Database::ident($table) . " e LEFT JOIN seo_metadata s ON s.entity_type = :t AND s.entity_id = e.id WHERE 1 = 1";
        $params = ['t' => $type];
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND e.' . Database::ident($titleCol) . ' LIKE :q';
            $params['q'] = $this->like($q);
        }
        $missing = (string) $request->query('missing', '');
        if ($missing === 'title') {
            $sql .= " AND (s.seo_title IS NULL OR s.seo_title = '')";
        } elseif ($missing === 'description') {
            $sql .= " AND (s.meta_description IS NULL OR s.meta_description = '')";
        } elseif ($missing === 'noindex') {
            $sql .= ' AND s.is_indexable = 0';
        }
        $result = $this->paginate($sql, $params, $request, 'e.id DESC');
        $result['items'] = array_map(static fn ($r) => $r + ['url' => $prefix . $r['slug']], $result['items']);
        return $this->ok($result);
    }

    public function show(Request $request): Response
    {
        [$type, $id, $routeKey] = $this->target($request);
        $record = $routeKey ? Database::one('SELECT * FROM seo_metadata WHERE route_key = :r', ['r' => $routeKey])
            : Database::one('SELECT * FROM seo_metadata WHERE entity_type = :t AND entity_id = :id', ['t' => $type, 'id' => $id]);
        return $this->ok(['record' => $record, 'defaults' => $this->defaults($type, $id, $routeKey)]);
    }

    public function update(Request $request): Response
    {
        [$type, $id, $routeKey] = $this->target($request);
        $data = $this->validate($request->all(), [
            'seo_title' => 'nullable|string|maxlen:190', 'meta_description' => 'nullable|string|maxlen:320', 'canonical_url' => 'nullable|url|maxlen:500',
            'meta_robots' => 'nullable|regex:/^(no)?index, (no)?follow$/',
            'is_indexable' => 'nullable|bool', 'og_title' => 'nullable|string|maxlen:190', 'og_description' => 'nullable|string|maxlen:320', 'og_image' => 'nullable|path',
            'twitter_title' => 'nullable|string|maxlen:190', 'twitter_description' => 'nullable|string|maxlen:320', 'twitter_image' => 'nullable|path',
        ]);
        $data['is_indexable'] = !array_key_exists('is_indexable', $data) || $data['is_indexable'] ? 1 : 0;
        $where = $routeKey ? ['route_key' => $routeKey] : ['entity_type' => $type, 'entity_id' => $id];
        $existing = $routeKey ? Database::one('SELECT * FROM seo_metadata WHERE route_key = :r', ['r' => $routeKey])
            : Database::one('SELECT * FROM seo_metadata WHERE entity_type = :t AND entity_id = :id', ['t' => $type, 'id' => $id]);
        if ($existing) {
            Database::update('seo_metadata', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('seo_metadata', $data + $where + ($routeKey ? ['entity_type' => 'static'] : []));
        }
        ActivityLogger::admin('seo.update', $routeKey ? 'static' : $type, $id, 'تغییر تنظیمات سئو' . ($routeKey ? ' (' . $routeKey . ')' : ''), ActivityLogger::diff($existing ?? [], $data));
        return $this->ok(null, 'تنظیمات سئو ذخیره شد.');
    }

    public function robots(): Response
    {
        return $this->ok(['extra' => (string) SettingsService::get('robots_extra', ''), 'preview' => (new \App\Controllers\Web\SeoController())->robotsText()]);
    }

    public function saveRobots(Request $request): Response
    {
        $data = $this->validate($request->all(), ['extra' => 'nullable|string|maxlen:3000']);
        $lines = array_filter(array_map('trim', explode("\n", (string) ($data['extra'] ?? ''))));
        foreach ($lines as $line) {
            if (!preg_match('/^(User-agent|Disallow|Allow|Crawl-delay):\s*[^\s<>]*$/i', $line)) {
                throw new HttpException(422, 'فقط دستورهای User-agent، Allow، Disallow و Crawl-delay مجاز است.', ['extra' => 'خط نامعتبر: ' . $line]);
            }
        }
        Database::query("UPDATE site_settings SET value = :v WHERE setting_key = 'robots_extra'", ['v' => implode("\n", $lines)]);
        SettingsService::forget();
        ActivityLogger::admin('seo.robots', 'settings', null, 'تغییر robots.txt', ['extra' => $lines]);
        return $this->ok(null, 'robots.txt ذخیره شد.');
    }

    private function target(Request $request): array
    {
        $type = (string) $request->param('type');
        $key = (string) $request->param('id');
        if ($type === 'static') {
            if (!isset(self::STATIC_ROUTES[$key])) {
                throw new HttpException(404, 'مسیر یافت نشد.');
            }
            return ['static', null, $key];
        }
        if (!isset(self::ENTITIES[$type]) || !ctype_digit($key)) {
            throw new HttpException(404, 'محتوا یافت نشد.');
        }
        [$table] = self::ENTITIES[$type];
        if (!Database::value('SELECT 1 FROM ' . Database::ident($table) . ' WHERE id = :id', ['id' => (int) $key])) {
            throw new HttpException(404, 'محتوا یافت نشد.');
        }
        return [$type, (int) $key, null];
    }

    /** What the page would use without overrides (for the Google preview). */
    private function defaults(string $type, ?int $id, ?string $routeKey): array
    {
        if ($routeKey) {
            [$label, $path] = self::STATIC_ROUTES[$routeKey];
            return ['title' => $label, 'description' => '', 'url' => absolute_url($path), 'image' => null, 'focus_keyword' => null];
        }
        [$table, $titleCol, $prefix] = self::ENTITIES[$type];
        $row = Database::one('SELECT * FROM ' . Database::ident($table) . ' WHERE id = :id', ['id' => $id]);
        $desc = $row['short_description'] ?? $row['excerpt'] ?? $row['description'] ?? $row['bio'] ?? '';
        $image = $row['featured_image'] ?? $row['image_path'] ?? $row['logo_path'] ?? $row['avatar_path'] ?? null;
        if ($type === 'product') {
            $image = Database::value('SELECT path FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order LIMIT 1', ['id' => $id]);
        }
        return ['title' => $row[$titleCol], 'description' => \App\Services\SeoService::excerpt((string) $desc, 160),
            'url' => absolute_url($prefix . $row['slug']), 'image' => $image, 'focus_keyword' => $row['focus_keyword'] ?? null];
    }
}
