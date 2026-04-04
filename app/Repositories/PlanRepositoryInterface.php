<?php

namespace App\Repositories;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contract for Plan data access operations.
 */
interface PlanRepositoryInterface
{
    /**
     * Get all active plans with their prices eager-loaded.
     */
    public function allActive(): Collection;

    /**
     * Get all plans (including inactive) with prices eager-loaded.
     */
    public function all(): Collection;

    /**
     * Find a plan by ID with prices eager-loaded.
     */
    public function findById(int $id): ?Plan;

    /**
     * Find a plan by ID or throw an exception.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Plan;

    /**
     * Get the price for a specific plan, currency, and billing cycle.
     */
    public function getPriceFor(int $planId, string $currency, string $billingCycle): ?PlanPrice;

    /**
     * Paginate all active plans with prices.
     */
    public function paginateActive(int $perPage = 15): LengthAwarePaginator;
}
