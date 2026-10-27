<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

final class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        if (!Auth::user()) {
            throw new HttpException(401, 'برای ادامه وارد حساب کاربری خود شوید.');
        }
    }
}
