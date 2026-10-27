<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\ContentService;
use App\Services\Payments\PaymentService;
use App\Services\SeoService;
use App\Services\SettingsService;

/** Server-rendered storefront pages. Data-heavy widgets are hydrated by the JS through the REST API. */
final class PageController
{
    /** Private pages are never indexed. */
    private const PRIVATE = ['robots' => 'noindex, nofollow', 'private' => true];

    public function render(string $page, string $template, array $data, array $seo, array $scripts = [], int $status = 200): Response
    {
        $user = Auth::user();
        $cart = CartService::current();
        $boot = [
            'csrf' => Csrf::token(),
            'base' => url(''),
            'user' => $user ? ['name' => $user['full_name']] : null,
            'settings' => SettingsService::all(true),
            'menus' => ContentService::menus(),
            'cartCount' => $cart ? (int) Database::value('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = :c', ['c' => $cart['id']]) : 0,
            'wishlist' => $user ? array_map('intval', array_column(Database::all('SELECT wi.product_id FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.user_id = :u', ['u' => $user['id']]), 'product_id')) : null,
            'paymentTestMode' => (bool) PaymentService::gateway()?->isTest(),
        ] + ($data['boot'] ?? []);
        $content = View::render('store/' . $template, $data);
        $html = View::render('layouts/store', ['seo' => $seo, 'page' => $page, 'content' => $content, 'scripts' => $scripts, 'boot' => $boot]);
        return Response::html($html, $status);
    }

    private function crumbs(array $items): array
    {
        return array_merge([['name' => 'خانه', 'url' => '']], $items);
    }

    public function home(): Response
    {
        $seo = SeoService::meta(['title' => SettingsService::get('store_name'), 'description' => SettingsService::get('store_tagline'), 'path' => '',
            'jsonld' => [SeoService::organization(), SeoService::website()]], null, null, 'home');
        return $this->render('home', 'home', [], $seo, ['vehicle-selector.js', 'pages/home.js']);
    }

    /* ---------------- Listings ---------------- */

    public function products(Request $request): Response
    {
        $hasFilters = (bool) array_intersect(array_keys($request->query), ['q', 'brand', 'min', 'max', 'vehicle', 'type', 'instock', 'discount', 'amazing', 'best', 'expert', 'new', 'sort', 'page', 'cat']);
        $initial = CatalogService::search(['per_page' => 24]);
        $seo = SeoService::meta(['title' => 'همه محصولات', 'description' => 'فهرست کامل لوازم جانبی و تجهیزات خودرو با فیلتر دسته‌بندی، برند، قیمت و خودروی سازگار.', 'path' => 'products',
            // Filtered variations point to the clean listing and are not indexed separately.
            'robots' => $hasFilters ? 'noindex, follow' : 'index, follow',
            'jsonld' => [SeoService::breadcrumbs($this->crumbs([['name' => 'همه محصولات', 'url' => 'products']])), SeoService::collection('همه محصولات', 'products', $initial['items'])]],
            null, null, 'products');
        return $this->render('products', 'listing', ['mode' => 'products', 'title' => 'همه محصولات', 'items' => $initial['items'],
            'crumbs' => [['name' => 'همه محصولات', 'url' => null]]], $seo, ['pages/listing.js']);
    }

    public function category(Request $request): Response
    {
        $cat = Database::one('SELECT * FROM categories WHERE slug = :s AND is_active = 1', ['s' => (string) $request->param('slug')]);
        if (!$cat) {
            throw new HttpException(404, 'دسته‌بندی یافت نشد.');
        }
        $initial = CatalogService::search(['cat' => [$cat['slug']], 'per_page' => 24]);
        $chain = CatalogService::breadcrumbs($cat['slug']);
        $children = Database::all('SELECT name, short_name, slug FROM categories WHERE parent_id = :id AND is_active = 1 ORDER BY sort_order', ['id' => $cat['id']]);
        $seo = SeoService::meta(['title' => $cat['name'], 'description' => $cat['description'], 'path' => 'categories/' . $cat['slug'], 'image' => $cat['image_path'],
            'robots' => $request->query ? 'noindex, follow' : 'index, follow',
            'jsonld' => [SeoService::breadcrumbs($this->crumbs($chain)), SeoService::collection($cat['name'], 'categories/' . $cat['slug'], $initial['items'])]],
            'category', (int) $cat['id']);
        $banner = ContentService::banners('category', 1)[0] ?? null;
        return $this->render('category', 'listing', ['mode' => 'category', 'title' => $cat['name'], 'category' => $cat, 'children' => $children, 'items' => $initial['items'],
            'total' => $initial['pagination']['total'], 'banner' => $banner,
            'crumbs' => array_map(static fn ($c, $i) => ['name' => $c['name'], 'url' => $i === count($chain) - 1 ? null : $c['url']], $chain, array_keys($chain)),
            'boot' => ['listing' => ['cat' => $cat['slug']]]], $seo, ['pages/listing.js']);
    }

