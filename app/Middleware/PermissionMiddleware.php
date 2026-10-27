<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\AdminAuth;
use App\Core\HttpException;
use App\Core\Request;

/** perm:a|b passes when the admin has any of the listed permissions. */
final class PermissionMiddleware
{
    public static function handle(Request $request, string $permissions): void
    {
        AdminMiddleware::handle($request);
        foreach (explode('|', $permissions) as $permission) {
            if (AdminAuth::can($permission)) {
                return;
            }
        }
        throw new HttpException(403, 'شما مجوز انجام این عملیات را ندارید.');
    }
}
