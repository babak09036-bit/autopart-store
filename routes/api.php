<?php
declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\Api;
use App\Core\Router;

/*
 * REST API. Every route runs CSRF verification for state-changing methods (POST/PUT/PATCH/DELETE).
 * Admin routes are protected by "admin" + a permission ("perm:<slug>") by default.
 */
return static function (Router $r): void {
    $r->group('/api', ['csrf'], static function (Router $r) {
        // ---- Boot / content ----
        $r->get('/boot', [Api\ContentController::class, 'boot']);
        $r->get('/settings/public', [Api\ContentController::class, 'settings']);
        $r->get('/menus', [Api\ContentController::class, 'menus']);
        $r->get('/banners', [Api\ContentController::class, 'banners']);
        $r->get('/homepage', [Api\ContentController::class, 'homepage']);
        $r->get('/pages/{slug}', [Api\ContentController::class, 'page']);
        $r->get('/faqs', [Api\ContentController::class, 'faqs']);
        $r->post('/contact', [Api\ContentController::class, 'contact'], ['throttle:contact,10,600']);
        $r->post('/newsletter', [Api\ContentController::class, 'newsletter'], ['throttle:newsletter,10,600']);

        // ---- Catalogue ----
        $r->get('/products', [Api\CatalogController::class, 'index']);
        $r->get('/products/{id:\d+}', [Api\CatalogController::class, 'show']);
        $r->get('/products/slug/{slug}', [Api\CatalogController::class, 'showBySlug']);
        $r->get('/search/suggest', [Api\CatalogController::class, 'suggest'], ['throttle:suggest,120,60']);
        $r->get('/categories', [Api\CatalogController::class, 'categories']);
        $r->get('/brands', [Api\CatalogController::class, 'brands']);
        $r->get('/vehicles', [Api\CatalogController::class, 'vehicles']);
        $r->get('/products/{id:\d+}/reviews', [Api\CatalogController::class, 'reviews']);
        $r->post('/products/{id:\d+}/reviews', [Api\CatalogController::class, 'storeReview'], ['auth', 'throttle:review,10,600']);
        $r->get('/products/{id:\d+}/questions', [Api\CatalogController::class, 'questions']);
        $r->post('/products/{id:\d+}/questions', [Api\CatalogController::class, 'storeQuestion'], ['auth', 'throttle:question,10,600']);

        // ---- Blog ----
        $r->get('/blog/posts', [Api\ContentController::class, 'posts']);
        $r->get('/blog/posts/{slug}', [Api\ContentController::class, 'post']);
        $r->get('/blog/categories', [Api\ContentController::class, 'blogCategories']);
        $r->get('/blog/tags', [Api\ContentController::class, 'blogTags']);
        $r->get('/blog/authors/{slug}', [Api\ContentController::class, 'author']);
        $r->get('/blog/search', [Api\ContentController::class, 'blogSearch'], ['throttle:blogsearch,60,60']);
        $r->post('/blog/posts/{slug}/comments', [Api\ContentController::class, 'storeComment'], ['throttle:comment,10,600']);
        $r->post('/blog/comments/{id:\d+}/report', [Api\ContentController::class, 'reportComment']);

        // ---- Customer auth ----
        $r->post('/auth/register', [Api\AuthController::class, 'register'], ['throttle:register,10,3600']);
        $r->post('/auth/login', [Api\AuthController::class, 'login'], ['throttle:login,20,600']);
        $r->post('/auth/logout', [Api\AuthController::class, 'logout']);
        $r->post('/auth/forgot-password', [Api\AuthController::class, 'forgotPassword'], ['throttle:forgot,5,3600']);
        $r->post('/auth/reset-password', [Api\AuthController::class, 'resetPassword'], ['throttle:reset,10,3600']);

        // ---- Account (logged-in customers only) ----
        $r->group('/account', ['auth'], static function (Router $r) {
            $r->get('/profile', [Api\AccountController::class, 'profile']);
            $r->put('/profile', [Api\AccountController::class, 'updateProfile']);
            $r->put('/password', [Api\AccountController::class, 'changePassword'], ['throttle:password,10,600']);
            $r->get('/addresses', [Api\AccountController::class, 'addresses']);
            $r->post('/addresses', [Api\AccountController::class, 'storeAddress']);
            $r->put('/addresses/{id:\d+}', [Api\AccountController::class, 'updateAddress']);
            $r->delete('/addresses/{id:\d+}', [Api\AccountController::class, 'deleteAddress']);
            $r->get('/orders', [Api\AccountController::class, 'orders']);
            $r->get('/orders/{number}', [Api\AccountController::class, 'order']);
            $r->get('/reviews', [Api\AccountController::class, 'reviews']);
            $r->get('/notifications', [Api\AccountController::class, 'notifications']);
        });
        $r->group('/wishlist', ['auth'], static function (Router $r) {
            $r->get('', [Api\AccountController::class, 'wishlist']);
            $r->post('/items', [Api\AccountController::class, 'addWishlist']);
            $r->delete('/items/{productId:\d+}', [Api\AccountController::class, 'removeWishlist']);
            $r->post('/sync', [Api\AccountController::class, 'syncWishlist']);
        });

        // ---- Cart & orders ----
        $r->get('/cart', [Api\ShopController::class, 'cart']);
        $r->post('/cart/items', [Api\ShopController::class, 'addItem'], ['throttle:cart,120,60']);
        $r->put('/cart/items/{id:\d+}', [Api\ShopController::class, 'updateItem'], ['throttle:cart,120,60']);
        $r->delete('/cart/items/{id:\d+}', [Api\ShopController::class, 'removeItem']);
        $r->post('/cart/coupon', [Api\ShopController::class, 'applyCoupon'], ['throttle:coupon,15,600']);
        $r->delete('/cart/coupon', [Api\ShopController::class, 'removeCoupon']);
        $r->post('/orders', [Api\ShopController::class, 'placeOrder'], ['throttle:order,10,600']);
        $r->get('/orders/{number}', [Api\ShopController::class, 'order']);
        $r->post('/orders/{number}/test-payment', [Api\ShopController::class, 'testPayment'], ['throttle:pay,20,600']);
        $r->post('/orders/track', [Api\ShopController::class, 'track'], ['throttle:track,20,600']);

        // ---- Admin ----
        $r->post('/admin/auth/login', [Admin\AuthController::class, 'login'], ['throttle:adminlogin,20,600']);
        $r->post('/admin/auth/logout', [Admin\AuthController::class, 'logout']);
        $r->group('/admin', ['admin'], static function (Router $r) {
            $r->get('/auth/me', [Admin\AuthController::class, 'me']);

            $r->get('/dashboard', [Admin\DashboardController::class, 'dashboard'], ['perm:dashboard.view']);
            $r->get('/reports', [Admin\DashboardController::class, 'reports'], ['perm:reports.view']);
            $r->get('/notifications', [Admin\DashboardController::class, 'notifications'], ['perm:notifications.view']);
            $r->post('/notifications/read', [Admin\DashboardController::class, 'readNotifications'], ['perm:notifications.view']);
            $r->get('/activity', [Admin\DashboardController::class, 'activity'], ['perm:logs.view']);

            $r->get('/products', [Admin\ProductController::class, 'index'], ['perm:products.view|products.manage']);
            $r->post('/products', [Admin\ProductController::class, 'store'], ['perm:products.manage']);
            $r->get('/products/{id:\d+}', [Admin\ProductController::class, 'show'], ['perm:products.view|products.manage']);
            $r->put('/products/{id:\d+}', [Admin\ProductController::class, 'update'], ['perm:products.manage']);
            $r->delete('/products/{id:\d+}', [Admin\ProductController::class, 'destroy'], ['perm:products.manage']);
            $r->post('/products/{id:\d+}/stock', [Admin\ProductController::class, 'adjustStock'], ['perm:inventory.manage']);
            $r->patch('/products/{id:\d+}/flags', [Admin\ProductController::class, 'flags'], ['perm:products.manage']);

            $r->get('/orders', [Admin\OrderController::class, 'index'], ['perm:orders.view']);
            $r->get('/orders/{id:\d+}', [Admin\OrderController::class, 'show'], ['perm:orders.view']);
            $r->put('/orders/{id:\d+}/status', [Admin\OrderController::class, 'updateStatus'], ['perm:orders.manage']);
            $r->put('/orders/{id:\d+}', [Admin\OrderController::class, 'updateDetails'], ['perm:orders.manage']);

            $r->get('/customers', [Admin\CustomerController::class, 'index'], ['perm:customers.view']);
            $r->get('/customers/{id:\d+}', [Admin\CustomerController::class, 'show'], ['perm:customers.view']);
            $r->put('/customers/{id:\d+}/status', [Admin\CustomerController::class, 'updateStatus'], ['perm:customers.manage']);

            $r->get('/reviews', [Admin\ReviewController::class, 'index'], ['perm:reviews.view|reviews.manage']);
            $r->get('/reviews/{id:\d+}', [Admin\ReviewController::class, 'show'], ['perm:reviews.view|reviews.manage']);
            $r->put('/reviews/{id:\d+}', [Admin\ReviewController::class, 'update'], ['perm:reviews.manage']);
            $r->delete('/reviews/{id:\d+}', [Admin\ReviewController::class, 'destroy'], ['perm:reviews.manage']);

            $r->get('/blog/posts', [Admin\BlogController::class, 'index'], ['perm:blog.posts']);
            $r->post('/blog/posts', [Admin\BlogController::class, 'store'], ['perm:blog.posts']);
            $r->get('/blog/posts/{id:\d+}', [Admin\BlogController::class, 'show'], ['perm:blog.posts']);
            $r->put('/blog/posts/{id:\d+}', [Admin\BlogController::class, 'update'], ['perm:blog.posts']);
            $r->delete('/blog/posts/{id:\d+}', [Admin\BlogController::class, 'destroy'], ['perm:blog.posts']);
            $r->post('/blog/posts/{id:\d+}/revisions/{revision:\d+}/restore', [Admin\BlogController::class, 'restoreRevision'], ['perm:blog.posts']);
            $r->get('/blog/comments', [Admin\BlogController::class, 'comments'], ['perm:blog.comments']);
            $r->put('/blog/comments/{id:\d+}', [Admin\BlogController::class, 'updateComment'], ['perm:blog.comments']);
            $r->post('/blog/comments/{id:\d+}/reply', [Admin\BlogController::class, 'replyComment'], ['perm:blog.comments']);
            $r->delete('/blog/comments/{id:\d+}', [Admin\BlogController::class, 'destroyComment'], ['perm:blog.comments']);

            $r->get('/homepage', [Admin\ContentController::class, 'sections'], ['perm:content.homepage']);
            $r->post('/homepage', [Admin\ContentController::class, 'saveSection'], ['perm:content.homepage']);
            $r->put('/homepage/{id:\d+}', [Admin\ContentController::class, 'saveSection'], ['perm:content.homepage']);
            $r->delete('/homepage/{id:\d+}', [Admin\ContentController::class, 'deleteSection'], ['perm:content.homepage']);
            $r->post('/homepage/reorder', [Admin\ContentController::class, 'reorderSections'], ['perm:content.homepage']);
            $r->get('/menus', [Admin\ContentController::class, 'menus'], ['perm:content.menus']);
            $r->post('/menus/items', [Admin\ContentController::class, 'saveMenuItem'], ['perm:content.menus']);
            $r->put('/menus/items/{id:\d+}', [Admin\ContentController::class, 'saveMenuItem'], ['perm:content.menus']);
            $r->delete('/menus/items/{id:\d+}', [Admin\ContentController::class, 'deleteMenuItem'], ['perm:content.menus']);
            $r->put('/menus/{id:\d+}', [Admin\ContentController::class, 'toggleMenu'], ['perm:content.menus']);
            $r->get('/settings', [Admin\ContentController::class, 'settings'], ['perm:settings.manage']);
            $r->put('/settings', [Admin\ContentController::class, 'saveSettings'], ['perm:settings.manage']);

            $r->get('/seo', [Admin\SeoController::class, 'index'], ['perm:seo.manage']);
            $r->get('/seo/robots', [Admin\SeoController::class, 'robots'], ['perm:seo.manage']);
            $r->put('/seo/robots', [Admin\SeoController::class, 'saveRobots'], ['perm:seo.manage']);
            $r->get('/seo/{type}/{id}', [Admin\SeoController::class, 'show'], ['perm:seo.manage']);
            $r->put('/seo/{type}/{id}', [Admin\SeoController::class, 'update'], ['perm:seo.manage']);

            $r->get('/media', [Admin\MediaController::class, 'index'], ['perm:media.manage|products.manage|content.banners|blog.posts']);
            $r->post('/media', [Admin\MediaController::class, 'upload'], ['perm:media.manage|products.manage|content.banners|blog.posts']);
            $r->put('/media/{id:\d+}', [Admin\MediaController::class, 'update'], ['perm:media.manage']);
            $r->delete('/media/{id:\d+}', [Admin\MediaController::class, 'destroy'], ['perm:media.manage']);

            $r->get('/admins', [Admin\AdminUserController::class, 'index'], ['perm:admins.manage']);
            $r->post('/admins', [Admin\AdminUserController::class, 'store'], ['perm:admins.manage']);
            $r->put('/admins/{id:\d+}', [Admin\AdminUserController::class, 'update'], ['perm:admins.manage']);
            $r->put('/roles/{id:\d+}', [Admin\AdminUserController::class, 'updateRole'], ['perm:admins.manage']);

            // Generic resources (permission checked per resource inside the controller)
            $r->get('/r/{resource}', [Admin\ResourceController::class, 'index']);
            $r->post('/r/{resource}', [Admin\ResourceController::class, 'store']);
            $r->get('/r/{resource}/{id:\d+}', [Admin\ResourceController::class, 'show']);
            $r->put('/r/{resource}/{id:\d+}', [Admin\ResourceController::class, 'update']);
            $r->delete('/r/{resource}/{id:\d+}', [Admin\ResourceController::class, 'destroy']);
            // Friendly aliases, e.g. GET /api/admin/banners
            $aliases = '{resource:banners|categories|brands|coupons|pages|faqs|redirects|messages|newsletter|questions|sitemap|vehicle-brands|vehicle-models|blog-categories|blog-tags|blog-authors}';
            $r->get('/' . $aliases, [Admin\ResourceController::class, 'index']);
            $r->post('/' . $aliases, [Admin\ResourceController::class, 'store']);
            $r->get('/' . $aliases . '/{id:\d+}', [Admin\ResourceController::class, 'show']);
            $r->put('/' . $aliases . '/{id:\d+}', [Admin\ResourceController::class, 'update']);
            $r->delete('/' . $aliases . '/{id:\d+}', [Admin\ResourceController::class, 'destroy']);
        });
    });
};
