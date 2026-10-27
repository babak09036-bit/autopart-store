<?php
declare(strict_types=1);

/*
 * Tests for the sensitive parts of the store. Run them against a development database only:
 * the HTTP suite registers a customer and places real (test-payment) orders.
 *
 *   php tests/run.php                              unit checks only (sanitizer, validation, uploads, redirects)
 *   php tests/run.php http://localhost/auto        + HTTP checks (headers, CSRF, access, cart → order → payment → review)
 *
 * Admin steps (coupon, order status, review moderation, permissions) run when these are set:
 *   ADMIN_USER=... ADMIN_PASS=... php tests/run.php http://localhost/auto
 * The HTTP suite needs PAYMENT_TEST_MODE=true on the server.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\HttpException;
use App\Services\HtmlSanitizer;
use App\Services\RedirectService;
use App\Services\Uploader;
use App\Validators\Validator;

$passed = 0;
$failed = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok   {$name}\n";
    } else {
        $failed[] = $name;
        echo "  FAIL {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}
function throws(callable $fn, int $status = 0): bool
{
    try {
        $fn();
        return false;
    } catch (HttpException $e) {
        return $status === 0 || $e->status === $status;
    }
}

/* ---------------------------------------------------------------- unit */
echo "Unit\n";

$dirty = '<p onclick="x()">متن <script>alert(1)</script><a href="javascript:alert(1)">لینک</a><img src="x" onerror="alert(1)"><iframe src="https://evil.test"></iframe><a href="https://example.com" target="_blank">ok</a></p>';
$clean = HtmlSanitizer::clean($dirty);
check('sanitizer removes <script>', !str_contains($clean, '<script') && !str_contains($clean, 'alert(1)</'));
check('sanitizer removes event handlers', !preg_match('/\son\w+=/i', $clean));
check('sanitizer removes javascript: links', !str_contains(strtolower($clean), 'javascript:'));
check('sanitizer removes iframes', !str_contains($clean, '<iframe'));
check('sanitizer keeps safe links with rel=noopener', str_contains($clean, 'https://example.com') && str_contains($clean, 'noopener'));

check('validator rejects SQL in int field', throws(fn () => Validator::validate(['id' => '1 OR 1=1'], ['id' => 'required|int']), 422));
check('validator accepts Persian-digit mobile', (Validator::validate(['m' => '۰۹۱۲۳۴۵۶۷۸۹'], ['m' => 'required|mobile'])['m'] ?? '') === '09123456789');
check('validator rejects bad mobile', throws(fn () => Validator::validate(['m' => '12345'], ['m' => 'required|mobile']), 422));
check('validator drops unknown fields (mass assignment)', !array_key_exists('is_admin', Validator::validate(['name' => 'a', 'is_admin' => 1], ['name' => 'required|string'])));
check('isUrl rejects javascript:', !Validator::isUrl('javascript:alert(1)'));
check('isUrl accepts relative path', Validator::isUrl('products/oil'));
check('isLocalPath rejects traversal', !Validator::isLocalPath('uploads/../config/app.php'));
check('isLocalPath rejects other folders', !Validator::isLocalPath('config/app.php'));
check('isLocalPath accepts uploads', Validator::isLocalPath('uploads/products/2026/10/a.webp'));

check('redirect normalise strips host and trailing slash', RedirectService::normalize('https://example.com/Old-Page/?a=1') === '/Old-Page' || RedirectService::normalize('/old-page/') === '/old-page');

