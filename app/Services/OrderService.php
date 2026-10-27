<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AdminAuth;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Services\Payments\PaymentService;
use App\Validators\Validator;

final class OrderService
{
    public const STATUSES = [
        'pending_payment' => 'در انتظار پرداخت',
        'paid' => 'پرداخت‌شده',
        'processing' => 'در حال پردازش',
        'ready_to_ship' => 'آماده ارسال',
        'shipped' => 'ارسال‌شده',
        'delivered' => 'تحویل‌شده',
        'cancelled' => 'لغوشده',
        'refunded' => 'مستردشده',
    ];

    /** Allowed admin transitions. */
    public const TRANSITIONS = [
        'pending_payment' => ['paid', 'processing', 'cancelled'],
        'paid' => ['processing', 'cancelled', 'refunded'],
        'processing' => ['ready_to_ship', 'cancelled', 'refunded'],
        'ready_to_ship' => ['shipped', 'processing', 'cancelled'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    private const SOLD = "'paid','processing','ready_to_ship','shipped','delivered'";

    /**
     * Places an order from the current cart.
     *  1. re-checks stock under row locks   2. reads real prices from the DB   3. re-validates the coupon
     *  4. writes everything in one transaction   5. idempotency key prevents duplicates on retries
     *  6. decrements stock   7. rolls back on any failure
     */
    public static function place(array $input, string $idempotencyKey): array
    {
        $data = Validator::validate($input, [
            'customer_name' => 'required|string|minlen:3|maxlen:120',
            'customer_mobile' => 'required|mobile',
            'customer_email' => 'nullable|email',
            'province' => 'required|string|maxlen:60',
            'city' => 'required|string|maxlen:60',
            'postal_code' => 'nullable|regex:/^\d{10}$/',
            'address_line' => 'required|string|minlen:10|maxlen:500',
            'customer_note' => 'nullable|string|maxlen:500',
            'save_address' => 'nullable|bool',
        ]);
        if (!preg_match('/^[a-f0-9-]{16,64}$/i', $idempotencyKey)) {
            throw new HttpException(422, 'شناسه درخواست نامعتبر است.');
        }
        $userId = Auth::id();
        $keyHash = hash('sha256', ($userId ?? 'guest') . '|' . $idempotencyKey);

        $existing = Database::one('SELECT * FROM orders WHERE idempotency_key = :k', ['k' => $keyHash]);
        if ($existing) {
            return ['order' => self::publicOrder($existing), 'payment' => null, 'duplicate' => true];
        }

        $cart = CartService::current();
        if (!$cart) {
            throw new HttpException(422, 'سبد خرید خالی است.');
        }

        try {
            $result = Database::transaction(static function () use ($cart, $data, $userId, $keyHash) {
                Database::query('SELECT id FROM carts WHERE id = :id FOR UPDATE', ['id' => $cart['id']]);
                $summary = CartService::summary($cart, true);
                $lines = array_values(array_filter($summary['items'], static fn ($i) => $i['available']));
                if (!$lines) {
                    throw new HttpException(422, 'سبد خرید شما کالای قابل خریدی ندارد.');
                }
                foreach ($summary['items'] as $item) {
                    if (!$item['available'] || $item['quantity'] > $item['stock']) {
                        throw new HttpException(409, 'موجودی «' . $item['title'] . '» تغییر کرده است؛ سبد خرید را بررسی کنید.');
                    }
                }
                $totals = $summary['totals'];
                if (!$totals['meets_minimum']) {
                    throw new HttpException(422, 'حداقل مبلغ سفارش ' . fa_number($totals['min_order_amount']) . ' تومان است.');
                }

                $coupon = null;
                $discount = 0.0;
                if ($summary['coupon']) {
                    $coupon = CouponService::findByCode($summary['coupon']['code'], true);
                    $check = CouponService::evaluate($coupon, $lines, $totals['subtotal'], $userId, $data['customer_mobile']);
                    if (!$check['ok']) {
                        throw new HttpException(422, $check['message']);
                    }
                    $discount = $check['discount'];
                    $totals = CartService::totals($totals['subtotal'], $totals['subtotal_original'], $discount);
                }

                $orderId = Database::insert('orders', [
                    'order_number' => self::newOrderNumber(),
                    'user_id' => $userId,
                    'status' => 'pending_payment',
                    'payment_status' => 'unpaid',
                    'subtotal' => $totals['subtotal'],
                    'discount_total' => $discount,
                    'shipping_total' => $totals['shipping'],
                    'grand_total' => $totals['grand_total'],
                    'coupon_id' => $coupon['id'] ?? null,
                    'coupon_code' => $coupon['code'] ?? null,
                    'customer_name' => $data['customer_name'],
                    'customer_mobile' => $data['customer_mobile'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'customer_note' => $data['customer_note'] ?? null,
                    'idempotency_key' => $keyHash,
                ]);

                foreach ($lines as $line) {
                    $sku = Database::value('SELECT COALESCE((SELECT sku FROM product_variants WHERE id = :v), (SELECT sku FROM products WHERE id = :p))',
                        ['v' => $line['variant_id'], 'p' => $line['product_id']]);
                    Database::insert('order_items', [
                        'order_id' => $orderId,
                        'product_id' => $line['product_id'],
                        'variant_id' => $line['variant_id'],
                        'product_name' => $line['title'],
                        'variant_name' => $line['variant_name'],
                        'sku' => $sku,
                        'unit_price' => $line['unit_price'],
                        'original_price' => $line['original_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                    ]);
                    InventoryService::adjust($line['product_id'], $line['variant_id'], -$line['quantity'], 'order', 'order', $orderId, null, null);
                }

                Database::insert('order_addresses', [
                    'order_id' => $orderId,
                    'receiver_name' => $data['customer_name'],
                    'receiver_mobile' => $data['customer_mobile'],
                    'province' => $data['province'],
                    'city' => $data['city'],
                    'postal_code' => $data['postal_code'] ?? null,
                    'address_line' => $data['address_line'],
                ]);
                Database::insert('order_status_history', ['order_id' => $orderId, 'from_status' => null, 'to_status' => 'pending_payment', 'note' => 'ثبت سفارش']);
                Database::insert('shipments', ['order_id' => $orderId, 'status' => 'pending']);

                if ($coupon) {
                    Database::insert('coupon_usages', ['coupon_id' => $coupon['id'], 'order_id' => $orderId, 'user_id' => $userId,
                        'customer_mobile' => $data['customer_mobile'], 'discount_amount' => $discount]);
                    Database::query('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id', ['id' => $coupon['id']]);
                }
                if ($userId && !empty($data['save_address'])) {
                    Database::insert('user_addresses', ['user_id' => $userId, 'title' => 'نشانی سفارش', 'receiver_name' => $data['customer_name'],
                        'receiver_mobile' => $data['customer_mobile'], 'province' => $data['province'], 'city' => $data['city'],
                        'postal_code' => $data['postal_code'] ?? null, 'address_line' => $data['address_line']]);
                }

                $order = Database::one('SELECT * FROM orders WHERE id = :id', ['id' => $orderId]);
                $payment = PaymentService::start($order);
                CartService::clear((int) $cart['id']);
                ActivityLogger::audit($userId ? 'user' : 'guest', $userId, 'order.placed', 'order', $orderId, ['total' => $order['grand_total']]);
                NotificationService::admins('order.new', 'سفارش جدید ' . $order['order_number'], 'مبلغ: ' . fa_number($order['grand_total']) . ' تومان', '#/orders/' . $orderId);
                return ['order' => $order, 'payment' => $payment];
            });
        } catch (\PDOException $e) {
            // A concurrent retry with the same idempotency key may have won the race.
            $existing = Database::one('SELECT * FROM orders WHERE idempotency_key = :k', ['k' => $keyHash]);
            if ($existing) {
                return ['order' => self::publicOrder($existing), 'payment' => null, 'duplicate' => true];
            }
            throw $e;
        }

        self::rememberGuestOrder((int) $result['order']['id']);
        return ['order' => self::publicOrder($result['order']), 'payment' => $result['payment'], 'duplicate' => false];
    }

    private static function newOrderNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $number = 'AP-' . random_int(200000, 999999);
            if (!Database::value('SELECT 1 FROM orders WHERE order_number = :n', ['n' => $number])) {
                return $number;
            }
        }
        throw new \RuntimeException('Could not allocate an order number');
    }

    /** Guests can view the orders they placed in this session. */
    private static function rememberGuestOrder(int $orderId): void
    {
        $ids = (array) Session::get('guest_orders', []);
        $ids[] = $orderId;
        Session::set('guest_orders', array_slice(array_unique($ids), -10));
    }

    /** Ownership check: the logged-in owner or the guest session that placed it. */
    public static function findForCustomer(string $orderNumber): array
    {
        $order = Database::one('SELECT * FROM orders WHERE order_number = :n', ['n' => $orderNumber]);
        $userId = Auth::id();
        $allowed = $order && (
            ($userId && (int) $order['user_id'] === $userId)
            || in_array((int) $order['id'], array_map('intval', (array) Session::get('guest_orders', [])), true)
        );
        if (!$allowed) {
            throw new HttpException(404, 'سفارش یافت نشد.');
        }
        return $order;
    }

    public static function publicOrder(array $order, bool $withItems = false): array
    {
        $out = [
            'id' => (int) $order['id'],
            'number' => $order['order_number'],
            'status' => $order['status'],
            'status_label' => self::STATUSES[$order['status']] ?? $order['status'],
            'payment_status' => $order['payment_status'],
            'subtotal' => (float) $order['subtotal'],
            'discount' => (float) $order['discount_total'],
            'shipping' => (float) $order['shipping_total'],
            'total' => (float) $order['grand_total'],
            'placed_at' => $order['placed_at'],
            'placed_at_fa' => fa_date($order['placed_at'], true),
        ];
        if ($withItems) {
            $out['items'] = array_map(static fn ($i) => [
                'product_id' => $i['product_id'] ? (int) $i['product_id'] : null,
                'title' => $i['product_name'], 'variant' => $i['variant_name'], 'quantity' => (int) $i['quantity'],
                'unit_price' => (float) $i['unit_price'], 'line_total' => (float) $i['line_total'],
                'url' => $i['slug'] ? 'products/' . $i['slug'] : null,
            ], Database::all('SELECT oi.*, p.slug FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :id', ['id' => $order['id']]));
            $out['history'] = array_map(static fn ($h) => ['status' => $h['to_status'], 'label' => self::STATUSES[$h['to_status']] ?? $h['to_status'], 'at' => fa_date($h['created_at'], true)],
                Database::all('SELECT to_status, created_at FROM order_status_history WHERE order_id = :id ORDER BY id', ['id' => $order['id']]));
            $shipment = Database::one('SELECT carrier, tracking_code, status FROM shipments WHERE order_id = :id', ['id' => $order['id']]);
            $out['shipment'] = $shipment;
            $out['address'] = Database::one('SELECT receiver_name, province, city, address_line FROM order_addresses WHERE order_id = :id', ['id' => $order['id']]);
        }
        return $out;
    }

    /** Status change with history, stock restore on cancel/refund and customer notification. */
    public static function changeStatus(int $orderId, string $to, ?string $note, ?int $adminId, array $extra = []): void
    {
        $order = Database::one('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
        if (!$order) {
            throw new HttpException(404, 'سفارش یافت نشد.');
        }
        $from = $order['status'];
        if ($from === $to) {
            return;
        }
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new HttpException(422, 'تغییر وضعیت از «' . self::STATUSES[$from] . '» به «' . (self::STATUSES[$to] ?? $to) . '» مجاز نیست.');
        }
        $update = ['status' => $to];
        if ($to === 'paid') {
            $update['payment_status'] = 'paid';
            Database::query("UPDATE payments SET status = 'succeeded', paid_at = COALESCE(paid_at, UTC_TIMESTAMP()) WHERE order_id = :o AND status = 'pending'", ['o' => $orderId]);
        }
        if ($to === 'refunded') {
            $update['payment_status'] = $order['payment_status'] === 'paid' ? 'refunded' : $order['payment_status'];
            Database::query("UPDATE payments SET status = 'refunded' WHERE order_id = :o AND status = 'succeeded'", ['o' => $orderId]);
        }
        Database::update('orders', $update, 'id = :id', ['id' => $orderId]);

        if (in_array($to, ['cancelled', 'refunded'], true)) {
            self::restoreStock($orderId, $adminId);
        }
        if ($to === 'shipped') {
            Database::update('shipments', array_filter([
                'status' => 'shipped', 'shipped_at' => now_utc(),
                'carrier' => $extra['carrier'] ?? null, 'tracking_code' => $extra['tracking_code'] ?? null,
            ], static fn ($v) => $v !== null), 'order_id = :o', ['o' => $orderId]);
        }
        if ($to === 'delivered') {
            Database::update('shipments', ['status' => 'delivered', 'delivered_at' => now_utc()], 'order_id = :o', ['o' => $orderId]);
        }
        Database::insert('order_status_history', ['order_id' => $orderId, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'admin_user_id' => $adminId]);
        self::refreshSalesCounts($orderId);

        if ($order['user_id']) {
            NotificationService::user((int) $order['user_id'], 'order.status', 'وضعیت سفارش ' . $order['order_number'] . ': ' . self::STATUSES[$to], $note, 'account#orders');
        }
        if ($adminId) {
            ActivityLogger::admin('order.status', 'order', $orderId, 'تغییر وضعیت سفارش ' . $order['order_number'] . ' به ' . self::STATUSES[$to], ['status' => [$from, $to], 'note' => $note]);
        }
    }

    /** Returns sold stock once per order (idempotent). */
    private static function restoreStock(int $orderId, ?int $adminId): void
    {
        $already = Database::value("SELECT 1 FROM inventory_movements WHERE reference_type = 'order' AND reference_id = :o AND reason = 'order_cancel' LIMIT 1", ['o' => $orderId]);
        if ($already) {
            return;
        }
        foreach (Database::all('SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = :o AND product_id IS NOT NULL', ['o' => $orderId]) as $item) {
            InventoryService::adjust((int) $item['product_id'], (int) $item['variant_id'], (int) $item['quantity'], 'order_cancel', 'order', $orderId, 'بازگشت موجودی سفارش لغو/مسترد شده', $adminId);
        }
    }

    private static function refreshSalesCounts(int $orderId): void
    {
        Database::query('UPDATE products p SET sales_count = (
            SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE oi.product_id = p.id AND o.status IN (' . self::SOLD . '))
            WHERE p.id IN (SELECT product_id FROM order_items WHERE order_id = :o AND product_id IS NOT NULL)', ['o' => $orderId]);
    }

    /** Public tracking needs both the order number and the customer's mobile (privacy). */
    public static function track(string $number, string $mobile): array
    {
        $order = Database::one('SELECT * FROM orders WHERE order_number = :n AND customer_mobile = :m', ['n' => $number, 'm' => $mobile]);
        if (!$order) {
            throw new HttpException(404, 'سفارشی با این مشخصات پیدا نشد.');
        }
        return self::publicOrder($order, true);
    }

    public static function adminId(): ?int
    {
        return AdminAuth::id();
    }
}
