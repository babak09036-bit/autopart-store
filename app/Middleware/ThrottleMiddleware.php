<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\RateLimiter;
use App\Core\Request;

/** throttle:name,max,seconds — keyed by client IP. */
final class ThrottleMiddleware
{
    public static function handle(Request $request, string $arg): void
    {
        [$name, $max, $seconds] = array_pad(explode(',', $arg), 3, null);
        RateLimiter::enforce('throttle:' . $name . ':' . $request->ip(), (int) ($max ?: 60), (int) ($seconds ?: 60));
    }
}
