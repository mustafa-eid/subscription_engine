<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Subscription model instances.
 *
 * Provides comprehensive state methods for creating subscriptions
 * in every possible lifecycle state (trialing, active, past_due,
 * canceled) with realistic date configurations for testing.
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Subscription>
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * Creates an active subscription that started 30 days ago.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::ACTIVE,
            'currency' => fake()->randomElement(Currency::values()),
            'billing_cycle' => fake()->randomElement(BillingCycle::values()),
            'price' => fake()->randomFloat(2, 5, 200),
            'starts_at' => now()->subDays(30),
        ];
    }

    /**
     * Indicate that the subscription is in an active trial period.
     *
     * @param  int  $trialDaysRemaining  Days remaining in the trial (default: 7)
     * @return static
     */
    public function trialing(int $trialDaysRemaining = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->addDays($trialDaysRemaining),
            'starts_at' => now()->subDays(14 - $trialDaysRemaining),
        ]);
    }

    /**
     * Indicate that the subscription's trial has expired.
     *
     * The subscription is still in 'trialing' status but the
     * trial_ends_at timestamp is in the past.
     *
     * @param  int  $daysAgo  How many days ago the trial expired (default: 1)
     * @return static
     */
    public function expiredTrial(int $daysAgo = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->subDays($daysAgo),
            'starts_at' => now()->subDays(14 + $daysAgo),
        ]);
    }

    /**
     * Indicate that the subscription is active (no trial).
     *
     * @return static
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::ACTIVE,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the subscription is past due (payment failed).
     *
     * The grace period is still active, so the user retains access.
     *
     * @param  int  $graceDaysRemaining  Days remaining in the grace period (default: 2)
     * @return static
     */
    public function pastDue(int $graceDaysRemaining = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_period_ends_at' => now()->addDays($graceDaysRemaining),
        ]);
    }

    /**
     * Indicate that the subscription's grace period has expired.
     *
     * The subscription is still in 'past_due' status but the
     * grace_period_ends_at timestamp is in the past.
     *
     * @param  int  $daysAgo  How many days ago the grace period expired (default: 1)
     * @return static
     */
    public function expiredGrace(int $daysAgo = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_period_ends_at' => now()->subDays($daysAgo),
        ]);
    }

    /**
     * Indicate that the subscription has been canceled.
     *
     * @return static
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::CANCELED,
            'ends_at' => now()->subDays(5),
            'grace_period_ends_at' => null,
        ]);
    }
}
