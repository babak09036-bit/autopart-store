<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Api\AuthController as CustomerAuth;
use App\Core\AdminAuth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;

final class AuthController extends AdminController
{
    public function login(Request $request): Response
    {
        $data = $this->validate($request->all(), ['username' => 'required|string|maxlen:190', 'password' => 'required|string|maxlen:128']);
        $identifier = mb_strtolower($data['username']);
        CustomerAuth::assertNotLocked('admin', $identifier, $request->ip());
        $admin = Database::one('SELECT id, password_hash, status, full_name FROM admin_users WHERE username = :u OR email = :e', ['u' => $identifier, 'e' => $identifier]);
        $valid = $admin && password_verify($data['password'], $admin['password_hash']);
        Database::insert('login_attempts', ['scope' => 'admin', 'identifier' => $identifier, 'ip' => $request->ip(), 'success' => $valid ? 1 : 0]);
        if (!$valid) {
            throw new HttpException(422, 'نام کاربری یا رمز عبور اشتباه است.');
        }
        if ($admin['status'] !== 'active') {
            throw new HttpException(403, 'حساب مدیریتی شما غیرفعال است.');
        }
        AdminAuth::login((int) $admin['id'], $request->ip());
        ActivityLogger::admin('login', 'admin_user', (int) $admin['id'], 'ورود به پنل مدیریت');
        return $this->ok(['csrf' => Csrf::token()], 'خوش آمدید، ' . $admin['full_name']);
    }

    public function logout(): Response
    {
        if (AdminAuth::id()) {
            ActivityLogger::admin('logout', 'admin_user', AdminAuth::id(), 'خروج از پنل مدیریت');
        }
        AdminAuth::logout();
        return $this->ok(['csrf' => Csrf::token()], 'از پنل خارج شدید.');
    }

    public function me(): Response
    {
        $admin = AdminAuth::admin();
        $unread = (int) Database::value("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND (recipient_id IS NULL OR recipient_id = :id) AND read_at IS NULL", ['id' => $admin['id']]);
        return $this->ok([
            'admin' => ['id' => (int) $admin['id'], 'name' => $admin['full_name'], 'username' => $admin['username'],
                'roles' => array_column(Database::all('SELECT r.name FROM admin_user_roles ar JOIN roles r ON r.id = ar.role_id WHERE ar.admin_user_id = :id', ['id' => $admin['id']]), 'name')],
            'permissions' => AdminAuth::permissions(),
            'csrf' => Csrf::token(),
            'unread_notifications' => $unread,
        ]);
    }
}
