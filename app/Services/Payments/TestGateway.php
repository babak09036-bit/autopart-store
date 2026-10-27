<?php
declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Sandbox gateway for development and demos. It never contacts a bank and never moves money.
 * It is only available when PAYMENT_TEST_MODE=true; payments it confirms are flagged is_test = 1.
 */
final class TestGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'test';
    }

    public function isTest(): bool
    {
        return true;
    }

    public function request(array $order, array $payment): array
    {
        return ['redirect_url' => 'checkout/test-payment?order=' . rawurlencode($order['order_number']), 'reference' => 'TEST-' . strtoupper(bin2hex(random_bytes(5)))];
    }

    public function verify(array $payment, array $callbackData): array
    {
        $success = ($callbackData['result'] ?? '') === 'success';
        return ['success' => $success, 'reference' => $payment['reference'], 'message' => $success ? 'پرداخت آزمایشی موفق بود.' : 'پرداخت آزمایشی لغو شد.'];
    }
}