    public function brand(Request $request): Response
    {
        $brand = Database::one('SELECT * FROM brands WHERE slug = :s AND is_active = 1', ['s' => (string) $request->param('slug')]);
        if (!$brand) {
            throw new HttpException(404, 'برند یافت نشد.');
        }
        $initial = CatalogService::search(['brand' => [$brand['slug']], 'per_page' => 24]);
        $title = 'محصولات برند ' . $brand['name'];
        $seo = SeoService::meta(['title' => $title, 'description' => $brand['description'], 'path' => 'brands/' . $brand['slug'], 'image' => $brand['logo_path'],
            'robots' => $request->query ? 'noindex, follow' : 'index, follow',
            'jsonld' => [SeoService::breadcrumbs($this->crumbs([['name' => $title, 'url' => 'brands/' . $brand['slug']]])), SeoService::collection($title, 'brands/' . $brand['slug'], $initial['items'])]],
            'brand', (int) $brand['id']);
        return $this->render('brand', 'listing', ['mode' => 'brand', 'title' => $title, 'brand' => $brand, 'items' => $initial['items'],
            'crumbs' => [['name' => $title, 'url' => null]], 'boot' => ['listing' => ['brand' => $brand['slug']]]], $seo, ['pages/listing.js']);
    }

    public function search(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $title = $q !== '' ? 'نتایج جستجو برای «' . $q . '»' : 'جستجوی محصولات';
        $seo = SeoService::meta(['title' => $title, 'path' => 'search'] + self::PRIVATE);
        return $this->render('search', 'listing', ['mode' => 'search', 'title' => $title, 'q' => $q, 'items' => [], 'crumbs' => [['name' => 'جستجو', 'url' => null]]], $seo, ['pages/listing.js']);
    }

    /* ---------------- Product ---------------- */

    public function product(Request $request): Response
    {
        $row = CatalogService::find((string) $request->param('slug'));
        if (!$row) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        $p = CatalogService::details($row);
        $crumbs = array_merge($p['breadcrumbs'], [['name' => $p['title'], 'url' => $p['url']]]);
        $jsonld = [SeoService::product($p), SeoService::breadcrumbs($this->crumbs($crumbs))];
        if ($p['faqs']) {
            $jsonld[] = SeoService::faqPage($p['faqs']);
        }
        $seo = SeoService::meta(['title' => $p['title'], 'description' => $p['shortDescription'] ?: $p['description'], 'path' => $p['url'], 'image' => $p['image'],
            'type' => 'product', 'jsonld' => $jsonld], 'product', $p['id']);
        $banner = ContentService::banners('product', 1)[0] ?? null;
        return $this->render('product', 'product', ['p' => $p, 'banner' => $banner, 'boot' => ['product' => ['id' => $p['id'], 'stock' => $p['stock'], 'variants' => $p['variants'], 'title' => $p['title']]]],
            $seo, ['pages/product.js']);
    }

    /* ---------------- Shopping & account (private) ---------------- */

    public function cart(): Response
    {
        return $this->render('cart', 'cart', [], SeoService::meta(['title' => 'سبد خرید', 'path' => 'cart'] + self::PRIVATE), ['pages/cart-page.js']);
    }

    public function checkout(): Response
    {
        return $this->render('checkout', 'checkout', [], SeoService::meta(['title' => 'تکمیل خرید', 'path' => 'checkout'] + self::PRIVATE), ['pages/checkout.js']);
    }

    public function orderPage(Request $request): Response
    {
        return $this->render('order', 'order', ['number' => (string) $request->param('number')], SeoService::meta(['title' => 'جزئیات سفارش', 'path' => 'checkout'] + self::PRIVATE), ['pages/order.js']);
    }

    public function favorites(): Response
    {
        return $this->render('favorites', 'favorites', [], SeoService::meta(['title' => 'علاقه‌مندی‌ها', 'path' => 'favorites'] + self::PRIVATE), ['pages/favorites-page.js']);
    }

    public function login(): Response
    {
        if (Auth::user()) {
            return Response::redirect(url('account'));
        }
        return $this->render('login', 'login', [], SeoService::meta(['title' => 'ورود و ثبت‌نام', 'path' => 'login'] + self::PRIVATE), ['pages/login.js']);
    }

