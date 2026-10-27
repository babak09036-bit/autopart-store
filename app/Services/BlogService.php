<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DOMDocument;
use DOMXPath;

final class BlogService
{
    /** Published posts, plus scheduled posts whose time has come (works even if the cron job is late). */
    public const LIVE = "bp.status IN ('published','scheduled') AND bp.published_at IS NOT NULL AND bp.published_at <= UTC_TIMESTAMP()";

    private const CARD = "SELECT bp.id, bp.title, bp.subtitle, bp.slug, bp.excerpt, bp.featured_image, bp.featured_image_alt, bp.reading_minutes,
        bp.published_at, bp.updated_at, bp.is_featured, bp.view_count, a.name AS author_name, a.slug AS author_slug,
        (SELECT bc.name FROM blog_post_categories bpc JOIN blog_categories bc ON bc.id = bpc.category_id WHERE bpc.post_id = bp.id ORDER BY bc.sort_order LIMIT 1) AS category_name,
        (SELECT bc.slug FROM blog_post_categories bpc JOIN blog_categories bc ON bc.id = bpc.category_id WHERE bpc.post_id = bp.id ORDER BY bc.sort_order LIMIT 1) AS category_slug
        FROM blog_posts bp LEFT JOIN blog_authors a ON a.id = bp.author_id";

