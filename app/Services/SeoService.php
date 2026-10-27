<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Builds the <head> metadata and JSON-LD for every public page.
 * Values stored in seo_metadata override the defaults computed from the content itself.
 * Structured data only contains information that is visible on the page (no invented ratings, prices or authors).
 */
final class SeoService
{
    public const ENTITY_TYPES = ['product', 'category', 'brand', 'page', 'post', 'blog_category', 'blog_tag', 'author', 'static'];

    public static function record(string $entityType, ?int $entityId, ?string $routeKey = null): ?array
    {
        if ($routeKey !== null) {
            return Database::one('SELECT * FROM seo_metadata WHERE route_key = :r', ['r' => $routeKey]);
        }
        return Database::one('SELECT * FROM seo_metadata WHERE entity_type = :t AND entity_id = :id', ['t' => $entityType, 'id' => $entityId]);
    }

    /**
     * @param array $d defaults: title, description, path (canonical path), image, type (website|article|product), private (bool)
     */
    public static function meta(array $d, ?string $entityType = null, ?int $entityId = null, ?string $routeKey = null): array
    {
        $settings = SettingsService::all();
        $store = (string) ($settings['store_name'] ?? 'اتوپارت');
        $suffix = (string) ($settings['seo_title_suffix'] ?? (' | ' . $store));
        $rec = ($entityType || $routeKey) ? self::record((string) $entityType, $entityId, $routeKey) : null;

        $title = trim((string) ($rec['seo_title'] ?? '')) ?: trim((string) ($d['title'] ?? $store));
        if (!str_contains($title, $store)) {
            $title .= $suffix;
        }
        $description = trim((string) ($rec['meta_description'] ?? '')) ?: self::excerpt((string) ($d['description'] ?? ''), 160);
        $canonical = trim((string) ($rec['canonical_url'] ?? '')) ?: (string) ($d['path'] ?? '');
        $canonical = absolute_url($canonical);

        if (!empty($d['private'])) {
            $robots = 'noindex, nofollow';
        } elseif ($rec && !(int) $rec['is_indexable']) {
            $robots = 'noindex, follow';
        } else {
            $robots = trim((string) ($rec['meta_robots'] ?? '')) ?: (string) ($d['robots'] ?? 'index, follow');
        }
        $image = trim((string) ($rec['og_image'] ?? '')) ?: (string) ($d['image'] ?? ($settings['default_og_image'] ?? ''));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'og' => [
                'type' => $d['type'] ?? 'website',
                'title' => trim((string) ($rec['og_title'] ?? '')) ?: $title,
                'description' => trim((string) ($rec['og_description'] ?? '')) ?: $description,
                'image' => $image ? absolute_url($image) : null,
                'site_name' => $store,
            ],
            'twitter' => [
                'card' => $image ? 'summary_large_image' : 'summary',
                'title' => trim((string) ($rec['twitter_title'] ?? '')) ?: (trim((string) ($rec['og_title'] ?? '')) ?: $title),
                'description' => trim((string) ($rec['twitter_description'] ?? '')) ?: $description,
                'image' => trim((string) ($rec['twitter_image'] ?? '')) ? absolute_url((string) $rec['twitter_image']) : ($image ? absolute_url($image) : null),
            ],
            'jsonld' => $d['jsonld'] ?? [],
        ];
    }

    public static function excerpt(string $text, int $length): string
    {
        $text = HtmlSanitizer::toText($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space ? mb_substr($cut, 0, $space) : $cut, '،,.;: ') . '…';
    }

    /* ---------------- JSON-LD builders ---------------- */

    public static function organization(): array
    {
        $s = SettingsService::all();
        $same = array_values(array_filter([$s['social_instagram'] ?? '', $s['social_telegram'] ?? '', $s['social_linkedin'] ?? '', $s['social_x'] ?? ''],
            static fn ($u) => (bool) preg_match('#^https?://#', (string) $u)));
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $s['store_name'] ?? '',
            'url' => absolute_url(''),
            'logo' => !empty($s['logo']) ? absolute_url($s['logo']) : null,
            'email' => $s['email'] ?? null,
            'sameAs' => $same ?: null,
        ]);
    }

    public static function website(): array
    {
        $s = SettingsService::all();
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $s['store_name'] ?? '',
            'url' => absolute_url(''),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => absolute_url('search') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param array $items [['name' => ..., 'url' => relative path], ...] */
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => absolute_url($item['url'])];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    public static function product(array $p): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['title'],
            'image' => array_map(static fn ($i) => absolute_url($i['path']), $p['images'] ?: [['path' => $p['image']]]),
            'description' => self::excerpt((string) ($p['shortDescription'] ?: $p['description']), 300),
            'sku' => $p['sku'] ?: null,
            'gtin' => $p['gtin'] ?: null,
            'brand' => $p['brand'] ? ['@type' => 'Brand', 'name' => $p['brand']['name']] : null,
            'url' => absolute_url($p['url']),
            'offers' => array_filter([
                '@type' => 'Offer',
                'url' => absolute_url($p['url']),
                'priceCurrency' => 'IRR',
                // schema.org expects the ISO currency; prices are stored in Toman (×10 = Rial).
                'price' => number_format($p['price'] * 10, 0, '.', ''),
                'availability' => $p['inStock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'priceValidUntil' => $p['saleEndsAt'] ? substr($p['saleEndsAt'], 0, 10) : null,
            ]),
        ];
        // Only real, approved reviews produce an AggregateRating.
        if ($p['reviewsCount'] > 0) {
            $data['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $p['rating'], 'reviewCount' => $p['reviewsCount'], 'bestRating' => 5, 'worstRating' => 1];
        }
        return array_filter($data, static fn ($v) => $v !== null);
    }

    public static function article(array $post, string $url): array
    {
        $s = SettingsService::all();
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => mb_substr($post['title'], 0, 110),
            'description' => $post['excerpt'] ?: null,
            'image' => $post['featured_image'] ? [absolute_url($post['featured_image'])] : null,
            'datePublished' => gmdate('c', strtotime((string) $post['published_at'])),
            'dateModified' => gmdate('c', strtotime((string) ($post['updated_at'] ?: $post['published_at']))),
            'author' => $post['author_name'] ? ['@type' => 'Person', 'name' => $post['author_name'], 'url' => absolute_url('blog/author/' . $post['author_slug'])] : null,
            'publisher' => ['@type' => 'Organization', 'name' => $s['store_name'] ?? '', 'logo' => ['@type' => 'ImageObject', 'url' => absolute_url((string) ($s['logo'] ?? ''))]],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => absolute_url($url)],
            'wordCount' => count(preg_split('/\s+/u', HtmlSanitizer::toText((string) $post['content'])) ?: []),
        ]);
    }

    public static function person(array $author): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $author['name'],
            'description' => $author['bio'] ?: null,
            'jobTitle' => $author['expertise'] ?: null,
            'image' => $author['avatar_path'] ? absolute_url($author['avatar_path']) : null,
            'url' => absolute_url('blog/author/' . $author['slug']),
        ]);
    }

    /** CollectionPage with an ItemList of the visible items. */
    public static function collection(string $name, string $path, array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => absolute_url($path),
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => array_values(array_map(static fn ($item, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => absolute_url($item['url']), 'name' => $item['title']], $items, array_keys($items))),
            ],
        ];
    }

    public static function faqPage(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]], $faqs),
        ];
    }
}
