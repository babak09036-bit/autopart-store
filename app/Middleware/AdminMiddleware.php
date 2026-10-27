<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\AdminAuth;
use App\Core\HttpException;
use App\Core\Request;

final class AdminMiddleware
{
    public static function handle(Request $request): void
    {
        if (!AdminAuth::admin()) {
            throw new HttpException(401, 'برای دسترسی به پنل مدیریت وارد شوید.');
        }
    }
}
