<?php

namespace App\Enums;

/**
 * Supported currencies for plan pricing.
 *
 * Each plan can define separate prices per currency,
 * enabling multi-region pricing strategies.
 */
enum Currency: string
{
    /** US Dollar — primary international currency. */
    case USD = 'usd';

    /** UAE Dirham — United Arab Emirates currency. */
    case AED = 'aed';

    /** Egyptian Pound — Egypt currency. */
    case EGP = 'egp';

    /**
     * Get all possible currency values as an array of strings.
     *
     * Useful for validation rules and database seeders.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get the currency symbol for display purposes.
     *
     * @return non-empty-string
     */
    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::AED => 'د.إ',
            self::EGP => 'ج.م',
        };
    }
}