    public function account(): Response
    {
        if (!Auth::user()) {
            return Response::redirect(url('login?next=account'));
        }
        return $this->render('account', 'account', [], SeoService::meta(['title' => 'حساب کاربری', 'path' => 'account'] + self::PRIVATE), ['pages/account.js']);
    }

    public function forgotPassword(): Response
    {
        return $this->render('login', 'forgot', [], SeoService::meta(['title' => 'بازیابی رمز عبور', 'path' => 'forgot-password'] + self::PRIVATE), ['pages/login.js']);
    }

    public function resetPassword(Request $request): Response
    {
        return $this->render('login', 'reset', ['token' => preg_match('/^[a-f0-9]{64}$/', (string) $request->query('token', '')) ? $request->query('token') : ''],
            SeoService::meta(['title' => 'تعیین رمز عبور جدید', 'path' => 'reset-password'] + self::PRIVATE), ['pages/login.js']);
    }

    public function tracking(): Response
    {
        return $this->render('tracking', 'tracking', [], SeoService::meta(['title' => 'پیگیری سفارش', 'path' => 'tracking'] + self::PRIVATE), ['pages/tracking.js']);
    }

    /* ---------------- CMS pages ---------------- */

    public function contact(): Response
    {
        return $this->cmsPage('contact', 'contact');
    }

    public function page(Request $request): Response
    {
        $slug = (string) $request->param('slug');
        if ($slug === 'contact') {
            return Response::redirect(url('contact'), 301);
        }
        return $this->cmsPage($slug, 'pages/' . $slug);
    }

    private function cmsPage(string $slug, string $path): Response
    {
        $page = ContentService::page($slug);
        if (!$page) {
            throw new HttpException(404, 'صفحه یافت نشد.');
        }
        $faqs = $page['template'] === 'faq' ? ContentService::faqs() : [];
        $jsonld = [SeoService::breadcrumbs($this->crumbs([['name' => $page['title'], 'url' => $path]]))];
        if ($faqs) {
            $jsonld[] = SeoService::faqPage($faqs);
        }
        if ($page['template'] === 'contact') {
            $jsonld[] = SeoService::organization();
        }
        $seo = SeoService::meta(['title' => $page['title'], 'description' => $page['excerpt'] ?: $page['content'], 'path' => $path, 'image' => $page['featured_image'], 'jsonld' => $jsonld],
            'page', (int) $page['id']);
        return $this->render($page['template'] === 'contact' ? 'contact' : 'page', 'page', ['page' => $page, 'faqs' => $faqs], $seo,
            $page['template'] === 'contact' ? ['pages/contact.js'] : []);
    }

    /* ---------------- Legacy static URLs → clean URLs (301) ---------------- */

    public function legacy(Request $request): Response
    {
        $file = basename($request->path);
        $q = $request->query;
        $target = match ($file) {
            'index.html' => '',
            'products.html' => 'products' . ($q ? '?' . http_build_query($q) : ''),
            'category.html' => isset($q['cat']) && preg_match('/^[\p{L}\p{N}-]+$/u', (string) $q['cat']) ? 'categories/' . $q['cat'] : 'products',
            'search.html' => 'search' . (isset($q['q']) ? '?q=' . rawurlencode((string) $q['q']) : ''),
            'product-details.html' => ($slug = isset($q['id']) && ctype_digit((string) $q['id']) ? Database::value("SELECT slug FROM products WHERE id = :id AND status = 'published'", ['id' => (int) $q['id']]) : null)
                ? 'products/' . $slug : 'products',
            'cart.html' => 'cart', 'favorites.html' => 'favorites', 'login.html' => 'login', 'tracking.html' => 'tracking', 'contact.html' => 'contact',
            default => null,
        };
        if ($target === null) {
            throw new HttpException(404, 'صفحه یافت نشد.');
        }
        return Response::redirect(url($target), 301);
    }

    /** Custom error page (404 has search, categories and popular products). */
    public function error(int $status, string $message): Response
    {
        $popular = $status === 404 ? CatalogService::search(['best' => 1, 'per_page' => 6])['items'] : [];
        $categories = $status === 404 ? Database::all('SELECT name, short_name, slug FROM categories WHERE is_active = 1 AND parent_id IS NOT NULL ORDER BY sort_order LIMIT 8') : [];
        $title = $status === 404 ? 'صفحه پیدا نشد' : 'خطا';
        return $this->render('error', 'error', ['status' => $status, 'message' => $message, 'popular' => $popular, 'categories' => $categories],
            SeoService::meta(['title' => $title, 'path' => ''] + self::PRIVATE), [], $status);
    }
}
