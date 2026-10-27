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

final class OrderController extends AdminController
{
    public function index(Request $request): Response
    {
        $sensitive = AdminAuth::can('customers.sensitive');
        $sql = 'SELECT o.id, o.order_number, o.customer_name, o.customer_mobile, o.status, o.payment_status, o.grand_total, o.placed_at,
                (SELECT SUM(quantity) FROM order_items oi WHERE oi.order_id = o.id) AS items_count,
                (SELECT tracking_code FROM shipments s WHERE s.order_id = o.id) AS tracking_code
            FROM orders o WHERE 1 = 1';
        $params = [];
        if (isset(OrderService::STATUSES[$s = (string) $request->query('status', '')])) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $s;
        }
        if (in_array($p = (string) $request->query('payment_status', ''), ['unpaid', 'paid', 'failed', 'refunded'], true)) {
            $sql .= ' AND o.payment_status = :ps';
            $params['ps'] = $p;
        }
        if ($q = trim(to_en_digits((string) $request->query('q', '')))) {
            $sql .= ' AND (o.order_number LIKE :q1 OR o.customer_name LIKE :q2' . ($sensitive ? ' OR o.customer_mobile LIKE :q3' : '') . ')';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q)] + ($sensitive ? ['q3' => $this->like($q)] : []);
        }
        if ($from = $request->query('from')) {
            $sql .= ' AND o.placed_at >= :from';
            $params['from'] = gmdate('Y-m-d H:i:s', strtotime($from . ' 00:00 Asia/Tehran') ?: 0);
        }
        if ($to = $request->query('to')) {
            $sql .= ' AND o.placed_at < :to';
            $params['to'] = gmdate('Y-m-d H:i:s', (strtotime($to . ' 00:00 Asia/Tehran') ?: time()) + 86400);
        }
        $result = $this->paginate($sql, $params, $request, $this->order($request, ['placed_at' => 'o.placed_at', 'total' => 'o.grand_total', 'id' => 'o.id'], 'o.id DESC'));
        $result['items'] = array_map(static fn ($o) => $o + [
            'status_label' => OrderService::STATUSES[$o['status']],
            'placed_at_fa' => fa_date($o['placed_at'], true),
            'customer_mobile' => $sensitive ? $o['customer_mobile'] : self::maskMobile($o['customer_mobile']),
        ], $result['items']);
        return $this->ok($result + ['statuses' => OrderService::STATUSES]);
    }

    public function show(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $o = Database::one('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
        if (!$o) {
            throw new HttpException(404, 'سفارش یافت نشد.');
        }
        $sensitive = AdminAuth::can('customers.sensitive');
        $address = Database::one('SELECT * FROM order_addresses WHERE order_id = :id', ['id' => $id]);
        return $this->ok([
            'order' => $o + [
                'status_label' => OrderService::STATUSES[$o['status']],
                'placed_at_fa' => fa_date($o['placed_at'], true),
                'customer_mobile' => $sensitive ? $o['customer_mobile'] : self::maskMobile($o['customer_mobile']),
                'customer_email' => $sensitive ? $o['customer_email'] : self::maskEmail($o['customer_email']),
            ],
            'address' => $sensitive ? $address : ($address ? ['province' => $address['province'], 'city' => $address['city'], 'hidden' => true] : null),
            'items' => Database::all('SELECT * FROM order_items WHERE order_id = :id', ['id' => $id]),
            'history' => array_map(static fn ($h) => $h + ['at' => fa_date($h['created_at'], true), 'label' => OrderService::STATUSES[$h['to_status']] ?? $h['to_status']],
                Database::all('SELECT h.*, a.full_name AS admin_name FROM order_status_history h LEFT JOIN admin_users a ON a.id = h.admin_user_id WHERE h.order_id = :id ORDER BY h.id', ['id' => $id])),
            'payments' => array_map(static fn ($p) => $p + ['at' => fa_date($p['created_at'], true)], Database::all('SELECT id, gateway, is_test, amount, status, reference, paid_at, created_at FROM payments WHERE order_id = :id ORDER BY id', ['id' => $id])),
            'shipment' => Database::one('SELECT * FROM shipments WHERE order_id = :id', ['id' => $id]),
            'allowed_transitions' => array_map(static fn ($s) => ['value' => $s, 'label' => OrderService::STATUSES[$s]], OrderService::TRANSITIONS[$o['status']]),
            'customer_orders' => $o['user_id'] ? (int) Database::value('SELECT COUNT(*) FROM orders WHERE user_id = :u', ['u' => $o['user_id']]) : null,
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), [
            'status' => 'required|in:' . implode(',', array_keys(OrderService::STATUSES)),
            'note' => 'nullable|string|maxlen:500',
            'carrier' => 'nullable|string|maxlen:60',
            'tracking_code' => 'nullable|string|maxlen:80',
        ]);
        Database::transaction(static fn () => OrderService::changeStatus($id, $data['status'], $data['note'] ?? null, AdminAuth::id(), $data));
        return $this->ok(null, 'وضعیت سفارش به «' . OrderService::STATUSES[$data['status']] . '» تغییر کرد.');
    }

    public function updateDetails(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        if (!Database::value('SELECT 1 FROM orders WHERE id = :id', ['id' => $id])) {
            throw new HttpException(404, 'سفارش یافت نشد.');
        }
        $data = $this->validate($request->all(), ['admin_note' => 'nullable|string|maxlen:3000', 'carrier' => 'nullable|string|maxlen:60', 'tracking_code' => 'nullable|string|maxlen:80']);
        Database::transaction(static function () use ($id, $data) {
            if (array_key_exists('admin_note', $data)) {
                Database::update('orders', ['admin_note' => $data['admin_note']], 'id = :id', ['id' => $id]);
            }
            $ship = array_intersect_key($data, array_flip(['carrier', 'tracking_code']));
            if ($ship) {
                Database::update('shipments', $ship, 'order_id = :id', ['id' => $id]);
            }
        });
        ActivityLogger::admin('update', 'order', $id, 'ویرایش یادداشت/اطلاعات ارسال سفارش', $data);
        return $this->ok(null, 'اطلاعات سفارش ذخیره شد.');
    }
}
