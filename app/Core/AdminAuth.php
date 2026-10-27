<?php
declare(strict_types=1);

namespace App\Core;

/** Administrator authentication + role-based permissions (session key "admin_id"). */
final class AdminAuth
{
    private static ?array $admin = null;
    private static ?array $permissions = null;
    private static bool $loaded = false;

    public static function admin(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('admin_id');
            $last = (int) Session::get('admin_seen', 0);
            $idle = (int) Config::get('app.session.admin_idle_minutes', 120) * 60;
            if ($id && $last && time() - $last > $idle) {
                Session::forget('admin_id');
                $id = null;
            }
            if ($id) {
                self::$admin = Database::one("SELECT id, full_name, username, email, status FROM admin_users WHERE id = :id AND status = 'active'", ['id' => (int) $id]);
                if (self::$admin) {
                    Session::set('admin_seen', time());
                } else {
                    Session::forget('admin_id');
                }
            }
        }
        return self::$admin;
    }

    public static function id(): ?int
    {
        $admin = self::admin();
        return $admin ? (int) $admin['id'] : null;
    }

    /** @return string[] permission slugs */
    public static function permissions(): array
    {
        if (self::$permissions === null) {
            $id = self::id();
            self::$permissions = $id ? array_column(Database::all(
                'SELECT DISTINCT p.slug FROM admin_user_roles aur
                 JOIN role_permissions rp ON rp.role_id = aur.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE aur.admin_user_id = :id', ['id' => $id]), 'slug') : [];
        }
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        return in_array($permission, self::permissions(), true);
    }

    public static function login(int $adminId, string $ip): void
    {
        Session::regenerate();
        Session::set('admin_id', $adminId);
        Session::set('admin_seen', time());
        Csrf::rotate();
        self::$loaded = false;
        self::$permissions = null;
        Database::query('UPDATE admin_users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = :ip WHERE id = :id', ['ip' => $ip, 'id' => $adminId]);
    }

    public static function logout(): void
    {
        Session::forget('admin_id');
        Session::forget('admin_seen');
        Session::regenerate();
        Csrf::rotate();
        self::$admin = null;
        self::$permissions = [];
        self::$loaded = true;
    }
}
