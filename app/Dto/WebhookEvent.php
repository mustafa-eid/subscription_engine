<?php

namespace App\Dto;

/**
 * Webhook Event DTO
 *
 * Represents a parsed webhook event from a payment gateway.
 * Normalizes events from different providers into a consistent format.
 */
final class WebhookEvent
{
    /**
     * Create a new WebhookEvent instance.
     *
     * @param string $type The normalized event type
     * @param string|null $subscriptionId The subscription ID from the gateway
     * @param string|null $transactionId The transaction ID
     * @param string|null $customerId The customer ID
     * @param float|null $amount The event amount
     * @param string|null $currency The currency code
     * @param string|null $status The payment status
     * @param array $rawPayload The original webhook payload
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $customerId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $status = null,
        public readonly array $rawPayload = [],
    ) {
    }

    /**
     * Check if this is a payment success event.
     */
    public function isPaymentSuccess(): bool
    {
        return in_array($this->type, [
            'payment.succeeded',
            'invoice.payment_succeeded',
            'checkout.order.completed',
        ]);
    }

    /**
     * Check if this is a payment failure event.
     */
    public function isPaymentFailure(): bool
    {
        return in_array($this->type, [
            'payment.failed',
            'invoice.payment_failed',
            'checkout.order.failed',
        ]);
    }

    /**
     * Check if this is a subscription cancellation event.
     */
    public function isSubscriptionCanceled(): bool
    {
        return in_array($this->type, [
            'subscription.canceled',
            'customer.subscription.deleted',
        ]);
    }

    /**
     * Check if this is a subscription update event.
     */
    public function isSubscriptionUpdated(): bool
    {
        return in_array($this->type, [
            'subscription.updated',
            'customer.subscription.updated',
        ]);
    }

    /**
     * Check if this is a trial ending soon event.
     */
    public function isTrialEnding(): bool
    {
        return in_array($this->type, [
            'customer.subscription.trial_will_end',
            'subscription.trial_ending',
        ]);
    }
}
