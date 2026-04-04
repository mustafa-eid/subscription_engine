<?php

namespace App\Events;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a subscription transitions to the ACTIVE state.
 *
 * This event is dispatched in three scenarios:
 *   1. A new subscription is created for a plan without a trial period
 *   2. A trial period expires and the subscription is automatically activated
 *   3. A payment succeeds during the grace period (recovery from past_due)
 *
 * Listeners can use this event to:
 *   - Send welcome or reactivation emails
 *   - Provision or re-enable premium resources
 *   - Update usage quotas
 *   - Notify external billing or analytics systems
 */
class SubscriptionActivated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Subscription  $subscription  The subscription that was activated
     * @param  int  $userId  The ID of the user who owns the subscription
     * @param  SubscriptionStatus  $previousStatus  The status before this transition
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $userId,
        public readonly SubscriptionStatus $previousStatus,
    ) {
    }
}
