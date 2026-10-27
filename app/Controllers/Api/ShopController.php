<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;

/** Cart, checkout, payment callback and public order tracking. */
final class ShopController extends Controller
{
    public function cart(): Response
    {
        return $this->ok(CartService::summary());
    }

    public function addItem(Request $request): Response
    {
        $data = $this->validate($request->all(), ['product_id' => 'required|int|min:1', 'variant_id' => 'nullable|int|min:0', 'quantity' => 'nullable|int|min:1|max:50']);
        $result = CartService::add((int) $data['product_id'], (int) ($data['variant_id'] ?? 0), (int) ($data['quantity'] ?? 1));
        return $this->ok(CartService::summary(), $result['message'], 201);
    }

    public function updateItem(Request $request): Response
    {
        $data = $this->validate($request->all(), ['quantity' => 'required|int|min:1|max:50']);
        CartService::update($this->intParam($request, 'id'), (int) $data['quantity']);
        return $this->ok(CartService::summary(), 'تعداد به‌روزرسانی شد.');
    }

    public function removeItem(Request $request): Response
    {
        CartService::remove($this->intParam($request, 'id'));
        return $this->ok(CartService::summary(), 'کالا از سبد خرید حذف شد.');
    }

    public function applyCoupon(Request $request): Response
    {
        $data = $this->validate($request->all(), ['code' => 'required|string|maxlen:40']);
        $result = CartService::applyCoupon($data['code']);
        return $this->ok(CartService::summary(), $result['message']);
    }

    public function removeCoupon(): Response
    {
        CartService::removeCoupon();
        return $this->ok(CartService::summary(), 'کد تخفیف حذف شد.');
    }

    /** POST /api/orders — requires an Idempotency-Key header (a UUID generated once per checkout attempt). */
    public function placeOrder(Request $request): Response
    {
        $key = (string) ($request->header('Idempotency-Key') ?? $request->input('idempotency_key', ''));
        $result = OrderService::place($request->all(), $key);
        $message = $result['duplicate'] ? 'این سفارش قبلاً ثبت شده است.' : 'سفارش شما ثبت شد.';
        return $this->ok($result, $message, $result['duplicate'] ? 200 : 201);
    }

    public function order(Request $request): Response
    {
        $order = OrderService::findForCustomer((string) $request->param('number'));
        $data = OrderService::publicOrder($order, true);
        $data['test_payment_available'] = $order['status'] === 'pending_payment' && PaymentService::gateway()?->isTest();
        return $this->ok($data);
    }

    /** Sandbox payment confirmation (only when PAYMENT_TEST_MODE=true). */
    public function testPayment(Request $request): Response
    {
        $gateway = PaymentService::gateway();
        if (!$gateway || !$gateway->isTest()) {
            throw new HttpException(404, 'پرداخت آزمایشی فعال نیست.');
        }
        $order = OrderService::findForCustomer((string) $request->param('number'));
        $data = $this->validate($request->all(), ['result' => 'required|in:success,cancel']);
        $result = PaymentService::complete($order, ['result' => $data['result']]);
        $fresh = Database::one('SELECT * FROM orders WHERE id = :id', ['id' => $order['id']]);
        return $this->ok(['order' => OrderService::publicOrder($fresh), 'success' => $result['success']], $result['message']);
    }

    public function track(Request $request): Response
    {
        $data = $this->validate($request->all(), ['number' => 'required|regex:/^AP-\d{6}$/', 'mobile' => 'required|mobile']);
        return $this->ok(OrderService::track($data['number'], $data['mobile']));
    }
}