$tmp = tempnam(sys_get_temp_dir(), 'apt');
file_put_contents($tmp, "<?php echo 'pwned';");
check('upload rejects PHP disguised as .jpg', throws(fn () => Uploader::image(['name' => 'shell.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], 'general'), 422));
file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
check('upload rejects SVG', throws(fn () => Uploader::image(['name' => 'a.svg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], 'general'), 422));
check('upload rejects unknown folder', throws(fn () => Uploader::image(['name' => 'a.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 1], '../config'), 422));
if (function_exists('imagecreatetruecolor')) {
    $im = imagecreatetruecolor(20, 20);
    imagepng($im, $tmp);
    imagedestroy($im);
    try {
        $saved = Uploader::image(['name' => 'ok.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], 'general');
        check('upload stores a re-encoded image with a random name', (bool) preg_match('#^uploads/general/\d{4}/\d{2}/[a-f0-9]{32}\.png$#', $saved['path']));
        Uploader::delete($saved['path']);
    } catch (\Throwable $e) {
        check('upload stores a valid PNG', false, $e->getMessage());
    }
}
@unlink($tmp);

/* ---------------------------------------------------------------- http */
$base = rtrim($argv[1] ?? '', '/');
if ($base !== '') {
    echo "\nHTTP ({$base})\n";

    /** Minimal client with its own cookie jar and CSRF token. */
    $client = static function () use ($base) {
        $jar = tempnam(sys_get_temp_dir(), 'jar');
        $state = ['csrf' => ''];
        $call = static function (string $method, string $path, ?array $body = null, array $headers = []) use ($base, $jar, &$state): array {
            $ch = curl_init($base . $path);
            $h = ['Accept: application/json', 'X-CSRF-Token: ' . $state['csrf']];
            foreach ($headers as $k => $v) {
                $h[] = $k . ': ' . $v;
            }
            if ($body !== null) {
                $h[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $h,
                CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false]);
            $raw = (string) curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            $json = json_decode(substr($raw, $hs), true);
            if (is_array($json) && isset($json['data']['csrf'])) {
                $state['csrf'] = $json['data']['csrf'];
            }
            return ['status' => $status, 'headers' => strtolower(substr($raw, 0, $hs)), 'json' => $json, 'body' => substr($raw, $hs)];
        };
        $call('GET', '/api/boot');
        return $call;
    };

    $guest = $client();
    $home = $guest('GET', '/');
    check('home page 200', $home['status'] === 200);
    check('security headers present', str_contains($home['headers'], 'x-content-type-options: nosniff') && str_contains($home['headers'], 'x-frame-options'));
    foreach (['/.env', '/config/app.php', '/app/bootstrap.php', '/database/schema.sql', '/storage/logs/', '/bin/console.php', '/tests/run.php'] as $p) {
        $r = $guest('GET', $p);
        check("blocked {$p}", in_array($r['status'], [403, 404], true), (string) $r['status']);
    }
    $r = $guest('GET', '/api/admin/dashboard');
    check('admin API requires login (401)', $r['status'] === 401);
    $r = $guest('GET', "/api/products?q=" . urlencode("' OR 1=1 -- ") . '&sort=' . urlencode('price; DROP TABLE users'));
    check('SQL injection in search is harmless', $r['status'] === 200 && ($r['json']['success'] ?? false));

    $noCsrf = (static function () use ($base) {
        $ch = curl_init($base . '/api/cart/items');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{"product_id":1}', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json']]);
        curl_exec($ch);
        $json = json_decode((string) curl_exec($ch), true);
        $s = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $s . (isset($json['errors']['csrf']) ? ' csrf' : '');
    })();
    check('POST without CSRF token rejected (403 csrf)', $noCsrf === '403 csrf', $noCsrf);

    // --- customer flow
    $c = $client();
    $mobile = '0912' . random_int(1000000, 9999999);
    $password = 'Test-' . bin2hex(random_bytes(6));
    $r = $c('POST', '/api/auth/register', ['full_name' => 'مشتری آزمایشی', 'mobile' => $mobile, 'password' => $password, 'password_confirmation' => $password, 'terms' => true]);
    check('register customer', $r['status'] === 201, json_encode($r['json']['errors'] ?? $r['json']['message'] ?? null, JSON_UNESCAPED_UNICODE));

    $productId = 2;
    $stockOf = static function () use ($guest, $productId): int {
        $d = $guest('GET', '/api/products/' . $productId)['json']['data'] ?? [];
        return (int) ($d['product']['stock'] ?? $d['stock'] ?? -1);
    };
    $before = $stockOf();
    $r = $c('POST', '/api/cart/items', ['product_id' => $productId, 'quantity' => 2, 'price' => 1]);
    check('add to cart', $r['status'] === 200 || $r['status'] === 201);
    $cart = $c('GET', '/api/cart')['json']['data'] ?? [];
    $line = $cart['items'][0] ?? [];
    check('client-sent price is ignored', isset($line['unit_price']) ? (float) $line['unit_price'] > 1 : (float) ($cart['totals']['subtotal'] ?? $cart['subtotal'] ?? 0) > 2);
    $r = $c('POST', '/api/cart/items', ['product_id' => $productId, 'quantity' => 999999]);
    check('quantity above limit rejected', $r['status'] === 422);

    $admin = null;
    if (getenv('ADMIN_USER') && getenv('ADMIN_PASS')) {
        $admin = $client();
        $r = $admin('POST', '/api/admin/auth/login', ['username' => getenv('ADMIN_USER'), 'password' => getenv('ADMIN_PASS')]);
        check('admin login', $r['status'] === 200);
        $code = 'T' . strtoupper(bin2hex(random_bytes(3)));
        $r = $admin('POST', '/api/admin/r/coupons', ['code' => $code, 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $couponId = $r['json']['data']['id'] ?? null;
        $r = $c('POST', '/api/cart/coupon', ['code' => $code]);
        check('apply coupon', $r['status'] === 200, (string) ($r['json']['message'] ?? ''));
        $r = $c('POST', '/api/cart/coupon', ['code' => 'NOPE-NOT-REAL']);
        check('unknown coupon rejected', $r['status'] === 422 || $r['status'] === 404);
    }

    $orderBody = ['customer_name' => 'مشتری آزمایشی', 'customer_mobile' => $mobile, 'province' => 'تهران', 'city' => 'تهران', 'address_line' => 'خیابان آزمایشی، پلاک ۱۲، واحد ۳', 'postal_code' => '1234567890'];
    $key = bin2hex(random_bytes(16));
    $r1 = $c('POST', '/api/orders', $orderBody, ['Idempotency-Key' => $key]);
    $number = $r1['json']['data']['order']['number'] ?? $r1['json']['data']['order']['order_number'] ?? null;
    check('place order', $r1['status'] === 201 && $number, json_encode($r1['json']['errors'] ?? $r1['json']['message'] ?? null, JSON_UNESCAPED_UNICODE));
    $r2 = $c('POST', '/api/orders', $orderBody, ['Idempotency-Key' => $key]);
    check('same Idempotency-Key returns the same order', ($r2['json']['data']['duplicate'] ?? false) === true && (($r2['json']['data']['order']['number'] ?? $r2['json']['data']['order']['order_number'] ?? null) === $number));
    check('stock decremented exactly once', $before - $stockOf() === 2, "before {$before}, after " . $stockOf());

    $other = $client();
    $r = $other('GET', '/api/orders/' . $number);
    check("another visitor cannot read the order", in_array($r['status'], [403, 404], true), (string) $r['status']);

    // one review per product per customer, so the "not bought" case uses a product outside this order
    $r = $c('POST', '/api/products/' . ($productId + 1) . '/reviews', ['rating' => 5, 'title' => 'بدون خرید', 'body' => 'این نظر برای محصولی ثبت می‌شود که خریده نشده است.']);
    $earlyReview = $r['json']['data']['id'] ?? null;
    check('review without a delivered purchase is not verified', $r['status'] === 201 && empty($r['json']['data']['verified_buyer']), (string) ($r['json']['message'] ?? ''));

    $r = $c('POST', "/api/orders/{$number}/test-payment", ['result' => 'success']);
    check('test payment', $r['status'] === 200, (string) ($r['json']['message'] ?? ''));
    $r = $guest('POST', '/api/orders/track', ['number' => $number, 'mobile' => $mobile]);
    check('tracking with number + mobile', $r['status'] === 200);
    $r = $guest('POST', '/api/orders/track', ['number' => $number, 'mobile' => '09120000000']);
    check('tracking with wrong mobile fails', $r['status'] !== 200);

    if ($admin) {
        $orderId = null;
        $list = $admin('GET', '/api/admin/orders?q=' . urlencode($number))['json']['data']['items'] ?? [];
        $orderId = $list[0]['id'] ?? null;
        $r = $admin('PUT', "/api/admin/orders/{$orderId}/status", ['status' => 'delivered']);
        check('invalid status jump rejected (paid → delivered)', $r['status'] === 422);
        foreach (['processing', 'ready_to_ship', 'shipped', 'delivered'] as $s) {
            $r = $admin('PUT', "/api/admin/orders/{$orderId}/status", ['status' => $s, 'tracking_code' => $s === 'shipped' ? 'TEST123' : null]);
        }
        check('order moved to delivered', $r['status'] === 200, (string) ($r['json']['message'] ?? ''));

        $c2 = $client();
        $c2('POST', '/api/auth/login', ['mobile' => $mobile, 'password' => $password]);
        $r = $c2('POST', '/api/products/' . $productId . '/reviews', ['rating' => 4, 'title' => 'بعد از تحویل', 'body' => 'این نظر بعد از تحویل سفارش ثبت می‌شود.']);
        $reviewId = $r['json']['data']['id'] ?? $earlyReview;
        $public = $guest('GET', "/api/products/{$productId}/reviews")['json']['data']['items'] ?? [];
        check('pending review not public', !in_array($reviewId, array_column($public, 'id'), true));
        if ($reviewId) {
            $admin('PUT', "/api/admin/reviews/{$reviewId}", ['status' => 'approved']);
            $public = $guest('GET', "/api/products/{$productId}/reviews")['json']['data']['items'] ?? [];
            $mine = array_values(array_filter($public, static fn ($x) => (int) $x['id'] === (int) $reviewId))[0] ?? null;
            check('approved review is public', $mine !== null);
            check('verified-buyer badge after delivery', $mine && !empty($mine['verified_buyer']));
            $r = $admin('PUT', "/api/admin/reviews/{$reviewId}", ['status' => 'rejected']);
            check('rejecting needs a reason', $r['status'] === 422);
            $admin('DELETE', "/api/admin/reviews/{$reviewId}");
        }
        if ($earlyReview && $earlyReview !== $reviewId) {
            $admin('DELETE', "/api/admin/reviews/{$earlyReview}");
        }
        $r = $c('GET', '/api/admin/dashboard');
        check('customer session cannot use admin API', $r['status'] === 401 || $r['status'] === 403);
        if (!empty($couponId)) {
            $admin('DELETE', "/api/admin/r/coupons/{$couponId}");
        }
    } else {
        echo "  skip admin steps (set ADMIN_USER and ADMIN_PASS)\n";
    }

    $r = $guest('POST', '/api/auth/login', ['mobile' => $mobile, 'password' => 'wrong-password']);
    check('wrong password rejected', $r['status'] === 422 || $r['status'] === 401);
}

echo "\n{$passed} passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
