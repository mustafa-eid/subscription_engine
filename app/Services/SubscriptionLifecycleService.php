<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionActivated;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionPastDue;
use App\Exceptions\AlreadySubscribedException;
use App\Exceptions\InvalidSubscriptionStateException;
use App\Exceptions\PlanNotFoundException;
use App\Exceptions\PriceNotFoundException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Repositories\PlanRepositoryInterface;
use App\Repositories\SubscriptionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SubscriptionLifecycleService
 *
 * Central service that manages the entire subscription lifecycle.
 * All business logic for subscription state transitions lives here.
 *
 * State Machine:
 *   trialing → active       (trial expires, payment succeeds)
 *   active   → past_due     (payment fails)
 *   past_due → active       (payment succeeds during grace)
 *   past_due → canceled     (grace period expires)
 *   active   → canceled     (user cancels)
 *   trialing → canceled     (user cancels during trial)
 *
 * Idempotency:
 *   The subscribe() method is designed to be idempotent at the application
 *   level (duplicate check before creation) and at the database level
 *   (unique constraint on active_user_id generated column). This prevents
 *   race conditions from double-clicks or network retries.
 */
class SubscriptionLifecycleService
{
    /**
     * Number of days for the grace period after a payment failure.
     */
    private const GRACE_PERIOD_DAYS = 3;

    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
    ) {
    }

    // =========================================================================
    //  PUBLIC API — Subscription Lifecycle
    // =========================================================================

    /**
     * Subscribe a user to a plan.
     *
     * Business rules:
     *  - User must not already have an active/trialing/past_due subscription.
     *  - Plan must exist and have a price for the given currency + billing cycle.
     *  - If the plan has a trial period, the subscription starts as 'trialing'.
     *  - Otherwise, it starts as 'active' immediately.
     *
     * Idempotency:
     *  - Application-level guard checks for existing active subscription.
     *  - Database-level unique constraint on (active_user_id) provides
     *    a safety net for concurrent requests that bypass the application guard.
     *
     * @param  int  $userId        The user subscribing
     * @param  int  $planId        The plan to subscribe to
     * @param  string  $currency   Currency code (e.g. 'usd', 'aed', 'egp')
     * @param  string  $billingCycle  Billing cycle (e.g. 'monthly', 'yearly')
     *
     * @throws AlreadySubscribedException   If user already has an active subscription
     * @throws PlanNotFoundException        If the plan does not exist
     * @throws PriceNotFoundException       If no price exists for the given currency + cycle
     */
    public function subscribe(int $userId, int $planId, string $currency, string $billingCycle): Subscription
    {
        return DB::transaction(function () use ($userId, $planId, $currency, $billingCycle) {
            // --- Pre-flight checks ---

            // Application-level idempotency guard: prevent duplicate subscriptions.
            // This is the first line of defense against double-clicks or retries.
            $existingSubscription = $this->subscriptionRepository->findActiveForUser($userId);

            if ($existingSubscription !== null) {
                Log::info('Subscription attempt blocked — user already has an active subscription.', [
                    'user_id' => $userId,
                    'existing_subscription_id' => $existingSubscription->id,
                    'existing_status' => $existingSubscription->status->value,
                ]);

                throw new AlreadySubscribedException($userId);
            }

            // Validate plan exists
            $plan = $this->planRepository->findById($planId);

            if ($plan === null) {
                throw new PlanNotFoundException($planId);
            }

            // Validate price exists for the requested currency + billing cycle
            $planPrice = $this->planRepository->getPriceFor($planId, $currency, $billingCycle);

            if ($planPrice === null) {
                throw new PriceNotFoundException($planId, $currency, $billingCycle);
            }

            // --- Determine initial state ---

            $now = now();
            $status = SubscriptionStatus::ACTIVE;
            $trialEndsAt = null;
            $startsAt = $now;

            // If the plan has a trial period, start in 'trialing' state
            if ($plan->hasTrial()) {
                $status = SubscriptionStatus::TRIALING;
                $trialEndsAt = $now->copy()->addDays($plan->trial_days);
            }

            // --- Create the subscription ---

            $subscription = $this->subscriptionRepository->create([
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'status' => $status->value,
                'currency' => $currency,
                'billing_cycle' => $billingCycle,
                'price' => $planPrice->price,
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $startsAt,
                'ends_at' => null,
                'grace_period_ends_at' => null,
            ]);

            Log::info('Subscription created.', [
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
                'plan_id' => $planId,
                'status' => $status->value,
                'currency' => $currency,
                'billing_cycle' => $billingCycle,
                'price' => $planPrice->price,
                'trial_ends_at' => $trialEndsAt?->toIso8601String(),
            ]);

            // Dispatch event if the subscription starts as active (no trial)
            if ($status === SubscriptionStatus::ACTIVE) {
                event(new SubscriptionActivated(
                    $subscription,
                    $userId,
                    SubscriptionStatus::TRIALING // previous state conceptually
                ));
            }

            return $subscription;
        });
    }

    /**
     * Handle trial expiration — transition from 'trialing' to 'active'.
     *
     * Called by the scheduler when a trial period has ended.
     * Simulates a successful payment to move the subscription to active.
     *
     * @throws InvalidSubscriptionStateException  If the subscription is not in 'trialing' state
     */
    public function handleTrialExpiration(Subscription $subscription): Subscription
    {
        // Guard: subscription must be in trialing state
        if ($subscription->status !== SubscriptionStatus::TRIALING) {
            throw new InvalidSubscriptionStateException(
                'handleTrialExpiration',
                $subscription->status->value,
                [SubscriptionStatus::TRIALING->value]
            );
        }

        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;

            // Transition to active — simulating successful payment after trial
            $this->subscriptionRepository->update($subscription, [
                'status' => SubscriptionStatus::ACTIVE->value,
                'trial_ends_at' => null, // Clear trial end date as it has been consumed
            ]);

            // Reload to get fresh state
            $subscription = $this->subscriptionRepository->findById($subscription->id);

            Log::info('Trial expired — subscription activated.', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'old_status' => $oldStatus->value,
                'new_status' => SubscriptionStatus::ACTIVE->value,
            ]);

            event(new SubscriptionActivated($subscription, $subscription->user_id, $oldStatus));

            return $subscription;
        });
    }

    /**
     * Handle payment failure — transition to 'past_due' with a grace period.
     *
     * The user retains access during the grace period (3 days).
     * If payment is not made within the grace period, the subscription
     * will be canceled by the scheduler.
     *
     * @throws InvalidSubscriptionStateException  If the subscription is not in 'active' or 'past_due' state
     */
    public function handlePaymentFailure(Subscription $subscription): Subscription
    {
        // Only active or already past_due subscriptions can have payment failures
        if (! in_array($subscription->status, [SubscriptionStatus::ACTIVE, SubscriptionStatus::PAST_DUE], true)) {
            throw new InvalidSubscriptionStateException(
                'handlePaymentFailure',
                $subscription->status->value,
                [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::PAST_DUE->value]
            );
        }

        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;
            $gracePeriodEndsAt = now()->addDays(self::GRACE_PERIOD_DAYS);

            $this->subscriptionRepository->update($subscription, [
                'status' => SubscriptionStatus::PAST_DUE->value,
                'grace_period_ends_at' => $gracePeriodEndsAt,
            ]);

            // Reload to get fresh state
            $subscription = $this->subscriptionRepository->findById($subscription->id);

            Log::warning('Payment failed — subscription moved to past_due.', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'old_status' => $oldStatus->value,
                'new_status' => SubscriptionStatus::PAST_DUE->value,
                'grace_period_ends_at' => $gracePeriodEndsAt->toIso8601String(),
            ]);

            event(new SubscriptionPastDue($subscription, $subscription->user_id, $oldStatus));

            return $subscription;
        });
    }

    /**
     * Handle successful payment — transition from 'past_due' back to 'active'.
     *
     * Clears the grace period and restores full access.
     *
     * @throws InvalidSubscriptionStateException  If the subscription is not in 'past_due' state
     */
    public function handlePaymentSuccess(Subscription $subscription): Subscription
    {
        // Only past_due subscriptions can recover via payment
        if ($subscription->status !== SubscriptionStatus::PAST_DUE) {
            throw new InvalidSubscriptionStateException(
                'handlePaymentSuccess',
                $subscription->status->value,
                [SubscriptionStatus::PAST_DUE->value]
            );
        }

        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;

            $this->subscriptionRepository->update($subscription, [
                'status' => SubscriptionStatus::ACTIVE->value,
                'grace_period_ends_at' => null,
            ]);

            // Reload to get fresh state
            $subscription = $this->subscriptionRepository->findById($subscription->id);

            Log::info('Payment succeeded — subscription reactivated.', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'old_status' => $oldStatus->value,
                'new_status' => SubscriptionStatus::ACTIVE->value,
            ]);

            event(new SubscriptionActivated($subscription, $subscription->user_id, $oldStatus));

            return $subscription;
        });
    }

    /**
     * Handle grace period expiration — transition from 'past_due' to 'canceled'.
     *
     * Called by the scheduler when the grace period has ended without payment.
     * Sets the ends_at timestamp to mark the official end of the subscription.
     *
     * @throws InvalidSubscriptionStateException  If the subscription is not in 'past_due' state
     */
    public function handleGracePeriodExpiration(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::PAST_DUE) {
            throw new InvalidSubscriptionStateException(
                'handleGracePeriodExpiration',
                $subscription->status->value,
                [SubscriptionStatus::PAST_DUE->value]
            );
        }

        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;

            $this->subscriptionRepository->update($subscription, [
                'status' => SubscriptionStatus::CANCELED->value,
                'ends_at' => now(),
                'grace_period_ends_at' => null,
            ]);

            // Reload to get fresh state
            $subscription = $this->subscriptionRepository->findById($subscription->id);

            Log::warning('Grace period expired — subscription canceled.', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'old_status' => $oldStatus->value,
                'new_status' => SubscriptionStatus::CANCELED->value,
            ]);

            event(new SubscriptionCanceled($subscription, $subscription->user_id, $oldStatus));

            return $subscription;
        });
    }

    /**
     * Cancel a subscription immediately.
     *
     * Can be called by the user or an admin. Sets the ends_at timestamp
     * and marks the subscription as canceled.
     *
     * @throws InvalidSubscriptionStateException  If the subscription is already canceled
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::CANCELED) {
            throw new InvalidSubscriptionStateException(
                'cancel',
                $subscription->status->value,
                [
                    SubscriptionStatus::TRIALING->value,
                    SubscriptionStatus::ACTIVE->value,
                    SubscriptionStatus::PAST_DUE->value,
                ]
            );
        }

        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;

            $this->subscriptionRepository->update($subscription, [
                'status' => SubscriptionStatus::CANCELED->value,
                'ends_at' => now(),
                'grace_period_ends_at' => null,
            ]);

            // Reload to get fresh state
            $subscription = $this->subscriptionRepository->findById($subscription->id);

            Log::info('Subscription canceled.', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'old_status' => $oldStatus->value,
                'new_status' => SubscriptionStatus::CANCELED->value,
            ]);

            event(new SubscriptionCanceled($subscription, $subscription->user_id, $oldStatus));

            return $subscription;
        });
    }

    // =========================================================================
    //  SCHEDULER HELPERS
    // =========================================================================

    /**
     * Process all expired trials — transition them to active.
     *
     * Called by the ProcessSubscriptions scheduled command.
     *
     * @return int Number of subscriptions processed
     */
    public function processExpiredTrials(): int
    {
        $expiredTrials = $this->subscriptionRepository->getExpiredTrials();
        $processed = 0;

        foreach ($expiredTrials as $subscription) {
            try {
                $this->handleTrialExpiration($subscription);
                $processed++;
            } catch (InvalidSubscriptionStateException $e) {
                // Race condition: another process may have already handled this.
                // Log and skip — don't fail the entire batch.
                Log::warning('Failed to process expired trial.', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process all expired grace periods — cancel them.
     *
     * Called by the ProcessSubscriptions scheduled command.
     *
     * @return int Number of subscriptions processed
     */
    public function processExpiredGracePeriods(): int
    {
        $expiredGrace = $this->subscriptionRepository->getExpiredGracePeriod();
        $processed = 0;

        foreach ($expiredGrace as $subscription) {
            try {
                $this->handleGracePeriodExpiration($subscription);
                $processed++;
            } catch (InvalidSubscriptionStateException $e) {
                // Race condition: another process may have already handled this.
                // Log and skip — don't fail the entire batch.
                Log::warning('Failed to process expired grace period.', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }
}
