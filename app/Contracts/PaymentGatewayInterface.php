<?php

namespace App\Contracts;

use App\Models\Subscription;

/**
 * Payment Gateway Interface
 *
 * Contract for payment gateway implementations.
 * This abstraction allows switching between payment providers
 * (Stripe, Paddle, etc.) without changing business logic.
 */
interface PaymentGatewayInterface
{
    /**
     * Process a subscription payment.
     *
     * @param Subscription $subscription The subscription to charge
     * @param float $amount The amount to charge
     * @param string $currency The currency code
     * @return PaymentResult The result of the payment attempt
     */
    public function charge(Subscription $subscription, float $amount, string $currency): PaymentResult;

    /**
     * Process a refund for a previous payment.
     *
     * @param string $transactionId The original transaction ID
     * @param float|null $amount The amount to refund (null for full refund)
     * @return PaymentResult The result of the refund attempt
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult;

    /**
     * Verify a webhook signature.
     *
     * @param array $payload The webhook payload
     * @param string $signature The signature to verify
     * @return bool Whether the signature is valid
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Handle webhook events.
     *
     * @param array $payload The webhook payload
     * @return WebhookEvent The parsed webhook event
     */
    public function handleWebhook(array $payload): WebhookEvent;

    /**
     * Get the gateway's name.
     *
     * @return string The gateway name
     */
    public function getName(): string;

    /**
     * Check if the gateway is configured and ready.
     *
     * @return bool Whether the gateway is ready
     */
    public function isConfigured(): bool;
}
