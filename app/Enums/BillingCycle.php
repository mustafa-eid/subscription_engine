<?php

namespace App\Enums;

/**
 * Billing cycle types for subscription plans.
 *
 * Defines the frequency at which a subscription is billed.
 * Each plan can have separate prices for each billing cycle.
 */
enum BillingCycle: string
{
    /** Monthly billing — charged every month. */
    case MONTHLY = 'monthly';

    /** Yearly billing — charged once per year (typically at a discount). */
    case YEARLY = 'yearly';

    /**
     * Get all possible billing cycle values as an array of strings.
     *
     * Useful for validation rules and database seeders.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
