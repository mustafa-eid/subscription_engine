<?php

namespace App\Events;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a subscription transitions to the CANCELED state.
 *
 * This event is dispatched in two scenarios:
 *   1. The user voluntarily cancels their subscription via the API
 *   2. The grace period expires without payment (automatic cancellation by scheduler)
 *
 * Listeners can use this event to:
 *   - Send cancellation confirmation emails
 *   - Revoke access to premium features
 *   - Trigger offboarding workflows
 *   - Update analytics and churn metrics
 */
class SubscriptionCanceled
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Subscription  $subscription  The subscription that was canceled
     * @param  int  $userId  The ID of the user who owned the subscription
     * @param  SubscriptionStatus  $previousStatus  The status before this transition
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $userId,
        public readonly SubscriptionStatus $previousStatus,
    ) {
    }
}
