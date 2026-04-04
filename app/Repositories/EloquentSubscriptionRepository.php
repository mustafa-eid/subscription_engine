<?php

namespace App\Repositories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent-based implementation of the Subscription repository.
 *
 * Handles all Subscription data access including lifecycle-related
 * queries (expired trials, expired grace periods).
 */
class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    /**
     * Create a new subscription.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Subscription
    {
        return Subscription::create($data);
    }

    /**
     * Update an existing subscription with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Subscription $subscription, array $data): bool
    {
        return $subscription->update($data);
    }

    /**
     * Find a subscription by ID. Returns null if not found.
     */
    public function findById(int $id): ?Subscription
    {
        return Subscription::query()
            ->with(['plan', 'plan.prices', 'user'])
            ->find($id);
    }

    /**
     * Find a subscription by ID or throw ModelNotFoundException.
     */
    public function findByIdOrFail(int $id): Subscription
    {
        return Subscription::query()
            ->with(['plan', 'plan.prices', 'user'])
            ->findOrFail($id);
    }

    /**
     * Get the active (or trialing) subscription for a user.
     *
     * Returns null if the user has no active/trialing subscription.
     */
    public function findActiveForUser(int $userId): ?Subscription
    {
        return Subscription::query()
            ->with(['plan', 'plan.prices'])
            ->where('user_id', $userId)
            ->whereIn('status', [
                SubscriptionStatus::TRIALING->value,
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::PAST_DUE->value,
            ])
            ->latest()
            ->first();
    }

    /**
     * Get all subscriptions for a user, ordered by newest first.
     */
    public function findByUser(int $userId): Collection
    {
        return Subscription::query()
            ->with(['plan', 'plan.prices'])
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    /**
     * Get subscriptions with expired trials that are still in 'trialing' status.
     *
     * These are subscriptions where trial_ends_at has passed but the status
     * hasn't been transitioned to 'active' yet.
     */
    public function getExpiredTrials(): Collection
    {
        return Subscription::query()
            ->with(['plan', 'user'])
            ->where('status', SubscriptionStatus::TRIALING->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();
    }

    /**
     * Get subscriptions in past_due status with expired grace period.
     *
     * These are subscriptions where grace_period_ends_at has passed
     * and should be canceled.
     */
    public function getExpiredGracePeriod(): Collection
    {
        return Subscription::query()
            ->with(['plan', 'user'])
            ->where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->get();
    }

    /**
     * Paginate all subscriptions with eager-loaded relations.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Subscription::query()
            ->with(['plan', 'user'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Update the subscription status.
     *
     * This is a focused method for status-only updates to keep
     * the business logic in the service layer.
     */
    public function updateStatus(Subscription $subscription, SubscriptionStatus $status): bool
    {
        return $subscription->update([
            'status' => $status->value,
        ]);
    }
}
