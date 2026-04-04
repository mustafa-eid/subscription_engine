<?php

namespace App\Events;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a subscription transitions to the PAST_DUE state.
 *
 * This event is dispatched when a payment fails for an active subscription.
 * The user enters a 3-day grace period during which they retain full access
 * while attempting to resolve the payment issue.
 *
 * Listeners can use this event to:
 *   - Send payment failure notification emails
 *   - Alert the billing team for manual review
 *   - Trigger dunning management workflows
 *   - Log for financial reconciliation
 */
class SubscriptionPastDue
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Subscription  $subscription  The subscription that entered past_due state
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
