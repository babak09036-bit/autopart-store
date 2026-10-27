<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;

abstract class AdminController extends Controller
{
    /** Paginated query helper: $sql must start with "SELECT ... FROM ..." without ORDER/LIMIT. */
    protected function paginate(string $sql, array $params, Request $request, string $order, int $defaultPerPage = 20): array
    {
        $perPage = max(5, min(100, (int) $request->query('per_page', $defaultPerPage)));
        $page = max(1, (int) $request->query('page', 1));
        $total = (int) Database::value('SELECT COUNT(*) FROM (' . $sql . ') t', $params);
        $rows = Database::all($sql . ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)]];
    }

    /** Whitelisted ORDER BY from ?sort=field&dir=asc|desc. */
    protected function order(Request $request, array $allowed, string $default): string
    {
        $sort = (string) $request->query('sort', '');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return isset($allowed[$sort]) ? $allowed[$sort] . ' ' . $dir : $default;
    }

    protected function requirePermission(string $permission): void
    {
        if (!AdminAuth::can($permission)) {
            throw new HttpException(403, 'شما مجوز انجام این عملیات را ندارید.');
        }
    }

    protected function like(string $value): string
    {
        return '%' . addcslashes(trim($value), '%_\\') . '%';
    }

    public static function maskMobile(?string $mobile): ?string
    {
        return $mobile ? substr($mobile, 0, 4) . '***' . substr($mobile, -4) : null;
    }

    public static function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return $email;
        }
        [$user, $domain] = explode('@', $email, 2);
        return mb_substr($user, 0, 2) . '***@' . $domain;
    }
}
