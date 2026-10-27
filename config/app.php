<?php
declare(strict_types=1);

use App\Core\Env;

$url = rtrim((string) Env::get('APP_URL', 'http://localhost'), '/');

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => $url,
    // URL path the site is served from, e.g. "/auto" under XAMPP or "" at a domain root.
    'base_path' => rtrim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/'),
    'key' => Env::get('APP_KEY', ''),
    'timezone_display' => 'Asia/Tehran',

    'session' => [
        'name' => 'APSESSID',
        'secure' => Env::bool('SESSION_SECURE', false),
        'lifetime' => 0,
        'admin_idle_minutes' => 120,
    ],

    'uploads' => [
        'max_bytes' => 5 * 1024 * 1024,
        'review_max_bytes' => 2 * 1024 * 1024,
        'allowed_mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        'max_pixels' => 6000 * 6000,
        'thumb_width' => 480,
    ],

    'payment' => [
        // Enables the sandbox gateway. It never moves money and must be false in production.
        'test_mode' => Env::bool('PAYMENT_TEST_MODE', false),
        'default_gateway' => Env::get('PAYMENT_GATEWAY', 'test'),
    ],

    'mail' => [
        'driver' => Env::get('MAIL_DRIVER', 'log'),
        'from' => Env::get('MAIL_FROM', 'no-reply@example.com'),
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_window_minutes' => 15,
        'password_reset_minutes' => 30,
    ],
];
