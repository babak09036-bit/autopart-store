<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/** Every state-changing request must carry the session CSRF token in X-CSRF-Token. */
final class CsrfMiddleware
{
    public static function handle(Request $request): void
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $token = $request->header('X-CSRF-Token') ?? ($request->all()['_csrf'] ?? null);
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            // 403 rather than the non-standard 419, which Apache rewrites to 500; clients detect errors.csrf and retry once.
            throw new HttpException(403, 'نشست شما منقضی شده است؛ صفحه را دوباره بارگذاری کنید.', ['csrf' => 'expired']);
        }
    }
}
