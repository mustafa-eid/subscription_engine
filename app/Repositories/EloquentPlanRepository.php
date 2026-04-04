<?php

namespace App\Repositories;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent-based implementation of the Plan repository.
 *
 * Handles all Plan and PlanPrice data access with eager loading
 * to prevent N+1 query issues.
 */
class EloquentPlanRepository implements PlanRepositoryInterface
{
    /**
     * Get all active plans with their prices eager-loaded.
     */
    public function allActive(): Collection
    {
        return Plan::query()
            ->with('prices')
            ->active()
            ->get();
    }

    /**
     * Get all plans (including inactive) with prices eager-loaded.
     */
    public function all(): Collection
    {
        return Plan::query()
            ->with('prices')
            ->get();
    }

    /**
     * Find a plan by ID with prices eager-loaded.
     * Returns null if not found.
     */
    public function findById(int $id): ?Plan
    {
        return Plan::query()
            ->with('prices')
            ->find($id);
    }

    /**
     * Find a plan by ID or throw ModelNotFoundException.
     */
    public function findByIdOrFail(int $id): Plan
    {
        return Plan::query()
            ->with('prices')
            ->findOrFail($id);
    }

    /**
     * Get the price for a specific plan, currency, and billing cycle.
     *
     * Returns null if no matching price configuration exists.
     */
    public function getPriceFor(int $planId, string $currency, string $billingCycle): ?PlanPrice
    {
        return PlanPrice::query()
            ->where('plan_id', $planId)
            ->where('currency', $currency)
            ->where('billing_cycle', $billingCycle)
            ->first();
    }

    /**
     * Paginate all active plans with prices.
     *
     * Useful for API endpoints that need pagination support.
     */
    public function paginateActive(int $perPage = 15): LengthAwarePaginator
    {
        return Plan::query()
            ->with('prices')
            ->active()
            ->paginate($perPage);
    }
}
