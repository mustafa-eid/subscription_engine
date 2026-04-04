<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the v1 API endpoints.
 *
 * Verifies:
 *  - Correct route prefixes (/api/v1/...)
 *  - ApiResponse wrapper format (status, message, data)
 *  - Pagination on plans index
 *  - Authentication requirements
 *  - Validation errors
 *  - Proper HTTP status codes
 */
class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->plan = Plan::factory()->withTrial(14)->create();
        $this->plan->prices()->createMany([
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 9.99],
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::YEARLY, 'price' => 99.99],
            ['currency' => Currency::AED, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 36.99],
        ]);

        // Define the 'api' rate limiter for tests (normally defined in bootstrap/app.php)
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }

    // =========================================================================
    //  PLANS ENDPOINTS
    // =========================================================================

    #[Test]
    public function get_plans_returns_paginated_response_with_api_wrapper(): void
    {
        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Plans retrieved successfully.')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'trial_days',
                        'has_trial',
                        'is_active',
                        'prices',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    #[Test]
    public function get_plans_respects_per_page_parameter(): void
    {
        // Create additional plans
        Plan::factory()->count(5)->create()->each(function ($plan) {
            $plan->prices()->create([
                'currency' => Currency::USD,
                'billing_cycle' => BillingCycle::MONTHLY,
                'price' => 19.99,
            ]);
        });

        $response = $this->getJson('/api/v1/plans?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 6); // 1 from setUp + 5 created
    }

    #[Test]
    public function get_single_plan_returns_correct_structure(): void
    {
        $response = $this->getJson("/api/v1/plans/{$this->plan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $this->plan->id)
            ->assertJsonPath('data.name', $this->plan->name);
    }

    #[Test]
    public function get_nonexistent_plan_returns_404(): void
    {
        $response = $this->getJson('/api/v1/plans/9999');

        $response->assertStatus(404);
    }

    #[Test]
    public function create_plan_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/plans', [
            'name' => 'Test Plan',
            'trial_days' => 7,
            'prices' => [
                ['currency' => 'usd', 'billing_cycle' => 'monthly', 'price' => 19.99],
            ],
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function create_plan_validates_input(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/plans', [
                // Missing required fields
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'trial_days', 'prices']);
    }

    #[Test]
    public function create_plan_succeeds_with_valid_data(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/plans', [
                'name' => 'Premium Plan',
                'description' => 'A premium plan',
                'trial_days' => 30,
                'is_active' => true,
                'prices' => [
                    ['currency' => 'usd', 'billing_cycle' => 'monthly', 'price' => 29.99],
                    ['currency' => 'aed', 'billing_cycle' => 'yearly', 'price' => 299.99],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Plan created successfully.')
            ->assertJsonPath('data.name', 'Premium Plan')
            ->assertJsonPath('data.trial_days', 30)
            ->assertJsonCount(2, 'data.prices');

        $this->assertDatabaseCount('plans', 2); // existing + new
        $this->assertDatabaseCount('plan_prices', 5); // 3 existing + 2 new
    }

    #[Test]
    public function update_plan_modifies_attributes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/plans/{$this->plan->id}", [
                'name' => 'Updated Plan Name',
                'trial_days' => 7,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Updated Plan Name')
            ->assertJsonPath('data.trial_days', 7);
    }

    #[Test]
    public function delete_plan_soft_deletes_it(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/plans/{$this->plan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Plan deleted successfully.');

        $this->assertSoftDeleted('plans', ['id' => $this->plan->id]);
    }

    // =========================================================================
    //  SUBSCRIPTION ENDPOINTS
    // =========================================================================

    #[Test]
    public function subscribe_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/subscriptions/subscribe', [
            'plan_id' => $this->plan->id,
            'currency' => 'usd',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function subscribe_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id', 'currency', 'billing_cycle']);
    }

    #[Test]
    public function subscribe_creates_trialing_subscription_for_plan_with_trial(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', [
                'plan_id' => $this->plan->id,
                'currency' => 'usd',
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Subscription created successfully.')
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.price', '9.99')
            ->assertJsonPath('data.has_access', true);
    }

    #[Test]
    public function subscribe_returns_409_for_duplicate_subscription(): void
    {
        // First subscription
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', [
                'plan_id' => $this->plan->id,
                'currency' => 'usd',
                'billing_cycle' => 'monthly',
            ])->assertStatus(201);

        // Second attempt — should be rejected
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', [
                'plan_id' => $this->plan->id,
                'currency' => 'usd',
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error', 'already_subscribed');
    }

    #[Test]
    public function list_subscriptions_returns_user_subscriptions(): void
    {
        Subscription::factory()
            ->for($this->user)
            ->for($this->plan)
            ->active()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscriptions');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'plan',
                        'status',
                        'currency',
                        'billing_cycle',
                        'price',
                        'has_access',
                    ],
                ],
            ]);
    }

    #[Test]
    public function cancel_subscription_changes_status_to_canceled(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->plan)
            ->active()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Subscription canceled successfully.')
            ->assertJsonPath('data.status', 'canceled');
    }

    #[Test]
    public function simulate_payment_failure_moves_to_past_due(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->plan)
            ->active()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/simulate-payment-failure");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'past_due')
            ->assertJsonPath('data.has_access', true); // Still in grace
    }

    #[Test]
    public function simulate_payment_success_recovers_past_due(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->plan)
            ->pastDue(2)
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/simulate-payment-success");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.has_access', true);
    }

    #[Test]
    public function user_cannot_cancel_another_users_subscription(): void
    {
        $otherUser = User::factory()->create();

        $subscription = Subscription::factory()
            ->for($otherUser)
            ->for($this->plan)
            ->active()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(403);
    }

    // =========================================================================
    //  API RESPONSE FORMAT CONSISTENCY
    // =========================================================================

    #[Test]
    public function all_success_responses_contain_status_field(): void
    {
        $endpoints = [
            ['GET', '/api/v1/plans'],
            ['GET', "/api/v1/plans/{$this->plan->id}"],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertJsonPath('status', 'success');
        }
    }

    #[Test]
    public function error_responses_contain_status_and_message(): void
    {
        // 404 — plan not found
        $response = $this->getJson('/api/v1/plans/9999');
        $response->assertStatus(404);

        // 409 — already subscribed (our custom exception, guaranteed to have status field)
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', [
                'plan_id' => $this->plan->id,
                'currency' => 'usd',
                'billing_cycle' => 'monthly',
            ])->assertStatus(201);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', [
                'plan_id' => $this->plan->id,
                'currency' => 'usd',
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error', 'already_subscribed');

        // 422 — validation error
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', []);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['plan_id', 'currency', 'billing_cycle']]);
    }
}
