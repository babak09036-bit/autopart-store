<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\OrderService;

final class PaymentService
{
    public static function gateway(): ?PaymentGatewayInterface
    {
        // Register real gateways here. Without one, orders stay "pending payment" and are confirmed manually by an admin.
        if (Config::get('app.payment.test_mode')) {
            return new TestGateway();
        }
        return null;
    }

    /** Creates the payment record for a new order (inside the order transaction). */
    public static function start(array $order): array
    {
        $gateway = self::gateway();
        $paymentId = Database::insert('payments', [
            'order_id' => $order['id'],
            'gateway' => $gateway?->name() ?? 'manual',
            'is_test' => $gateway?->isTest() ? 1 : 0,
            'amount' => $order['grand_total'],
            'status' => 'pending',
        ]);
        $payment = Database::one('SELECT * FROM payments WHERE id = :id', ['id' => $paymentId]);
        if (!$gateway) {
            return ['redirect_url' => null, 'mode' => 'manual', 'message' => 'درگاه پرداخت آنلاین فعال نیست؛ کارشناسان برای هماهنگی پرداخت با شما تماس می‌گیرند.'];
        }
        $result = $gateway->request($order, $payment);
        Database::query('UPDATE payments SET reference = :r WHERE id = :id', ['r' => $result['reference'], 'id' => $paymentId]);
        Database::insert('payment_transactions', ['payment_id' => $paymentId, 'type' => 'request', 'status' => 'created',
            'payload' => json_encode(['gateway' => $gateway->name(), 'amount' => $order['grand_total']])]);
        return ['redirect_url' => $result['redirect_url'], 'mode' => $gateway->isTest() ? 'test' : 'online', 'message' => null];
    }

    /** Handles the (test) gateway callback. Only orders in "pending_payment" can be paid. */
    public static function complete(array $order, array $callbackData): array
    {
        $gateway = self::gateway();
        if (!$gateway) {
            throw new HttpException(400, 'درگاه پرداخت فعال نیست.');
        }
        return Database::transaction(static function () use ($order, $callbackData, $gateway) {
            $locked = Database::one('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $order['id']]);
            if ($locked['status'] !== 'pending_payment') {
                throw new HttpException(409, 'وضعیت پرداخت این سفارش قبلاً مشخص شده است.');
            }
            $payment = Database::one("SELECT * FROM payments WHERE order_id = :o AND status = 'pending' ORDER BY id DESC LIMIT 1 FOR UPDATE", ['o' => $order['id']]);
            if (!$payment) {
                throw new HttpException(409, 'پرداخت در انتظاری برای این سفارش وجود ندارد.');
            }
            $result = $gateway->verify($payment, $callbackData);
            Database::insert('payment_transactions', ['payment_id' => $payment['id'], 'type' => 'verify', 'status' => $result['success'] ? 'success' : 'failed',
                'payload' => json_encode(['reference' => $result['reference'], 'test' => $gateway->isTest()])]);
            if (!$result['success']) {
                Database::query("UPDATE payments SET status = 'failed' WHERE id = :id", ['id' => $payment['id']]);
                Database::query("UPDATE orders SET payment_status = 'failed' WHERE id = :id", ['id' => $order['id']]);
                return $result;
            }
            Database::query("UPDATE payments SET status = 'succeeded', paid_at = UTC_TIMESTAMP() WHERE id = :id", ['id' => $payment['id']]);
            OrderService::changeStatus((int) $order['id'], 'paid', $gateway->isTest() ? 'پرداخت آزمایشی (بدون انتقال وجه)' : 'پرداخت آنلاین', null);
            ActivityLogger::audit('system', null, 'payment.succeeded', 'order', (int) $order['id'], ['test' => $gateway->isTest()]);
            NotificationService::admins('order.paid', 'سفارش ' . $order['order_number'] . ' پرداخت شد', null, '#/orders/' . $order['id']);
            return $result;
        });
    }
}
