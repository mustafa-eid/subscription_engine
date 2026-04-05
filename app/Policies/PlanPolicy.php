<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Plan Policy
 *
 * Defines authorization rules for plan-related operations.
 * Plans are publicly viewable, but management operations
 * require administrative privileges.
 */
class PlanPolicy
{
    /**
     * Determine if the user can view the plan.
     *
     * Plans are publicly accessible (no authentication required).
     */
    public function view(?User $user, Plan $plan): bool
    {
        return true;
    }

    /**
     * Determine if the user can view any plans.
     *
     * Anyone can view the list of active plans.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can create a plan.
     *
     * Only authenticated users can create plans.
     * In a production system, this would check for admin role.
     */
    public function create(User $user): bool
    {
        // TODO: Add role-based check when admin roles are implemented
        // return $user->hasRole('admin');
        return $user !== null;
    }

    /**
     * Determine if the user can update the plan.
     *
     * Only authenticated users can update plans.
     * In a production system, this would check for admin role.
     */
    public function update(User $user, Plan $plan): bool
    {
        // TODO: Add role-based check when admin roles are implemented
        // return $user->hasRole('admin');
        return $user !== null;
    }

    /**
     * Determine if the user can delete the plan.
     *
     * Only authenticated users can delete plans.
     * In a production system, this would check for admin role.
     */
    public function delete(User $user, Plan $plan): bool
    {
        // TODO: Add role-based check when admin roles are implemented
        // return $user->hasRole('admin');
        return $user !== null;
    }

    /**
     * Determine if the user can restore the plan.
     *
     * Only authenticated users can restore soft-deleted plans.
     */
    public function restore(User $user, Plan $plan): bool
    {
        // TODO: Add role-based check when admin roles are implemented
        return $user !== null;
    }

    /**
     * Determine if the user can permanently delete the plan.
     *
     * Only authenticated users can force delete plans.
     */
    public function forceDelete(User $user, Plan $plan): bool
    {
        // TODO: Add role-based check when admin roles are implemented
        // return $user->hasRole('admin');
        return $user !== null;
    }
}
