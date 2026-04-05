<?php

namespace App\Dto;

/**
 * Payment Result DTO
 *
 * Represents the result of a payment operation.
 * This is a value object that encapsulates payment outcome data.
 */
final class PaymentResult
{
    /**
     * Create a new PaymentResult instance.
     *
     * @param bool $success Whether the payment was successful
     * @param string|null $transactionId The transaction ID from the payment gateway
     * @param string|null $errorMessage Error message if payment failed
     * @param string|null $status The payment status code from the gateway
     * @param array $metadata Additional metadata from the payment gateway
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $status = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Create a successful payment result.
     */
    public static function success(string $transactionId, string $status = 'succeeded', array $metadata = []): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            status: $status,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed payment result.
     */
    public static function failure(string $errorMessage, ?string $status = null): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            status: $status,
        );
    }

    /**
     * Check if the payment requires further action (e.g., 3D Secure).
     */
    public function requiresAction(): bool
    {
        return $this->status === 'requires_action' || $this->status === 'requires_source_action';
    }

    /**
     * Check if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }
}
