<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;

/** Admin accounts and role permissions (requires admins.manage). */
final class AdminUserController extends AdminController
{
    public function index(): Response
    {
        $admins = Database::all('SELECT id, full_name, username, email, status, last_login_at, created_at FROM admin_users ORDER BY id');
        foreach ($admins as &$a) {
            $a['role_ids'] = array_map('intval', array_column(Database::all('SELECT role_id FROM admin_user_roles WHERE admin_user_id = :id', ['id' => $a['id']]), 'role_id'));
            $a['last_login_fa'] = fa_date($a['last_login_at'], true);
        }
        return $this->ok(['admins' => $admins, 'roles' => $this->roles(), 'permissions' => Database::all('SELECT id, slug, name, group_name FROM permissions ORDER BY id')]);
    }

    private function roles(): array
    {
        $roles = Database::all('SELECT id, slug, name, description, is_system FROM roles ORDER BY id');
        foreach ($roles as &$r) {
            $r['permission_ids'] = array_map('intval', array_column(Database::all('SELECT permission_id FROM role_permissions WHERE role_id = :id', ['id' => $r['id']]), 'permission_id'));
        }
        return $roles;
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request->all(), [
            'full_name' => 'required|string|maxlen:120', 'username' => 'required|regex:/^[a-z0-9_.-]{3,60}$/', 'email' => 'required|email',
            'password' => 'required|string|minlen:12|maxlen:128', 'role_ids' => 'required|array', 'status' => 'nullable|in:active,disabled',
        ]);
        $this->assertStrong($data['password']);
        if (Database::value('SELECT 1 FROM admin_users WHERE username = :u OR email = :e', ['u' => $data['username'], 'e' => $data['email']])) {
            throw new HttpException(422, 'نام کاربری یا ایمیل تکراری است.', ['username' => 'تکراری است.']);
        }
        $id = Database::transaction(function () use ($data) {
            $id = Database::insert('admin_users', ['full_name' => $data['full_name'], 'username' => $data['username'], 'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT), 'status' => $data['status'] ?? 'active']);
            $this->syncRoles($id, $data['role_ids']);
            return $id;
        });
        ActivityLogger::admin('admin.create', 'admin_user', $id, 'ایجاد مدیر: ' . $data['username'], ['roles' => $data['role_ids']]);
        return $this->ok(['id' => $id], 'مدیر جدید ایجاد شد.', 201);
    }

    public function update(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), [
            'full_name' => 'required|string|maxlen:120', 'email' => 'required|email', 'password' => 'nullable|string|minlen:12|maxlen:128',
            'role_ids' => 'required|array', 'status' => 'required|in:active,disabled',
        ]);
        if (!Database::value('SELECT 1 FROM admin_users WHERE id = :id', ['id' => $id])) {
            throw new HttpException(404, 'مدیر یافت نشد.');
        }
        $superRole = (int) Database::value("SELECT id FROM roles WHERE slug = 'super_admin'");
        $willBeSuper = in_array($superRole, array_map('intval', $data['role_ids']), true) && $data['status'] === 'active';
        $otherSupers = (int) Database::value("SELECT COUNT(*) FROM admin_user_roles ar JOIN admin_users a ON a.id = ar.admin_user_id
            WHERE ar.role_id = :r AND a.status = 'active' AND a.id <> :id", ['r' => $superRole, 'id' => $id]);
        if (!$willBeSuper && $otherSupers === 0) {
            throw new HttpException(422, 'حداقل یک مدیر کل فعال باید باقی بماند.');
        }
        if ($id === AdminAuth::id() && $data['status'] !== 'active') {
            throw new HttpException(422, 'نمی‌توانید حساب خودتان را غیرفعال کنید.');
        }
        if (!empty($data['password'])) {
            $this->assertStrong($data['password']);
        }
        Database::transaction(function () use ($id, $data) {
            $row = ['full_name' => $data['full_name'], 'email' => $data['email'], 'status' => $data['status']];
            if (!empty($data['password'])) {
                $row['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            Database::update('admin_users', $row, 'id = :id', ['id' => $id]);
            $this->syncRoles($id, $data['role_ids']);
        });
        ActivityLogger::admin('admin.update', 'admin_user', $id, 'ویرایش مدیر' . (!empty($data['password']) ? ' (با تغییر رمز)' : ''), ['roles' => $data['role_ids'], 'status' => $data['status']]);
        return $this->ok(null, 'اطلاعات مدیر ذخیره شد.');
    }

    public function updateRole(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $role = Database::one('SELECT * FROM roles WHERE id = :id', ['id' => $id]);
        if (!$role) {
            throw new HttpException(404, 'نقش یافت نشد.');
        }
        if ($role['slug'] === 'super_admin') {
            throw new HttpException(422, 'مجوزهای مدیر کل قابل تغییر نیست.');
        }
        $data = $this->validate($request->all(), ['permission_ids' => 'nullable|array']);
        Database::transaction(static function () use ($id, $data) {
            Database::query('DELETE FROM role_permissions WHERE role_id = :id', ['id' => $id]);
            foreach (array_unique(array_map('intval', (array) ($data['permission_ids'] ?? []))) as $pid) {
                Database::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT :r, id FROM permissions WHERE id = :p', ['r' => $id, 'p' => $pid]);
            }
        });
        ActivityLogger::admin('role.update', 'role', $id, 'تغییر مجوزهای نقش ' . $role['name'], ['permissions' => $data['permission_ids'] ?? []]);
        return $this->ok(null, 'مجوزهای نقش ذخیره شد.');
    }

    private function syncRoles(int $adminId, array $roleIds): void
    {
        Database::query('DELETE FROM admin_user_roles WHERE admin_user_id = :id', ['id' => $adminId]);
        foreach (array_unique(array_map('intval', $roleIds)) as $rid) {
            Database::query('INSERT IGNORE INTO admin_user_roles (admin_user_id, role_id) SELECT :a, id FROM roles WHERE id = :r', ['a' => $adminId, 'r' => $rid]);
        }
    }

    private function assertStrong(string $password): void
    {
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password) || strlen($password) < 12) {
            throw new HttpException(422, 'رمز عبور مدیر باید حداقل ۱۲ کاراکتر و شامل حرف و عدد باشد.', ['password' => 'رمز عبور ضعیف است.']);
        }
    }
}
