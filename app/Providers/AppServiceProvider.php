<?php

namespace App\Providers;

use App\Repositories\EloquentPlanRepository;
use App\Repositories\EloquentSubscriptionRepository;
use App\Repositories\PlanRepositoryInterface;
use App\Repositories\SubscriptionRepositoryInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Application Service Provider
 *
 * Registers application-wide service bindings.
 * This is where the Repository Pattern interfaces are bound
 * to their Eloquent implementations, enabling dependency
 * injection throughout the application.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Binds repository interfaces to their concrete Eloquent
     * implementations so they can be type-hinted in constructors.
     */
    public function register(): void
    {
        // Bind repository interfaces to their Eloquent implementations
        $this->app->bind(PlanRepositoryInterface::class, EloquentPlanRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, EloquentSubscriptionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
