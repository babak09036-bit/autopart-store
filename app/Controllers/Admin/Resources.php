<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Services\RedirectService;

/**
 * Definitions for simple admin resources served by ResourceController.
 *  fields  : validation rules; also the ONLY writable columns (mass-assignment guard)
 *  perm    : required permission (view and manage); 'view_perm' optionally relaxes reads
 *  search  : columns searched by ?q=     filters: ?field=value exact filters     sort: allowed sort columns
 *  slug    : [source field, URL prefix for automatic redirects or null]
 *  dates   : datetime columns (converted Tehran ↔ UTC)     html: sanitised HTML columns
 *  relations: pivot tables written from array fields     readonly: no create
 */
final class Resources
{
    public static function get(string $key): array
    {
        $all = self::all();
        if (!isset($all[$key])) {
            throw new HttpException(404, 'بخش مدیریتی یافت نشد.');
        }
        return $all[$key] + ['key' => $key, 'search' => [], 'filters' => [], 'sort' => ['id'], 'default_sort' => 'id DESC',
            'slug' => null, 'dates' => [], 'html' => [], 'relations' => [], 'readonly' => false, 'list' => null, 'before_save' => null, 'before_delete' => null];
    }

    public static function all(): array
    {
        return [
            'brands' => [
                'table' => 'brands', 'label' => 'برند', 'perm' => 'brands.manage', 'view_perm' => 'brands.manage|products.view',
                'fields' => ['name' => 'required|string|maxlen:120', 'slug' => 'nullable|slug', 'logo_path' => 'nullable|path', 'description' => 'nullable|string|maxlen:2000',
                    'is_active' => 'nullable|bool', 'sort_order' => 'nullable|int'],
                'search' => ['name', 'slug'], 'filters' => ['is_active'], 'sort' => ['id', 'name', 'sort_order'], 'default_sort' => 'sort_order ASC, name ASC',
                'slug' => ['name', 'brands'],
                'list' => "SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS products_count FROM brands b",
                'before_delete' => static function (array $row) {
                    if (Database::value('SELECT 1 FROM products WHERE brand_id = :id LIMIT 1', ['id' => $row['id']])) {
                        throw new HttpException(409, 'این برند محصول دارد؛ ابتدا محصولات را به برند دیگری منتقل کنید یا برند را غیرفعال کنید.');
                    }
                },
            ],
            'categories' => [
                'table' => 'categories', 'label' => 'دسته‌بندی', 'perm' => 'categories.manage', 'view_perm' => 'categories.manage|products.view',
                'fields' => ['name' => 'required|string|maxlen:120', 'short_name' => 'nullable|string|maxlen:60', 'slug' => 'nullable|slug', 'parent_id' => 'nullable|int',
                    'description' => 'nullable|string|maxlen:3000', 'image_path' => 'nullable|path', 'sort_order' => 'nullable|int', 'is_active' => 'nullable|bool'],
                'search' => ['name', 'slug'], 'filters' => ['is_active', 'parent_id'], 'sort' => ['id', 'name', 'sort_order'], 'default_sort' => 'sort_order ASC, name ASC',
                'slug' => ['name', 'categories'],
                'list' => "SELECT c.*, (SELECT par.name FROM categories par WHERE par.id = c.parent_id) AS parent_name,
                    (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) AS products_count FROM categories c",
                'before_save' => static function (array $data, ?array $row) {
                    if (!empty($data['parent_id']) && $row && (int) $data['parent_id'] === (int) $row['id']) {
                        throw new HttpException(422, 'دسته نمی‌تواند والد خودش باشد.', ['parent_id' => 'والد نامعتبر است.']);
                    }
                    if (!empty($data['parent_id']) && !Database::value('SELECT 1 FROM categories WHERE id = :id', ['id' => $data['parent_id']])) {
                        throw new HttpException(422, 'دسته والد یافت نشد.', ['parent_id' => 'والد نامعتبر است.']);
                    }
                    return $data;
                },
                'before_delete' => static function (array $row) {
                    if (Database::value('SELECT 1 FROM product_categories WHERE category_id = :id LIMIT 1', ['id' => $row['id']])) {
                        throw new HttpException(409, 'این دسته محصول دارد؛ ابتدا محصولات را منتقل کنید یا دسته را غیرفعال کنید.');
                    }
                },
            ],
            'vehicle-brands' => [
                'table' => 'vehicle_brands', 'label' => 'برند خودرو', 'perm' => 'vehicles.manage', 'view_perm' => 'vehicles.manage|products.view',
                'fields' => ['name' => 'required|string|maxlen:80', 'slug' => 'nullable|slug', 'sort_order' => 'nullable|int'],
                'search' => ['name'], 'sort' => ['id', 'name', 'sort_order'], 'default_sort' => 'sort_order ASC', 'slug' => ['name', null],
            ],
            'vehicle-models' => [
                'table' => 'vehicle_models', 'label' => 'مدل خودرو', 'perm' => 'vehicles.manage', 'view_perm' => 'vehicles.manage|products.view',
                'fields' => ['vehicle_brand_id' => 'required|int', 'name' => 'required|string|maxlen:80', 'slug' => 'nullable|slug',
                    'year_from' => 'required|int|min:1300|max:1500', 'year_to' => 'required|int|min:1300|max:1500', 'sort_order' => 'nullable|int'],
                'search' => ['name'], 'filters' => ['vehicle_brand_id'], 'sort' => ['id', 'name', 'sort_order'], 'default_sort' => 'vehicle_brand_id ASC, sort_order ASC', 'slug' => ['name', null],
                'list' => 'SELECT m.*, b.name AS brand_name FROM vehicle_models m JOIN vehicle_brands b ON b.id = m.vehicle_brand_id',
                'before_save' => static function (array $data) {
                    if (isset($data['year_from'], $data['year_to']) && $data['year_to'] < $data['year_from']) {
                        throw new HttpException(422, 'سال پایان باید بعد از سال شروع باشد.', ['year_to' => 'سال پایان نامعتبر است.']);
                    }
                    return $data;
                },
            ],
            'coupons' => [
                'table' => 'coupons', 'label' => 'کد تخفیف', 'perm' => 'coupons.manage',
                'fields' => ['code' => 'required|string|regex:/^[A-Za-z0-9_-]{3,40}$/', 'description' => 'nullable|string|maxlen:255', 'type' => 'required|in:percent,fixed',
                    'value' => 'required|numeric|min:1', 'min_order_amount' => 'nullable|numeric|min:0', 'max_discount_amount' => 'nullable|numeric|min:0',
                    'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'usage_limit' => 'nullable|int|min:1', 'per_user_limit' => 'nullable|int|min:1',
                    'is_active' => 'nullable|bool', 'product_ids' => 'nullable|array', 'category_ids' => 'nullable|array'],
                'search' => ['code', 'description'], 'filters' => ['is_active', 'type'], 'sort' => ['id', 'code', 'ends_at', 'used_count'], 'dates' => ['starts_at', 'ends_at'],
                'relations' => ['product_ids' => ['coupon_products', 'coupon_id', 'product_id'], 'category_ids' => ['coupon_categories', 'coupon_id', 'category_id']],
                'before_save' => static function (array $data) {
                    $data['code'] = strtoupper($data['code'] ?? '');
                    if (($data['type'] ?? '') === 'percent' && ($data['value'] ?? 0) > 100) {
                        throw new HttpException(422, 'درصد تخفیف نمی‌تواند بیش از ۱۰۰ باشد.', ['value' => 'حداکثر ۱۰۰٪.']);
                    }
                    if (!empty($data['starts_at']) && !empty($data['ends_at']) && $data['ends_at'] <= $data['starts_at']) {
                        throw new HttpException(422, 'تاریخ پایان باید بعد از تاریخ شروع باشد.', ['ends_at' => 'تاریخ پایان نامعتبر است.']);
                    }
                    $data['min_order_amount'] ??= 0;
                    return $data;
                },
            ],
            'banners' => [
                'table' => 'banners', 'label' => 'بنر', 'perm' => 'content.banners',
                'fields' => ['placement' => 'required|in:hero,promo,category,special_offer,product,blog,mobile', 'title' => 'required|string|maxlen:160',
                    'eyebrow' => 'nullable|string|maxlen:80', 'description' => 'nullable|string|maxlen:255', 'button_text' => 'nullable|string|maxlen:60',
                    'button_url' => 'nullable|url|maxlen:500', 'image_desktop' => 'required|path', 'image_mobile' => 'nullable|path', 'image_alt' => 'nullable|string|maxlen:255',
                    'bg_color' => 'nullable|color', 'text_theme' => 'required|in:light,dark', 'sort_order' => 'nullable|int', 'starts_at' => 'nullable|date',
                    'ends_at' => 'nullable|date', 'is_active' => 'nullable|bool'],
                'search' => ['title', 'description'], 'filters' => ['placement', 'is_active'], 'sort' => ['id', 'sort_order', 'title', 'starts_at'], 'default_sort' => 'placement ASC, sort_order ASC',
                'dates' => ['starts_at', 'ends_at'],
                'before_save' => static function (array $data) {
                    if (!empty($data['starts_at']) && !empty($data['ends_at']) && $data['ends_at'] <= $data['starts_at']) {
                        throw new HttpException(422, 'زمان پایان باید بعد از زمان شروع باشد.', ['ends_at' => 'زمان پایان نامعتبر است.']);
                    }
                    return $data;
                },
            ],
            'pages' => [
                'table' => 'pages', 'label' => 'صفحه', 'perm' => 'content.pages',
                'fields' => ['title' => 'required|string|maxlen:190', 'slug' => 'nullable|slug', 'excerpt' => 'nullable|string|maxlen:500', 'content' => 'nullable|string|maxlen:200000',
                    'featured_image' => 'nullable|path', 'template' => 'required|in:default,contact,faq', 'status' => 'required|in:draft,published'],
                'search' => ['title', 'slug'], 'filters' => ['status'], 'sort' => ['id', 'title', 'updated_at'], 'html' => ['content'], 'slug' => ['title', 'pages'],
                'before_save' => static function (array $data, ?array $row) {
                    if (($data['status'] ?? '') === 'published' && empty($row['published_at'])) {
                        $data['published_at'] = now_utc();
                    }
                    return $data;
                },
                'extra_fields' => ['published_at'],
            ],
            'faqs' => [
                'table' => 'faqs', 'label' => 'پرسش متداول', 'perm' => 'content.pages',
                'fields' => ['product_id' => 'nullable|int', 'question' => 'required|string|maxlen:255', 'answer' => 'required|string|maxlen:3000',
                    'sort_order' => 'nullable|int', 'is_active' => 'nullable|bool'],
                'search' => ['question'], 'filters' => ['is_active', 'product_id'], 'sort' => ['id', 'sort_order'], 'default_sort' => 'product_id IS NULL DESC, sort_order ASC',
                'list' => 'SELECT f.*, p.name AS product_name FROM faqs f LEFT JOIN products p ON p.id = f.product_id',
            ],
            'blog-categories' => [
                'table' => 'blog_categories', 'label' => 'دسته وبلاگ', 'perm' => 'blog.taxonomy', 'view_perm' => 'blog.taxonomy|blog.posts',
                'fields' => ['name' => 'required|string|maxlen:120', 'slug' => 'nullable|slug', 'parent_id' => 'nullable|int', 'description' => 'nullable|string|maxlen:3000',
                    'image_path' => 'nullable|path', 'sort_order' => 'nullable|int'],
                'search' => ['name', 'slug'], 'sort' => ['id', 'name', 'sort_order'], 'default_sort' => 'sort_order ASC', 'slug' => ['name', 'blog/category'],
                'list' => 'SELECT c.*, (SELECT COUNT(*) FROM blog_post_categories x WHERE x.category_id = c.id) AS posts_count FROM blog_categories c',
            ],
            'blog-tags' => [
                'table' => 'blog_tags', 'label' => 'برچسب', 'perm' => 'blog.taxonomy', 'view_perm' => 'blog.taxonomy|blog.posts',
                'fields' => ['name' => 'required|string|maxlen:80', 'slug' => 'nullable|slug'],
                'search' => ['name', 'slug'], 'sort' => ['id', 'name'], 'default_sort' => 'name ASC', 'slug' => ['name', 'blog/tag'],
                'list' => 'SELECT t.*, (SELECT COUNT(*) FROM blog_post_tags x WHERE x.tag_id = t.id) AS posts_count FROM blog_tags t',
            ],
            'blog-authors' => [
                'table' => 'blog_authors', 'label' => 'نویسنده', 'perm' => 'blog.taxonomy', 'view_perm' => 'blog.taxonomy|blog.posts',
                'fields' => ['name' => 'required|string|maxlen:120', 'slug' => 'nullable|slug', 'avatar_path' => 'nullable|path', 'bio' => 'nullable|string|maxlen:2000',
                    'expertise' => 'nullable|string|maxlen:190'],
                'search' => ['name'], 'sort' => ['id', 'name'], 'default_sort' => 'name ASC', 'slug' => ['name', 'blog/author'],
                'list' => 'SELECT a.*, (SELECT COUNT(*) FROM blog_posts bp WHERE bp.author_id = a.id) AS posts_count FROM blog_authors a',
            ],
            'redirects' => [
                'table' => 'redirects', 'label' => 'ریدایرکت', 'perm' => 'redirects.manage',
                'fields' => ['source_path' => 'required|string|maxlen:255', 'target_url' => 'required|url|maxlen:500', 'status_code' => 'required|in:301,302', 'is_active' => 'nullable|bool'],
                'search' => ['source_path', 'target_url'], 'filters' => ['is_active', 'status_code'], 'sort' => ['id', 'hits', 'source_path'],
                'before_save' => static function (array $data, ?array $row) {
                    $data['source_path'] = RedirectService::normalize($data['source_path']);
                    if ($data['source_path'] === '/' || str_starts_with($data['source_path'], '/api') || str_starts_with($data['source_path'], '/admin')) {
                        throw new HttpException(422, 'این مسیر قابل ریدایرکت نیست.', ['source_path' => 'مسیر مبدأ مجاز نیست.']);
                    }
                    RedirectService::assertNoLoop($data['source_path'], $data['target_url'], $row ? (int) $row['id'] : null);
                    $data['status_code'] = (int) $data['status_code'];
                    return $data;
                },
            ],
            'messages' => [
                'table' => 'contact_messages', 'label' => 'پیام', 'perm' => 'messages.manage', 'readonly' => true,
                'fields' => ['status' => 'required|in:new,read,archived'],
                'search' => ['name', 'contact', 'subject', 'message'], 'filters' => ['status'], 'sort' => ['id', 'created_at'],
                'before_save' => static function (array $data, ?array $row) {
                    if (($data['status'] ?? '') !== 'new' && $row && !$row['read_at']) {
                        $data['read_at'] = now_utc();
                    }
                    return $data;
                },
                'extra_fields' => ['read_at'],
            ],
            'newsletter' => [
                'table' => 'newsletter_subscribers', 'label' => 'مشترک خبرنامه', 'perm' => 'newsletter.view', 'readonly' => true,
                'fields' => ['status' => 'required|in:subscribed,unsubscribed'],
                'search' => ['email'], 'filters' => ['status'], 'sort' => ['id', 'created_at'],
            ],
            'questions' => [
                'table' => 'product_questions', 'label' => 'پرسش محصول', 'perm' => 'questions.manage', 'readonly' => true,
                'fields' => ['answer' => 'nullable|string|maxlen:3000', 'status' => 'required|in:pending,approved,rejected'],
                'search' => ['question', 'answer'], 'filters' => ['status', 'product_id'], 'sort' => ['id', 'created_at'],
                'list' => 'SELECT q.*, p.name AS product_name, u.full_name AS user_name FROM product_questions q JOIN products p ON p.id = q.product_id LEFT JOIN users u ON u.id = q.user_id',
                'before_save' => static function (array $data, ?array $row) {
                    if (!empty($data['answer']) && ($row['answer'] ?? null) !== $data['answer']) {
                        $data['answered_by'] = \App\Core\AdminAuth::id();
                        $data['answered_at'] = now_utc();
                    }
                    return $data;
                },
                'extra_fields' => ['answered_by', 'answered_at'],
            ],
            'sitemap' => [
                'table' => 'sitemap_settings', 'label' => 'تنظیمات سایت‌مپ', 'perm' => 'seo.manage', 'readonly' => true,
                'fields' => ['is_enabled' => 'nullable|bool', 'changefreq' => 'required|in:always,hourly,daily,weekly,monthly,yearly,never', 'priority' => 'required|numeric|min:0|max:1'],
                'sort' => ['id'], 'default_sort' => 'id ASC',
            ],
        ];
    }
}