    public static function card(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'subtitle' => $r['subtitle'],
            'slug' => $r['slug'],
            'url' => 'blog/' . $r['slug'],
            'excerpt' => $r['excerpt'],
            'image' => $r['featured_image'] ?: 'assets/images/ui/placeholder.svg',
            'image_alt' => $r['featured_image_alt'] ?: $r['title'],
            'reading_minutes' => (int) $r['reading_minutes'],
            'published_at' => $r['published_at'],
            'published_at_fa' => fa_date($r['published_at']),
            'author' => $r['author_name'] ? ['name' => $r['author_name'], 'url' => 'blog/author/' . $r['author_slug']] : null,
            'category' => $r['category_slug'] ? ['name' => $r['category_name'], 'url' => 'blog/category/' . $r['category_slug']] : null,
        ];
    }

    /** @param array $f page, per_page, category (slug), tag (slug), author (slug), q, featured, sort(new|popular), exclude[] */
    public static function list(array $f): array
    {
        $where = [self::LIVE];
        $params = [];
        if (!empty($f['category'])) {
            $where[] = 'EXISTS (SELECT 1 FROM blog_post_categories x JOIN blog_categories c ON c.id = x.category_id WHERE x.post_id = bp.id AND (c.slug = :cat OR c.parent_id = (SELECT id FROM blog_categories WHERE slug = :cat2)))';
            $params['cat'] = $f['category'];
            $params['cat2'] = $f['category'];
        }
        if (!empty($f['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM blog_post_tags x JOIN blog_tags t ON t.id = x.tag_id WHERE x.post_id = bp.id AND t.slug = :tag)';
            $params['tag'] = $f['tag'];
        }
        if (!empty($f['author'])) {
            $where[] = 'a.slug = :author';
            $params['author'] = $f['author'];
        }
        if (!empty($f['featured'])) {
            $where[] = 'bp.is_featured = 1';
        }
        if (!empty($f['exclude'])) {
            $where[] = 'bp.id NOT IN ' . Database::in('ex', array_map('intval', (array) $f['exclude']), $params);
        }
        if (!empty($f['q'])) {
            $q = '%' . addcslashes(trim((string) $f['q']), '%_\\') . '%';
            $where[] = '(bp.title LIKE :q1 OR bp.excerpt LIKE :q2 OR bp.content LIKE :q3)';
            $params += ['q1' => $q, 'q2' => $q, 'q3' => $q];
        }
        $order = ($f['sort'] ?? '') === 'popular' ? 'bp.view_count DESC, bp.published_at DESC' : 'bp.published_at DESC';
        $perPage = max(1, min(30, (int) ($f['per_page'] ?? 9)));
        $page = max(1, (int) ($f['page'] ?? 1));
        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM blog_posts bp LEFT JOIN blog_authors a ON a.id = bp.author_id' . $sqlWhere, $params);
        $rows = Database::all(self::CARD . $sqlWhere . " ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), $params);
        return [
            'items' => array_map([self::class, 'card'], $rows),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
        ];
    }

    public static function findLive(string $slug): ?array
    {
        return Database::one('SELECT bp.*, a.name AS author_name, a.slug AS author_slug, a.bio AS author_bio, a.avatar_path AS author_avatar, a.expertise AS author_expertise
            FROM blog_posts bp LEFT JOIN blog_authors a ON a.id = bp.author_id WHERE bp.slug = :s AND ' . self::LIVE, ['s' => $slug]);
    }

    /** Full article: content with heading ids, table of contents, taxonomy, related posts/products, comments. */
    public static function article(array $post): array
    {
        [$html, $toc] = self::withToc((string) $post['content']);
        $id = (int) $post['id'];
        $categories = Database::all('SELECT c.name, c.slug FROM blog_post_categories x JOIN blog_categories c ON c.id = x.category_id WHERE x.post_id = :id ORDER BY c.sort_order', ['id' => $id]);
        $tags = Database::all('SELECT t.name, t.slug FROM blog_post_tags x JOIN blog_tags t ON t.id = x.tag_id WHERE x.post_id = :id ORDER BY t.name', ['id' => $id]);

        $relatedIds = array_map('intval', array_column(Database::all('SELECT related_post_id FROM blog_post_relations WHERE post_id = :id', ['id' => $id]), 'related_post_id'));
        $related = [];
        if ($relatedIds) {
            $p = [];
            $related = array_map([self::class, 'card'], Database::all(self::CARD . ' WHERE ' . self::LIVE . ' AND bp.id IN ' . Database::in('r', $relatedIds, $p) . ' LIMIT 4', $p));
        }
        if (count($related) < 3 && $categories) {
            $more = self::list(['category' => $categories[0]['slug'], 'per_page' => 3 - count($related), 'exclude' => array_merge([$id], array_column($related, 'id'))])['items'];
            $related = array_merge($related, $more);
        }
        $productIds = array_map('intval', array_column(Database::all('SELECT product_id FROM blog_post_products WHERE post_id = :id', ['id' => $id]), 'product_id'));
        $products = $productIds ? CatalogService::search(['ids' => $productIds, 'keep_order' => true, 'per_page' => 8])['items'] : [];

        return [
            'html' => self::renderEmbeds($html),
            'toc' => $toc,
            'categories' => $categories,
            'tags' => $tags,
            'related' => $related,
            'products' => $products,
            'comments' => self::comments($id),
        ];
    }

    /** Adds unique ids to H2/H3 and returns [html, toc]. */
    public static function withToc(string $html): array
    {
        if (trim($html) === '') {
            return ['', []];
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        $toc = [];
        $used = [];
        foreach ((new DOMXPath($doc))->query('//h2|//h3') ?: [] as $h) {
            $text = trim($h->textContent);
            $base = slugify($text) ?: 'section';
            $slug = $base;
            for ($i = 2; isset($used[$slug]); $i++) {
                $slug = $base . '-' . $i;
            }
            $used[$slug] = true;
            $h->setAttribute('id', $slug);
            $toc[] = ['level' => strtolower($h->nodeName) === 'h2' ? 2 : 3, 'id' => $slug, 'text' => $text];
        }
        $root = $doc->getElementById('__root');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return [$out, $toc];
    }

    /** <div class="product-embed" data-product-id="12"></div> → a linked product box with live price. */
    private static function renderEmbeds(string $html): string
    {
        return (string) preg_replace_callback('#<div class="product-embed" data-product-id="(\d+)"></div>#', static function ($m) {
            $row = CatalogService::find((int) $m[1]);
            if (!$row) {
                return '';
            }
            $p = CatalogService::dto($row);
            return '<div class="product-embed"><a href="' . e(url($p['url'])) . '"><img src="' . e(url($p['image'])) . '" alt="' . e($p['imageAlt']) . '" width="120" height="120" loading="lazy">'
                . '<span><strong>' . e($p['title']) . '</strong><span>' . e(fa_number($p['price'])) . ' تومان</span></span></a></div>';
        }, $html);
    }

    public static function comments(int $postId): array
    {
        $rows = Database::all("SELECT id, parent_id, author_name, body, created_at, admin_user_id FROM blog_comments
            WHERE post_id = :p AND status = 'approved' ORDER BY created_at", ['p' => $postId]);
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int) ($r['parent_id'] ?? 0)][] = [
                'id' => (int) $r['id'], 'author' => $r['author_name'], 'body' => $r['body'],
                'is_official' => (bool) $r['admin_user_id'], 'at' => fa_date($r['created_at']),
            ];
        }
        $top = $byParent[0] ?? [];
        foreach ($top as &$c) {
            $c['replies'] = $byParent[$c['id']] ?? [];
        }
        return $top;
    }

    public static function readingMinutes(string $html): int
    {
        $words = count(preg_split('/\s+/u', HtmlSanitizer::toText($html)) ?: []);
        return max(1, (int) round($words / 200));
    }

    /** Cron: promote due scheduled posts to published. */
    public static function publishScheduled(): int
    {
        return Database::query("UPDATE blog_posts SET status = 'published' WHERE status = 'scheduled' AND published_at <= UTC_TIMESTAMP()")->rowCount();
    }
}
