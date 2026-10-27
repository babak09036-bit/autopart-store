<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Dynamic XML sitemaps: an index plus one sitemap per content type.
 * Only published, indexable, canonical URLs are listed; lastmod is the real last change.
 */
final class SitemapService
{
    private const SECTIONS = ['pages', 'products', 'categories', 'brands', 'posts', 'blog_categories', 'authors'];

    private static function settings(): array
    {
        return array_column(Database::all('SELECT section, is_enabled, changefreq, priority FROM sitemap_settings'), null, 'section');
    }

    /** Excludes items marked noindex or canonicalised elsewhere. */
    private static function indexableFilter(string $type, string $alias): string
    {
        return "NOT EXISTS (SELECT 1 FROM seo_metadata s WHERE s.entity_type = '{$type}' AND s.entity_id = {$alias}.id
            AND (s.is_indexable = 0 OR (s.canonical_url IS NOT NULL AND s.canonical_url <> '')))";
    }

    private static function rows(string $section): array
    {
        return match ($section) {
            'pages' => array_merge(
                [['loc' => '', 'lastmod' => Database::value('SELECT MAX(updated_at) FROM homepage_sections')], ['loc' => 'products', 'lastmod' => Database::value("SELECT MAX(COALESCE(updated_at, published_at)) FROM products WHERE status = 'published'")]],
                array_map(static fn ($r) => ['loc' => $r['slug'] === 'contact' ? 'contact' : 'pages/' . $r['slug'], 'lastmod' => $r['m']],
                    Database::all("SELECT p.slug, COALESCE(p.updated_at, p.published_at, p.created_at) AS m FROM pages p WHERE p.status = 'published' AND " . self::indexableFilter('page', 'p')))),
            'products' => array_map(static fn ($r) => ['loc' => 'products/' . $r['slug'], 'lastmod' => $r['m'], 'image' => $r['image']],
                Database::all("SELECT p.slug, COALESCE(p.updated_at, p.published_at) AS m,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS image
                    FROM products p WHERE p.status = 'published' AND " . self::indexableFilter('product', 'p') . ' ORDER BY p.id')),
            'categories' => array_map(static fn ($r) => ['loc' => 'categories/' . $r['slug'], 'lastmod' => $r['m']],
                Database::all('SELECT c.slug, COALESCE(c.updated_at, c.created_at) AS m FROM categories c WHERE c.is_active = 1 AND ' . self::indexableFilter('category', 'c')
                    . " AND EXISTS (SELECT 1 FROM product_categories pc JOIN products p ON p.id = pc.product_id AND p.status = 'published'
                        WHERE pc.category_id = c.id OR pc.category_id IN (SELECT ch.id FROM categories ch WHERE ch.parent_id = c.id))")),
            'brands' => array_map(static fn ($r) => ['loc' => 'brands/' . $r['slug'], 'lastmod' => $r['m']],
                Database::all('SELECT b.slug, COALESCE(b.updated_at, b.created_at) AS m FROM brands b WHERE b.is_active = 1 AND ' . self::indexableFilter('brand', 'b')
                    . " AND EXISTS (SELECT 1 FROM products p WHERE p.brand_id = b.id AND p.status = 'published')")),
            'posts' => array_map(static fn ($r) => ['loc' => 'blog/' . $r['slug'], 'lastmod' => $r['m'], 'image' => $r['featured_image']],
                Database::all('SELECT bp.slug, COALESCE(bp.updated_at, bp.published_at) AS m, bp.featured_image FROM blog_posts bp WHERE ' . BlogService::LIVE . ' AND ' . self::indexableFilter('post', 'bp'))),
            'blog_categories' => array_map(static fn ($r) => ['loc' => 'blog/category/' . $r['slug'], 'lastmod' => $r['m']],
                Database::all('SELECT c.slug, (SELECT MAX(COALESCE(bp.updated_at, bp.published_at)) FROM blog_post_categories x JOIN blog_posts bp ON bp.id = x.post_id WHERE x.category_id = c.id AND ' . BlogService::LIVE . ') AS m
                    FROM blog_categories c WHERE ' . self::indexableFilter('blog_category', 'c') . ' HAVING m IS NOT NULL')),
            'authors' => array_map(static fn ($r) => ['loc' => 'blog/author/' . $r['slug'], 'lastmod' => $r['m']],
                Database::all('SELECT a.slug, (SELECT MAX(COALESCE(bp.updated_at, bp.published_at)) FROM blog_posts bp WHERE bp.author_id = a.id AND ' . BlogService::LIVE . ') AS m
                    FROM blog_authors a WHERE ' . self::indexableFilter('author', 'a') . ' HAVING m IS NOT NULL')),
            default => [],
        };
    }

    public static function index(): string
    {
        $settings = self::settings();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::SECTIONS as $section) {
            if (isset($settings[$section]) && !(int) $settings[$section]['is_enabled']) {
                continue;
            }
            $rows = self::rows($section);
            if (!$rows) {
                continue;
            }
            $last = max(array_map(static fn ($r) => (string) ($r['lastmod'] ?? ''), $rows));
            $xml .= '  <sitemap><loc>' . self::x(absolute_url('sitemap-' . str_replace('_', '-', $section) . '.xml')) . '</loc>'
                . ($last ? '<lastmod>' . gmdate('c', (int) strtotime($last)) . '</lastmod>' : '') . "</sitemap>\n";
        }
        return $xml . '</sitemapindex>' . "\n";
    }

    public static function section(string $name): ?string
    {
        $section = str_replace('-', '_', $name);
        $settings = self::settings();
        if (!in_array($section, self::SECTIONS, true) || (isset($settings[$section]) && !(int) $settings[$section]['is_enabled'])) {
            return null;
        }
        $freq = $settings[$section]['changefreq'] ?? 'weekly';
        $priority = $settings[$section]['priority'] ?? '0.5';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach (self::rows($section) as $row) {
            $xml .= '  <url><loc>' . self::x(absolute_url($row['loc'])) . '</loc>'
                . (!empty($row['lastmod']) ? '<lastmod>' . gmdate('c', (int) strtotime((string) $row['lastmod'])) . '</lastmod>' : '')
                . '<changefreq>' . $freq . '</changefreq><priority>' . $priority . '</priority>'
                . (!empty($row['image']) ? '<image:image><image:loc>' . self::x(absolute_url($row['image'])) . '</image:loc></image:image>' : '')
                . "</url>\n";
        }
        return $xml . '</urlset>' . "\n";
    }

    /** XML-escape and percent-encode non-ASCII path characters. */
    private static function x(string $url): string
    {
        $encoded = preg_replace_callback('/[^\x21-\x7E]/u', static fn ($m) => rawurlencode($m[0]), $url) ?? $url;
        return htmlspecialchars($encoded, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
