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

/** Homepage sections, menus and store settings. */
final class ContentController extends AdminController
{
    public const SECTION_TYPES = [
        'hero_slider' => 'اسلایدر بنر اصلی', 'quick_categories' => 'دسته‌بندی‌های سریع', 'amazing' => 'پیشنهاد شگفت‌انگیز',
        'promo_banners' => 'بنرهای تبلیغاتی', 'visual_categories' => 'دسته‌بندی تصویری', 'ranked_products' => 'فهرست رتبه‌دار محصولات',
        'brands' => 'برندها', 'product_grid' => 'گرید محصول', 'product_slider' => 'اسلایدر محصول', 'compact_grid' => 'گرید فشرده محصول',
        'mosaic' => 'محصول ویژه + گرید', 'tabbed_products' => 'محصولات زبانه‌ای', 'discounted' => 'محصولات تخفیف‌دار',
        'vehicle_selector' => 'انتخاب خودرو', 'big_banner' => 'بنر بزرگ', 'two_banners' => 'بنر دو ستونه', 'text' => 'محتوای متنی',
        'blog_posts' => 'مقالات وبلاگ', 'benefits' => 'مزایای فروشگاه', 'testimonials' => 'نظرات مشتریان', 'newsletter' => 'خبرنامه',
    ];

    /* ---------------- Homepage ---------------- */

    public function sections(): Response
    {
        $sections = Database::all('SELECT * FROM homepage_sections ORDER BY sort_order, id');
        foreach ($sections as &$s) {
            $s['settings'] = $s['settings'] ? json_decode($s['settings'], true) : new \stdClass();
            $s['type_label'] = self::SECTION_TYPES[$s['type']] ?? $s['type'];
            $s['items'] = array_map(static function ($i) {
                $i['payload'] = $i['payload'] ? json_decode($i['payload'], true) : null;
                return $i;
            }, Database::all('SELECT item_type, item_id, payload FROM homepage_section_items WHERE section_id = :id ORDER BY sort_order, id', ['id' => $s['id']]));
        }
        return $this->ok(['sections' => $sections, 'types' => self::SECTION_TYPES]);
    }

