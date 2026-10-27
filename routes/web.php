<?php
declare(strict_types=1);

use App\Controllers\Web\BlogController;
use App\Controllers\Web\PageController;
use App\Controllers\Web\SeoController;
use App\Core\Router;

/* Public storefront routes (clean URLs). Anything else → custom 404. */
return static function (Router $r): void {
    $r->get('/', [PageController::class, 'home']);
    $r->get('/products', [PageController::class, 'products']);
    $r->get('/products/{slug}', [PageController::class, 'product']);
    $r->get('/categories/{slug}', [PageController::class, 'category']);
    $r->get('/brands/{slug}', [PageController::class, 'brand']);
    $r->get('/search', [PageController::class, 'search']);

    $r->get('/cart', [PageController::class, 'cart']);
    $r->get('/checkout', [PageController::class, 'checkout']);
    $r->get('/checkout/test-payment', [PageController::class, 'checkout']);
    $r->get('/orders/{number}', [PageController::class, 'orderPage']);
    $r->get('/favorites', [PageController::class, 'favorites']);
    $r->get('/login', [PageController::class, 'login']);
    $r->get('/account', [PageController::class, 'account']);
    $r->get('/forgot-password', [PageController::class, 'forgotPassword']);
    $r->get('/reset-password', [PageController::class, 'resetPassword']);
    $r->get('/tracking', [PageController::class, 'tracking']);

    $r->get('/contact', [PageController::class, 'contact']);
    $r->get('/pages/{slug}', [PageController::class, 'page']);

    $r->get('/blog', [BlogController::class, 'index']);
    $r->get('/blog/search', [BlogController::class, 'search']);
    $r->get('/blog/category/{slug}', [BlogController::class, 'category']);
    $r->get('/blog/tag/{slug}', [BlogController::class, 'tag']);
    $r->get('/blog/author/{slug}', [BlogController::class, 'author']);
    $r->get('/blog/preview/{id:\d+}', [BlogController::class, 'preview']);
    $r->get('/blog/{slug}', [BlogController::class, 'article']);

    $r->get('/sitemap.xml', [SeoController::class, 'sitemapIndex']);
    $r->get('/sitemap-{section:[a-z-]+}.xml', [SeoController::class, 'sitemap']);
    $r->get('/robots.txt', [SeoController::class, 'robots']);

    // 301s from the previous static file names
    $r->get('/{file:[a-z-]+\.html}', [PageController::class, 'legacy']);
};
