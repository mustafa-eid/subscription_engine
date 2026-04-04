<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Plan model instances.
 *
 * Provides default plan data and convenient state methods
 * for creating plans with specific configurations (with trial,
 * without trial, inactive).
 *
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Plan>
     */
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' Plan',
            'description' => fake()->sentence(),
            'trial_days' => fake()->randomElement([0, 7, 14, 30]),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the plan should have a trial period.
     *
     * @param  int  $days  Number of trial days (default: 14)
     * @return static
     */
    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_days' => $days,
        ]);
    }

    /**
     * Indicate that the plan should not have a trial period.
     *
     * @return static
     */
    public function withoutTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_days' => 0,
        ]);
    }

    /**
     * Indicate that the plan should be inactive.
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
