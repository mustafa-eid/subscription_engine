<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating PlanPrice model instances.
 *
 * Provides random pricing data and convenient state methods
 * for creating prices with specific currencies or billing cycles.
 *
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PlanPrice>
     */
    protected $model = PlanPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency' => fake()->randomElement(Currency::values()),
            'billing_cycle' => fake()->randomElement(BillingCycle::values()),
            'price' => fake()->randomFloat(2, 5, 200),
        ];
    }

    /**
     * Set the currency to USD.
     *
     * @return static
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => Currency::USD,
        ]);
    }

    /**
     * Set the billing cycle to monthly.
     *
     * @return static
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);
    }

    /**
     * Set the billing cycle to yearly.
     *
     * @return static
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_cycle' => BillingCycle::YEARLY,
        ]);
    }
}
