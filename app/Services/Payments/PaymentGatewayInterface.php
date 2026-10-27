<?php
declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Contract for online payment gateways. A real gateway (e.g. a bank's IPG) must:
 *  - create a payment request and return the URL to redirect the customer to;
 *  - verify the callback with the provider's server before marking an order as paid.
 * No real gateway is bundled: integrating one requires the provider's credentials and documentation.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    public function isTest(): bool;

    /** @return array{redirect_url:?string, reference:?string} */
    public function request(array $order, array $payment): array;

    /** @return array{success:bool, reference:?string, message:string} */
    public function verify(array $payment, array $callbackData): array;
}
