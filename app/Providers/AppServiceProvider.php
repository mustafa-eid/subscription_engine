<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Gateways\PaymentGatewayManager;
use App\Gateways\StripePaymentGateway;
use App\Gateways\StubPaymentGateway;
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

        // Bind payment gateway manager
        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            return new PaymentGatewayManager();
        });

        // Bind payment gateway interface to manager's default driver
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->getDefaultDriver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
