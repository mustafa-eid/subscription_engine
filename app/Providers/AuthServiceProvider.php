<?php

namespace App\Providers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Policies\PlanPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Auth Service Provider
 *
 * Registers policy mappings for model authorization.
 * This enables the use of Gate::authorize() and @can directives
 * with model instances for automatic policy resolution.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Plan::class => PlanPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Policies are automatically registered by Laravel's discovery
        // but we explicitly register them here for clarity
        $this->registerPolicies();
    }
}
