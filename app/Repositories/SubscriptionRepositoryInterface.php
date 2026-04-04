<?php

namespace App\Repositories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contract for Subscription data access operations.
 */
interface SubscriptionRepositoryInterface
{
    /**
     * Create a new subscription.
     */
    public function create(array $data): Subscription;

    /**
     * Update an existing subscription.
     */
    public function update(Subscription $subscription, array $data): bool;

    /**
     * Find a subscription by ID.
     */
    public function findById(int $id): ?Subscription;

    /**
     * Find a subscription by ID or throw an exception.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Subscription;

    /**
     * Get the active (or trialing) subscription for a user.
     */
    public function findActiveForUser(int $userId): ?Subscription;

    /**
     * Get all subscriptions for a user.
     */
    public function findByUser(int $userId): Collection;

    /**
     * Get subscriptions with expired trials that are still in 'trialing' status.
     */
    public function getExpiredTrials(): Collection;

    /**
     * Get subscriptions in past_due status with expired grace period.
     */
    public function getExpiredGracePeriod(): Collection;

    /**
     * Paginate all subscriptions with eager-loaded relations.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Update the subscription status.
     */
    public function updateStatus(Subscription $subscription, SubscriptionStatus $status): bool;
}
