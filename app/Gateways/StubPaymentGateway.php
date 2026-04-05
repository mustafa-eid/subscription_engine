<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Dto\PaymentResult;
use App\Dto\WebhookEvent;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * Stub Payment Gateway
 *
 * A simple payment gateway implementation for testing and development.
 * Simulates payment processing without requiring external payment providers.
 */
class StubPaymentGateway implements PaymentGatewayInterface
{
    /**
     * {@inheritdoc}
     */
    public function charge(Subscription $subscription, float $amount, string $currency): PaymentResult
    {
        Log::info('Stub payment processed', [
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return PaymentResult::success(
            transactionId: 'stub_' . uniqid('pay_'),
            status: 'succeeded',
            metadata: [
                'gateway' => 'stub',
                'subscription_id' => $subscription->id,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult
    {
        Log::info('Stub refund processed', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);

        return PaymentResult::success(
            transactionId: 'stub_refund_' . uniqid('ref_'),
            status: 'succeeded',
            metadata: [
                'gateway' => 'stub',
                'original_transaction_id' => $transactionId,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $payload, string $signature): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function handleWebhook(array $payload): WebhookEvent
    {
        $type = $payload['type'] ?? 'unknown';

        Log::info('Stub webhook received', [
            'type' => $type,
            'payload' => $payload,
        ]);

        return new WebhookEvent(
            type: $type,
            subscriptionId: $payload['subscription_id'] ?? null,
            transactionId: $payload['transaction_id'] ?? null,
            customerId: $payload['customer_id'] ?? null,
            amount: $payload['amount'] ?? null,
            currency: $payload['currency'] ?? null,
            status: $payload['status'] ?? null,
            rawPayload: $payload,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'stub';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return true;
    }
}
