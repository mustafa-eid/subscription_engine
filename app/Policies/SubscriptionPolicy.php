<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/**
 * Subscription Policy
 *
 * Defines authorization rules for subscription-related operations.
 * This policy ensures users can only manage their own subscriptions
 * and provides fine-grained control over subscription actions.
 */
class SubscriptionPolicy
{
    /**
     * Determine if the user can view the subscription.
     *
     * Users can only view their own subscriptions.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine if the user can view any subscriptions.
     *
     * Authenticated users can always access their subscription list endpoint.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can create a subscription.
     *
     * Users can create a subscription if they don't already have an active one.
     * This is checked at the service level, but we include it here for completeness.
     */
    public function create(User $user): bool
    {
        // The actual check is done in the service layer (AlreadySubscribedException)
        // but we allow the attempt here and let the service handle the validation
        return true;
    }

    /**
     * Determine if the user can cancel the subscription.
     *
     * Users can only cancel their own subscriptions, and only if
     * the subscription is not already canceled.
     */
    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id
            && $subscription->status->value !== 'canceled';
    }

    /**
     * Determine if the user can update the subscription.
     *
     * Users cannot directly update subscriptions.
     * Plan changes should go through the upgrade/downgrade flow.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        // Regular users cannot update subscriptions
        return false;
    }

    /**
     * Determine if the user can delete the subscription.
     *
     * Users cannot delete subscriptions directly;
     * they must use the cancel flow instead.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        // Regular users cannot delete subscriptions
        return false;
    }

    /**
     * Determine if the user can simulate payment success.
     *
     * Only the subscription owner can simulate payments (for testing).
     */
    public function simulatePayment(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine if the user can restore the subscription.
     *
     * Users cannot restore canceled subscriptions.
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return false;
    }

    /**
     * Determine if the user can force-delete the subscription.
     *
     * Users cannot permanently delete subscriptions.
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return false;
    }
}
