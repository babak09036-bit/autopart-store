<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\OrderService;

/** Contact details are masked unless the admin has customers.sensitive. */
final class CustomerController extends AdminController
{
    public function index(Request $request): Response
    {
        $sensitive = AdminAuth::can('customers.sensitive');
        $sql = "SELECT u.id, u.full_name, u.mobile, u.email, u.status, u.created_at, u.last_login_at,
                (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
                (SELECT COALESCE(SUM(o.grand_total), 0) FROM orders o WHERE o.user_id = u.id AND o.status IN ('paid','processing','ready_to_ship','shipped','delivered')) AS total_spent
            FROM users u WHERE 1 = 1";
        $params = [];
        if ($q = trim(to_en_digits((string) $request->query('q', '')))) {
            $sql .= ' AND (u.full_name LIKE :q1' . ($sensitive ? ' OR u.mobile LIKE :q2 OR u.email LIKE :q3' : '') . ')';
            $params += ['q1' => $this->like($q)] + ($sensitive ? ['q2' => $this->like($q), 'q3' => $this->like($q)] : []);
        }
        if (in_array($s = (string) $request->query('status', ''), ['active', 'suspended'], true)) {
            $sql .= ' AND u.status = :s';
            $params['s'] = $s;
        }
        $wrapped = 'SELECT * FROM (' . $sql . ') t';
        $result = $this->paginate($wrapped, $params, $request, $this->order($request, ['created_at' => 't.created_at', 'orders' => 't.orders_count', 'spent' => 't.total_spent', 'name' => 't.full_name'], 't.id DESC'));
        $result['items'] = array_map(fn ($u) => $this->present($u, $sensitive), $result['items']);
        return $this->ok($result);
    }

    public function show(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $u = Database::one('SELECT id, full_name, mobile, email, status, created_at, last_login_at FROM users WHERE id = :id', ['id' => $id]);
        if (!$u) {
            throw new HttpException(404, 'مشتری یافت نشد.');
        }
        $sensitive = AdminAuth::can('customers.sensitive');
        $orders = Database::all('SELECT id, order_number, status, grand_total, placed_at FROM orders WHERE user_id = :id ORDER BY id DESC', ['id' => $id]);
        return $this->ok([
            'customer' => $this->present($u, $sensitive) + ['last_login_fa' => fa_date($u['last_login_at'], true)],
            'orders' => array_map(static fn ($o) => $o + ['status_label' => OrderService::STATUSES[$o['status']], 'placed_at_fa' => fa_date($o['placed_at'], true)], $orders),
            'addresses' => $sensitive ? Database::all('SELECT title, receiver_name, province, city, address_line FROM user_addresses WHERE user_id = :id', ['id' => $id]) : [],
            'reviews_count' => (int) Database::value('SELECT COUNT(*) FROM product_reviews WHERE user_id = :id', ['id' => $id]),
            'total_spent' => (float) Database::value("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE user_id = :id AND status IN ('paid','processing','ready_to_ship','shipped','delivered')", ['id' => $id]),
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), ['status' => 'required|in:active,suspended']);
        if (!Database::update('users', ['status' => $data['status']], 'id = :id', ['id' => $id]) && !Database::value('SELECT 1 FROM users WHERE id = :id', ['id' => $id])) {
            throw new HttpException(404, 'مشتری یافت نشد.');
        }
        ActivityLogger::admin('customer.status', 'user', $id, 'تغییر وضعیت حساب مشتری به ' . ($data['status'] === 'active' ? 'فعال' : 'مسدود'), $data);
        return $this->ok(null, 'وضعیت حساب ذخیره شد.');
    }

    private function present(array $u, bool $sensitive): array
    {
        return array_merge($u, [
            'mobile' => $sensitive ? $u['mobile'] : self::maskMobile($u['mobile']),
            'email' => $sensitive ? $u['email'] : self::maskEmail($u['email']),
            'created_at_fa' => fa_date($u['created_at']),
        ]);
    }
}
