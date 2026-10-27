<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\ThrottleMiddleware;

/** Resolves middleware names used in route definitions. */
final class Middleware
{
    public static function run(string $name, Request $request): void
    {
        [$key, $arg] = array_pad(explode(':', $name, 2), 2, null);
        match ($key) {
            'csrf' => CsrfMiddleware::handle($request),
            'auth' => AuthMiddleware::handle($request),
            'admin' => AdminMiddleware::handle($request),
            'perm' => PermissionMiddleware::handle($request, (string) $arg),
            'throttle' => ThrottleMiddleware::handle($request, (string) $arg),
            default => throw new \LogicException('Unknown middleware ' . $key),
        };
    }
}
