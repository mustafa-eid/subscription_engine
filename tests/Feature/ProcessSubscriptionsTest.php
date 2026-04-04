<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionActivated;
use App\Events\SubscriptionCanceled;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private Plan $planWithTrial;
    private Plan $planWithoutTrial;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a plan with a 14-day trial
        $this->planWithTrial = Plan::create([
            'name' => 'Starter',
            'description' => 'Starter plan with trial',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $this->planWithTrial->prices()->createMany([
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 9.99],
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::YEARLY, 'price' => 99.99],
        ]);

        // Create a plan without trial
        $this->planWithoutTrial = Plan::create([
            'name' => 'Enterprise',
            'description' => 'Enterprise plan, no trial',
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $this->planWithoutTrial->prices()->createMany([
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 99.99],
        ]);

        $this->user = User::factory()->create();
    }

    // =========================================================================
    //  EXPIRED TRIAL TESTS
    // =========================================================================

    #[Test]
    public function it_activates_subscriptions_with_expired_trials(): void
    {
        // Create a subscription whose trial ended yesterday
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->subDay(),
            'starts_at' => now()->subDays(15),
        ]);

        $this->artisan('subscriptions:process')
            ->assertExitCode(0);

        // Reload and verify state change
        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    #[Test]
    public function it_does_not_activate_trials_that_have_not_expired(): void
    {
        // Create a subscription whose trial ends tomorrow
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->addDay(),
            'starts_at' => now()->subDays(5),
        ]);

        $this->artisan('subscriptions:process')
            ->assertExitCode(0);

        $subscription->refresh();

        // Should remain unchanged
        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    #[Test]
    public function it_dispatches_subscription_activated_event_when_trial_expires(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->subHour(),
            'starts_at' => now()->subDays(15),
        ]);

        $this->artisan('subscriptions:process');

        Event::assertDispatched(SubscriptionActivated::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->userId === $subscription->user_id
                && $event->previousStatus === SubscriptionStatus::TRIALING;
        });
    }

    #[Test]
    public function it_is_idempotent_when_processing_expired_trials(): void
    {
        // Running the command twice should not cause errors or duplicate transitions
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->subDays(2),
            'starts_at' => now()->subDays(16),
        ]);

        // First run — should activate
        $this->artisan('subscriptions:process')->assertExitCode(0);
        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);

        // Second run — should be a no-op (no errors, no state change)
        $this->artisan('subscriptions:process')->assertExitCode(0);
        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
    }

    // =========================================================================
    //  GRACE PERIOD EXPIRATION TESTS
    // =========================================================================

    #[Test]
    public function it_cancels_subscriptions_with_expired_grace_periods(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
            'grace_period_ends_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:process')
            ->assertExitCode(0);

        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);
        $this->assertNotNull($subscription->ends_at);
        $this->assertNull($subscription->grace_period_ends_at);
    }

    #[Test]
    public function it_does_not_cancel_subscriptions_still_in_grace_period(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
            'grace_period_ends_at' => now()->addDay(),
        ]);

        $this->artisan('subscriptions:process')
            ->assertExitCode(0);

        $subscription->refresh();

        // Should remain past_due
        $this->assertEquals(SubscriptionStatus::PAST_DUE, $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
    }

    #[Test]
    public function it_dispatches_subscription_canceled_event_when_grace_expires(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
            'grace_period_ends_at' => now()->subHour(),
        ]);

        $this->artisan('subscriptions:process');

        Event::assertDispatched(SubscriptionCanceled::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->userId === $subscription->user_id
                && $event->previousStatus === SubscriptionStatus::PAST_DUE;
        });
    }

    #[Test]
    public function it_is_idempotent_when_processing_expired_grace_periods(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
            'grace_period_ends_at' => now()->subDays(2),
        ]);

        // First run — should cancel
        $this->artisan('subscriptions:process')->assertExitCode(0);
        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);

        // Second run — should be a no-op
        $this->artisan('subscriptions:process')->assertExitCode(0);
        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);
    }

    // =========================================================================
    //  LOGGING TESTS
    // =========================================================================

    #[Test]
    public function it_logs_trial_expiration_transitions(): void
    {
        // Use a partial mock — allow all Log calls, but verify the specific one
        $loggedMessages = [];
        Log::shouldReceive('info', 'warning', 'error')
            ->andReturnUsing(function (...$args) use (&$loggedMessages) {
                $loggedMessages[] = ['level' => 'info', 'message' => $args[0], 'context' => $args[1] ?? []];
            });

        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->subHour(),
            'starts_at' => now()->subDays(15),
        ]);

        $this->artisan('subscriptions:process');

        $found = collect($loggedMessages)->contains(function ($entry) {
            return str_contains($entry['message'], 'Trial expired')
                && $entry['context']['old_status'] === 'trialing'
                && $entry['context']['new_status'] === 'active';
        });

        $this->assertTrue($found, 'Expected log entry for trial expiration was not found.');
    }

    #[Test]
    public function it_logs_grace_period_expiration_transitions(): void
    {
        $loggedMessages = [];
        Log::shouldReceive('info', 'warning', 'error')
            ->andReturnUsing(function (...$args) use (&$loggedMessages) {
                $loggedMessages[] = ['level' => $args[0] === 'warning' ? 'warning' : 'info', 'message' => $args[0], 'context' => $args[1] ?? []];
            });

        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
            'grace_period_ends_at' => now()->subHour(),
        ]);

        $this->artisan('subscriptions:process');

        $found = collect($loggedMessages)->contains(function ($entry) {
            return str_contains($entry['message'], 'Grace period expired')
                && $entry['context']['old_status'] === 'past_due'
                && $entry['context']['new_status'] === 'canceled';
        });

        $this->assertTrue($found, 'Expected log entry for grace period expiration was not found.');
    }

    // =========================================================================
    //  BATCH PROCESSING TESTS
    // =========================================================================

    #[Test]
    public function it_processes_multiple_expired_trials_in_a_single_run(): void
    {
        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $this->planWithTrial->id,
                'status' => SubscriptionStatus::TRIALING,
                'currency' => Currency::USD,
                'billing_cycle' => BillingCycle::MONTHLY,
                'price' => 9.99,
                'trial_ends_at' => now()->subDays(rand(1, 10)),
                'starts_at' => now()->subDays(20),
            ]);
        }

        $this->artisan('subscriptions:process')->assertExitCode(0);

        // All should be activated
        $this->assertEquals(
            5,
            Subscription::where('status', SubscriptionStatus::ACTIVE)->count()
        );
    }

    #[Test]
    public function it_handles_mixed_scenarios_correctly(): void
    {
        // Create subscriptions in various states
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        // Expired trial → should activate
        Subscription::create([
            'user_id' => $user1->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->subDay(),
            'starts_at' => now()->subDays(15),
        ]);

        // Active trial → should NOT change
        Subscription::create([
            'user_id' => $user2->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->addDays(5),
            'starts_at' => now()->subDays(9),
        ]);

        // Expired grace → should cancel
        Subscription::create([
            'user_id' => $user3->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
            'grace_period_ends_at' => now()->subDay(),
        ]);

        // Active subscription → should NOT change
        Subscription::create([
            'user_id' => $user4->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::ACTIVE,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(30),
        ]);

        $this->artisan('subscriptions:process')->assertExitCode(0);

        // Verify each state
        $this->assertEquals(
            SubscriptionStatus::ACTIVE,
            Subscription::where('user_id', $user1->id)->first()->status
        );
        $this->assertEquals(
            SubscriptionStatus::TRIALING,
            Subscription::where('user_id', $user2->id)->first()->status
        );
        $this->assertEquals(
            SubscriptionStatus::CANCELED,
            Subscription::where('user_id', $user3->id)->first()->status
        );
        $this->assertEquals(
            SubscriptionStatus::ACTIVE,
            Subscription::where('user_id', $user4->id)->first()->status
        );
    }

    // =========================================================================
    //  DRY RUN TEST
    // =========================================================================

    #[Test]
    public function it_does_not_modify_state_in_dry_run_mode(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->planWithTrial->id,
            'status' => SubscriptionStatus::TRIALING,
            'currency' => Currency::USD,
            'billing_cycle' => BillingCycle::MONTHLY,
            'price' => 9.99,
            'trial_ends_at' => now()->subDay(),
            'starts_at' => now()->subDays(15),
        ]);

        $this->artisan('subscriptions:process --dry-run')
            ->assertExitCode(0);

        $subscription->refresh();

        // Should remain unchanged
        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    // =========================================================================
    //  SCHEDULER REGISTRATION TEST
    // =========================================================================

    #[Test]
    public function the_command_is_registered_in_the_scheduler(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();

        $found = collect($events)->contains(function ($event) {
            return str_contains($event->command, 'subscriptions:process');
        });

        $this->assertTrue($found, 'The subscriptions:process command should be registered in the scheduler.');
    }
}