    public function saveSection(Request $request): Response
    {
        $id = $request->param('id') !== null ? $this->intParam($request, 'id') : null;
        $data = $this->validate($request->all(), [
            'type' => 'required|in:' . implode(',', array_keys(self::SECTION_TYPES)),
            'title' => 'nullable|string|maxlen:160',
            'subtitle' => 'nullable|string|maxlen:255',
            'bg_color' => 'nullable|color',
            'is_active' => 'nullable|bool',
            'settings' => 'nullable|array',
            'items' => 'nullable|array|max:60',
        ]);
        $settings = $this->cleanSettings((array) ($data['settings'] ?? []));
        $row = ['type' => $data['type'], 'title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null, 'bg_color' => $data['bg_color'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0, 'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)];
        $items = $this->cleanItems((array) ($data['items'] ?? []));
        $id = Database::transaction(static function () use ($id, $row, $items) {
            if ($id) {
                if (!Database::update('homepage_sections', $row, 'id = :id', ['id' => $id]) && !Database::value('SELECT 1 FROM homepage_sections WHERE id = :id', ['id' => $id])) {
                    throw new HttpException(404, 'سکشن یافت نشد.');
                }
            } else {
                $row['sort_order'] = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM homepage_sections');
                $id = Database::insert('homepage_sections', $row);
            }
            Database::query('DELETE FROM homepage_section_items WHERE section_id = :id', ['id' => $id]);
            foreach ($items as $i => $item) {
                Database::insert('homepage_section_items', $item + ['section_id' => $id, 'sort_order' => $i]);
            }
            return $id;
        });
        ActivityLogger::admin('content.homepage', 'homepage_section', $id, 'ذخیره سکشن صفحه اصلی: ' . ($row['title'] ?: self::SECTION_TYPES[$row['type']]), $row);
        return $this->ok(['id' => $id], 'سکشن ذخیره شد.');
    }

    public function deleteSection(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        Database::query('DELETE FROM homepage_sections WHERE id = :id', ['id' => $id]);
        ActivityLogger::admin('content.homepage', 'homepage_section', $id, 'حذف سکشن صفحه اصلی');
        return $this->ok(null, 'سکشن حذف شد.');
    }

    public function reorderSections(Request $request): Response
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), 'is_numeric'));
        Database::transaction(static function () use ($ids) {
            foreach ($ids as $i => $id) {
                Database::query('UPDATE homepage_sections SET sort_order = :s WHERE id = :id', ['s' => $i + 1, 'id' => (int) $id]);
            }
        });
        ActivityLogger::admin('content.homepage', 'homepage_section', null, 'تغییر ترتیب سکشن‌های صفحه اصلی', ['order' => $ids]);
        return $this->ok(null, 'ترتیب سکشن‌ها ذخیره شد.');
    }

    /** Only known setting keys with validated values are stored. */
    private function cleanSettings(array $s): array
    {
        $out = [];
        if (isset($s['source']) && in_array($s['source'], ['latest', 'amazing', 'bestseller', 'new', 'featured', 'discount', 'category', 'manual'], true)) {
            $out['source'] = $s['source'];
        }
        if (isset($s['limit'])) {
            $out['limit'] = max(1, min(24, (int) $s['limit']));
        }
        foreach (['category', 'banner_placement'] as $k) {
            if (!empty($s[$k]) && preg_match('/^[\p{L}\p{N}_-]{1,160}$/u', (string) $s[$k])) {
                $out[$k] = (string) $s[$k];
            }
        }
        if (!empty($s['link']) && Validator::isUrl((string) $s['link'])) {
            $out['link'] = (string) $s['link'];
        }
        if (!empty($s['image']) && Validator::isLocalPath((string) $s['image'])) {
            $out['image'] = (string) $s['image'];
        }
        foreach (['layout' => ['split', 'dark', 'default'], 'action' => ['cart', 'details']] as $k => $allowed) {
            if (isset($s[$k]) && in_array($s[$k], $allowed, true)) {
                $out[$k] = $s[$k];
            }
        }
        if (isset($s['columns'])) {
            $out['columns'] = max(2, min(6, (int) $s['columns']));
        }
        if (isset($s['interval'])) {
            $out['interval'] = max(2000, min(20000, (int) $s['interval']));
        }
        if (isset($s['body'])) {
            $out['body'] = \App\Services\HtmlSanitizer::clean((string) $s['body']);
        }
        if (isset($s['tabs']) && is_array($s['tabs'])) {
            $out['tabs'] = array_values(array_filter(array_map(static fn ($t) => is_array($t) && !empty($t['label']) && !empty($t['category'])
                ? ['label' => mb_substr((string) $t['label'], 0, 40), 'category' => mb_substr((string) $t['category'], 0, 160)] : null, array_slice($s['tabs'], 0, 6))));
        }
        if (isset($s['items']) && is_array($s['items'])) {
            $out['items'] = array_values(array_filter(array_map(static fn ($b) => is_array($b) && !empty($b['title']) ? [
                'icon' => preg_match('/^[a-z-]{2,30}$/', (string) ($b['icon'] ?? '')) ? $b['icon'] : 'check',
                'title' => mb_substr((string) $b['title'], 0, 80), 'text' => mb_substr((string) ($b['text'] ?? ''), 0, 200),
                'url' => !empty($b['url']) && Validator::isUrl((string) $b['url']) ? $b['url'] : null,
            ] : null, array_slice($s['items'], 0, 8))));
        }
        return $out;
    }

    private function cleanItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item) || !in_array($item['item_type'] ?? '', ['product', 'category', 'banner', 'brand', 'post', 'testimonial'], true)) {
                continue;
            }
            $payload = null;
            if (!empty($item['payload']) && is_array($item['payload'])) {
                $p = $item['payload'];
                $payload = array_filter([
                    'name' => isset($p['name']) ? mb_substr((string) $p['name'], 0, 80) : null,
                    'city' => isset($p['city']) ? mb_substr((string) $p['city'], 0, 60) : null,
                    'text' => isset($p['text']) ? mb_substr((string) $p['text'], 0, 600) : null,
                    'rating' => isset($p['rating']) ? max(1, min(5, (float) $p['rating'])) : null,
                    'product_id' => isset($p['product_id']) && ctype_digit((string) $p['product_id']) ? (int) $p['product_id'] : null,
                    'avatar' => !empty($p['avatar']) && Validator::isLocalPath((string) $p['avatar']) ? $p['avatar'] : null,
                    'image' => !empty($p['image']) && Validator::isLocalPath((string) $p['image']) ? $p['image'] : null,
                    'description' => isset($p['description']) ? mb_substr((string) $p['description'], 0, 200) : null,
                    // seeded placeholder testimonials stay labelled as samples on the storefront
                    'sample' => !empty($p['sample']) ? true : null,
                ], static fn ($v) => $v !== null);
            }
            $out[] = ['item_type' => $item['item_type'], 'item_id' => isset($item['item_id']) && ctype_digit((string) $item['item_id']) ? (int) $item['item_id'] : null,
                'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null];
        }
        return $out;
    }

    /* ---------------- Menus ---------------- */

    public function menus(): Response
    {
        $menus = Database::all('SELECT * FROM menus ORDER BY id');
        foreach ($menus as &$m) {
            $m['items'] = Database::all('SELECT * FROM menu_items WHERE menu_id = :id ORDER BY parent_id IS NOT NULL, sort_order, id', ['id' => $m['id']]);
        }
        return $this->ok($menus);
    }

    public function saveMenuItem(Request $request): Response
    {
        $id = $request->param('id') !== null ? $this->intParam($request, 'id') : null;
        $data = $this->validate($request->all(), [
            'menu_id' => 'required|int', 'parent_id' => 'nullable|int', 'title' => 'required|string|maxlen:120', 'url' => 'required|url|maxlen:500',
            'image_path' => 'nullable|path', 'is_highlight' => 'nullable|bool', 'sort_order' => 'nullable|int', 'is_active' => 'nullable|bool',
        ]);
        if (!Database::value('SELECT 1 FROM menus WHERE id = :id', ['id' => $data['menu_id']])) {
            throw new HttpException(422, 'منو یافت نشد.');
        }
        if (!empty($data['parent_id'])) {
            $parent = Database::one('SELECT menu_id, parent_id FROM menu_items WHERE id = :id', ['id' => $data['parent_id']]);
            if (!$parent || (int) $parent['menu_id'] !== (int) $data['menu_id'] || $parent['parent_id'] || ($id && (int) $data['parent_id'] === $id)) {
                throw new HttpException(422, 'والد انتخاب‌شده معتبر نیست (فقط یک سطح زیرمنو مجاز است).', ['parent_id' => 'والد نامعتبر است.']);
            }
        }
        $row = ['menu_id' => $data['menu_id'], 'parent_id' => $data['parent_id'] ?: null, 'title' => $data['title'], 'url' => $data['url'],
            'image_path' => $data['image_path'] ?? null, 'is_highlight' => !empty($data['is_highlight']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => !array_key_exists('is_active', $data) || $data['is_active'] ? 1 : 0];
        if ($id) {
            Database::update('menu_items', $row, 'id = :id', ['id' => $id]);
        } else {
            $id = Database::insert('menu_items', $row);
        }
        ActivityLogger::admin('content.menu', 'menu_item', $id, 'ذخیره آیتم منو: ' . $row['title'], $row);
        return $this->ok(['id' => $id], 'آیتم منو ذخیره شد.');
    }

    public function deleteMenuItem(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        Database::query('DELETE FROM menu_items WHERE id = :id', ['id' => $id]);
        ActivityLogger::admin('content.menu', 'menu_item', $id, 'حذف آیتم منو');
        return $this->ok(null, 'آیتم منو حذف شد.');
    }

    public function toggleMenu(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), ['is_active' => 'required|bool']);
        Database::update('menus', ['is_active' => $data['is_active'] ? 1 : 0], 'id = :id', ['id' => $id]);
        ActivityLogger::admin('content.menu', 'menu', $id, ($data['is_active'] ? 'فعال' : 'غیرفعال') . ' کردن منو');
        return $this->ok(null, 'منو ذخیره شد.');
    }

    /* ---------------- Settings ---------------- */

    public function settings(): Response
    {
        $rows = Database::all('SELECT setting_key, value, type, group_name, label, is_public FROM site_settings ORDER BY group_name, sort_order');
        return $this->ok($rows);
    }

    public function saveSettings(Request $request): Response
    {
        $input = (array) $request->input('settings', []);
        $known = array_column(Database::all('SELECT setting_key, type, value FROM site_settings'), null, 'setting_key');
        $errors = [];
        $changes = [];
        foreach ($input as $key => $value) {
            if (!isset($known[$key])) {
                continue;
            }
            $type = $known[$key]['type'];
            $value = is_string($value) ? trim(to_en_digits($value)) : $value;
            $clean = match ($type) {
                'int' => is_numeric($value) && $value >= 0 ? (string) (int) $value : null,
                'bool' => in_array($value, [true, false, 1, 0, '1', '0'], true) ? ($value ? '1' : '0') : null,
                'image' => $value === '' || Validator::isLocalPath((string) $value) ? (string) $value : null,
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? (string) $value : null,
                'json' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (json_decode((string) $value) !== null ? (string) $value : null),
                default => mb_strlen((string) $value) <= 3000 ? strip_tags((string) $value) : null,
            };
            if (str_starts_with($key, 'social_') && $clean !== '' && $clean !== null && !preg_match('#^https://#', $clean)) {
                $clean = null;
            }
            if ($clean === null) {
                $errors[$key] = 'مقدار وارد شده معتبر نیست.';
                continue;
            }
            if ($clean !== $known[$key]['value']) {
                $changes[$key] = [$known[$key]['value'], $clean];
                Database::query('UPDATE site_settings SET value = :v WHERE setting_key = :k', ['v' => $clean, 'k' => $key]);
            }
        }
        if ($errors) {
            throw new HttpException(422, 'برخی تنظیمات معتبر نیستند (لینک شبکه‌های اجتماعی باید با https شروع شود).', $errors);
        }
        SettingsService::forget();
        if ($changes) {
            ActivityLogger::admin('settings.update', 'settings', null, 'تغییر تنظیمات فروشگاه', $changes);
        }
        return $this->ok(null, 'تنظیمات ذخیره شد.');
    }
}
