<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested price configuration does not exist
 * for the given plan, currency, and billing cycle combination.
 *
 * This occurs when a user attempts to subscribe to a plan using
 * a currency or billing cycle that the plan does not support.
 *
 * HTTP Response: 404 Not Found
 */
class PriceNotFoundException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  int  $planId  The ID of the plan
     * @param  string  $currency  The requested currency code (e.g. 'usd')
     * @param  string  $billingCycle  The requested billing cycle (e.g. 'monthly')
     */
    public function __construct(int $planId, string $currency, string $billingCycle)
    {
        parent::__construct(
            "No price found for plan (ID: {$planId}) with currency '{$currency}' and billing cycle '{$billingCycle}'."
        );
    }
}
