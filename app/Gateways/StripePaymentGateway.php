<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Dto\PaymentResult;
use App\Dto\WebhookEvent;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Payment Gateway
 *
 * Stub implementation of the Stripe payment gateway.
 * This is a placeholder that simulates payment processing.
 * Replace with actual Stripe SDK integration in production.
 */
class StripePaymentGateway implements PaymentGatewayInterface
{
    /**
     * Create a new StripePaymentGateway instance.
     */
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $webhookSecret = null,
    ) {
        $this->apiKey = $this->apiKey ?? config('subscriptions.payment.drivers.stripe.api_key');
        $this->webhookSecret = $this->webhookSecret ?? config('subscriptions.payment.drivers.stripe.webhook_secret');
    }

    /**
     * {@inheritdoc}
     */
    public function charge(Subscription $subscription, float $amount, string $currency): PaymentResult
    {
        try {
            // TODO: Implement actual Stripe charge
            // Example:
            // \Stripe\Stripe::setApiKey($this->apiKey);
            // $charge = \Stripe\Charge::create([
            //     'amount' => (int) ($amount * 100), // Convert to cents
            //     'currency' => $currency,
            //     'customer' => $subscription->user->stripe_customer_id,
            //     'description' => "Subscription #{$subscription->id}",
            // ]);

            Log::info('Stripe charge simulated', [
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            // Simulate successful payment
            return PaymentResult::success(
                transactionId: 'stripe_' . uniqid('ch_'),
                status: 'succeeded',
                metadata: [
                    'subscription_id' => $subscription->id,
                    'simulated' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Stripe charge failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failure(
                errorMessage: $e->getMessage(),
                status: 'failed'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult
    {
        try {
            // TODO: Implement actual Stripe refund
            // $refund = \Stripe\Refund::create([
            //     'charge' => $transactionId,
            //     'amount' => $amount ? (int) ($amount * 100) : null,
            // ]);

            Log::info('Stripe refund simulated', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            return PaymentResult::success(
                transactionId: 'refund_' . uniqid('re_'),
                status: 'succeeded',
                metadata: [
                    'original_transaction_id' => $transactionId,
                    'refunded_amount' => $amount,
                    'simulated' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Stripe refund failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failure(
                errorMessage: $e->getMessage(),
                status: 'failed'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $payload, string $signature): bool
    {
        // TODO: Implement actual Stripe webhook verification
        // try {
        //     \Stripe\Webhook::constructEvent(
        //         $payload,
        //         $signature,
        //         $this->webhookSecret
        //     );
        //     return true;
        // } catch (\Exception $e) {
        //     return false;
        // }

        // For development, always return true
        Log::info('Stripe webhook signature verification simulated');

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function handleWebhook(array $payload): WebhookEvent
    {
        // TODO: Parse actual Stripe webhook payload
        // Stripe webhook example:
        // {
        //     "type": "invoice.payment_succeeded",
        //     "data": {
        //         "object": {
        //             "subscription": "sub_xxx",
        //             "charge": "ch_xxx",
        //             "amount_paid": 999,
        //             "currency": "usd"
        //         }
        //     }
        // }

        $type = $payload['type'] ?? 'unknown';
        $data = $payload['data']['object'] ?? [];

        Log::info('Stripe webhook received', [
            'type' => $type,
            'payload' => $payload,
        ]);

        return new WebhookEvent(
            type: $this->normalizeEventType($type),
            subscriptionId: $data['subscription'] ?? null,
            transactionId: $data['charge'] ?? $data['id'] ?? null,
            customerId: $data['customer'] ?? null,
            amount: isset($data['amount_paid']) ? $data['amount_paid'] / 100 : null,
            currency: $data['currency'] ?? null,
            status: $data['status'] ?? null,
            rawPayload: $payload,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'stripe';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Normalize Stripe event types to a consistent format.
     */
    protected function normalizeEventType(string $type): string
    {
        return match ($type) {
            'invoice.payment_succeeded' => 'payment.succeeded',
            'invoice.payment_failed' => 'payment.failed',
            'customer.subscription.deleted' => 'subscription.canceled',
            'customer.subscription.updated' => 'subscription.updated',
            'customer.subscription.trial_will_end' => 'subscription.trial_ending',
            default => $type,
        };
    }
}
