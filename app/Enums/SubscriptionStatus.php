<?php

namespace App\Enums;

/**
 * Subscription lifecycle statuses.
 *
 * Defines the possible states of a subscription throughout its lifetime:
 *   - TRIALING  → User is in the free trial period
 *   - ACTIVE    → Subscription is paid and fully operational
 *   - PAST_DUE  → Payment failed; user is in the grace period
 *   - CANCELED  → Subscription has ended (terminal state)
 */
enum SubscriptionStatus: string
{
    /** User is in the free trial period. */
    case TRIALING = 'trialing';

    /** Subscription is paid and fully operational. */
    case ACTIVE = 'active';

    /** Payment failed; user is in the grace period with access retained. */
    case PAST_DUE = 'past_due';

    /** Subscription has ended. This is a terminal state. */
    case CANCELED = 'canceled';

    /**
     * Get all possible status values as an array of strings.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Check if the subscription is considered active (trialing or active).
     *
     * These states represent subscriptions that are functioning normally.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::TRIALING, self::ACTIVE], true);
    }

    /**
     * Check if the subscription is in a terminal state.
     *
     * A terminal state means no further transitions are possible.
     */
    public function isTerminal(): bool
    {
        return $this === self::CANCELED;
    }
}
