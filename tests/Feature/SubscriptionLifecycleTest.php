<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionActivated;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionPastDue;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comprehensive feature tests for the subscription lifecycle engine.
 *
 * Covers: state transitions, event dispatch, logging, idempotency,
 * access logic, and batch processing.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionLifecycleService $service;
    private User $user;
    private Plan $planWithTrial;
    private Plan $planWithoutTrial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionLifecycleService::class);
        $this->user = User::factory()->create();

        // Plan with 14-day trial
        $this->planWithTrial = Plan::factory()->withTrial(14)->create();
        $this->planWithTrial->prices()->createMany([
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 9.99],
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::YEARLY, 'price' => 99.99],
            ['currency' => Currency::AED, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 36.99],
        ]);

        // Plan without trial
        $this->planWithoutTrial = Plan::factory()->withoutTrial()->create();
        $this->planWithoutTrial->prices()->createMany([
            ['currency' => Currency::USD, 'billing_cycle' => BillingCycle::MONTHLY, 'price' => 49.99],
        ]);
    }

    // =========================================================================
    //  1. SUBSCRIBE — TRIAL FLOW
    // =========================================================================

    #[Test]
    public function subscribing_to_plan_with_trial_creates_trialing_subscription(): void
    {
        $subscription = $this->service->subscribe(
            $this->user->id,
            $this->planWithTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isFuture());
        $this->assertEquals('9.99', $subscription->price);
        $this->assertEquals(Currency::USD, $subscription->currency);
        $this->assertEquals(BillingCycle::MONTHLY, $subscription->billing_cycle);
    }

    #[Test]
    public function subscribing_to_plan_without_trial_creates_active_subscription(): void
    {
        $subscription = $this->service->subscribe(
            $this->user->id,
            $this->planWithoutTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    #[Test]
    public function subscribing_dispatches_activated_event_when_no_trial(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $subscription = $this->service->subscribe(
            $this->user->id,
            $this->planWithoutTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        Event::assertDispatched(SubscriptionActivated::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->userId === $this->user->id;
        });
    }

    // =========================================================================
    //  2. TRIAL EXPIRATION → ACTIVE
    // =========================================================================

    #[Test]
    public function expired_trial_moves_to_active(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->expiredTrial(1)
            ->create();

        $result = $this->service->handleTrialExpiration($subscription);

        $this->assertEquals(SubscriptionStatus::ACTIVE, $result->status);
        $this->assertNull($result->trial_ends_at);
    }

    #[Test]
    public function trial_expiration_dispatches_activated_event(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->expiredTrial(1)
            ->create();

        $this->service->handleTrialExpiration($subscription);

        Event::assertDispatched(SubscriptionActivated::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->previousStatus === SubscriptionStatus::TRIALING;
        });
    }

    #[Test]
    public function trial_expiration_is_logged(): void
    {
        $loggedMessages = [];
        $this->mockLogCalls($loggedMessages);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->expiredTrial(1)
            ->create();

        $this->service->handleTrialExpiration($subscription);

        $found = collect($loggedMessages)->contains(fn ($entry) =>
            str_contains($entry['message'], 'Trial expired')
            && $entry['context']['subscription_id'] === $subscription->id
            && $entry['context']['user_id'] === $this->user->id
            && $entry['context']['old_status'] === 'trialing'
            && $entry['context']['new_status'] === 'active'
        );

        $this->assertTrue($found, 'Expected trial expiration log entry not found.');
    }

    // =========================================================================
    //  3. PAYMENT FAILURE → PAST_DUE
    // =========================================================================

    #[Test]
    public function payment_failure_moves_active_to_past_due(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $result = $this->service->handlePaymentFailure($subscription);

        $this->assertEquals(SubscriptionStatus::PAST_DUE, $result->status);
        $this->assertNotNull($result->grace_period_ends_at);
        $this->assertTrue($result->grace_period_ends_at->isFuture());
        // Grace period should be ~3 days from now
        $this->assertLessThan(4, now()->diffInDays($result->grace_period_ends_at, false));
    }

    #[Test]
    public function payment_failure_dispatches_past_due_event(): void
    {
        Event::fake([SubscriptionPastDue::class]);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $this->service->handlePaymentFailure($subscription);

        Event::assertDispatched(SubscriptionPastDue::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->previousStatus === SubscriptionStatus::ACTIVE;
        });
    }

    #[Test]
    public function payment_failure_is_logged(): void
    {
        $loggedMessages = [];
        $this->mockLogCalls($loggedMessages);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $this->service->handlePaymentFailure($subscription);

        $found = collect($loggedMessages)->contains(fn ($entry) =>
            str_contains($entry['message'], 'Payment failed')
            && $entry['context']['subscription_id'] === $subscription->id
            && $entry['context']['new_status'] === 'past_due'
        );

        $this->assertTrue($found, 'Expected payment failure log entry not found.');
    }

    // =========================================================================
    //  4. PAYMENT SUCCESS DURING GRACE → ACTIVE
    // =========================================================================

    #[Test]
    public function payment_success_recovers_past_due_to_active(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->pastDue(2)
            ->create();

        $result = $this->service->handlePaymentSuccess($subscription);

        $this->assertEquals(SubscriptionStatus::ACTIVE, $result->status);
        $this->assertNull($result->grace_period_ends_at);
    }

    #[Test]
    public function payment_success_dispatches_activated_event(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->pastDue(2)
            ->create();

        $this->service->handlePaymentSuccess($subscription);

        Event::assertDispatched(SubscriptionActivated::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->previousStatus === SubscriptionStatus::PAST_DUE;
        });
    }

    #[Test]
    public function payment_success_is_logged(): void
    {
        $loggedMessages = [];
        $this->mockLogCalls($loggedMessages);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->pastDue(2)
            ->create();

        $this->service->handlePaymentSuccess($subscription);

        $found = collect($loggedMessages)->contains(fn ($entry) =>
            str_contains($entry['message'], 'Payment succeeded')
            && $entry['context']['old_status'] === 'past_due'
            && $entry['context']['new_status'] === 'active'
        );

        $this->assertTrue($found, 'Expected payment success log entry not found.');
    }

    // =========================================================================
    //  5. GRACE PERIOD EXPIRATION → CANCELED
    // =========================================================================

    #[Test]
    public function expired_grace_period_moves_to_canceled(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->expiredGrace(1)
            ->create();

        $result = $this->service->handleGracePeriodExpiration($subscription);

        $this->assertEquals(SubscriptionStatus::CANCELED, $result->status);
        $this->assertNotNull($result->ends_at);
        $this->assertNull($result->grace_period_ends_at);
    }

    #[Test]
    public function grace_expiration_dispatches_canceled_event(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->expiredGrace(1)
            ->create();

        $this->service->handleGracePeriodExpiration($subscription);

        Event::assertDispatched(SubscriptionCanceled::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->previousStatus === SubscriptionStatus::PAST_DUE;
        });
    }

    #[Test]
    public function grace_expiration_is_logged(): void
    {
        $loggedMessages = [];
        $this->mockLogCalls($loggedMessages);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->expiredGrace(1)
            ->create();

        $this->service->handleGracePeriodExpiration($subscription);

        $found = collect($loggedMessages)->contains(fn ($entry) =>
            str_contains($entry['message'], 'Grace period expired')
            && $entry['context']['old_status'] === 'past_due'
            && $entry['context']['new_status'] === 'canceled'
        );

        $this->assertTrue($found, 'Expected grace expiration log entry not found.');
    }

    // =========================================================================
    //  6. CANCEL SUBSCRIPTION
    // =========================================================================

    #[Test]
    public function cancel_immediately_cancels_active_subscription(): void
    {
        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $result = $this->service->cancel($subscription);

        $this->assertEquals(SubscriptionStatus::CANCELED, $result->status);
        $this->assertNotNull($result->ends_at);
        $this->assertNull($result->grace_period_ends_at);
    }

    #[Test]
    public function cancel_dispatches_canceled_event(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $this->service->cancel($subscription);

        Event::assertDispatched(SubscriptionCanceled::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id
                && $event->previousStatus === SubscriptionStatus::ACTIVE;
        });
    }

    #[Test]
    public function cancel_is_logged(): void
    {
        $loggedMessages = [];
        $this->mockLogCalls($loggedMessages);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $this->service->cancel($subscription);

        $found = collect($loggedMessages)->contains(fn ($entry) =>
            str_contains($entry['message'], 'Subscription canceled')
            && $entry['context']['subscription_id'] === $subscription->id
            && $entry['context']['new_status'] === 'canceled'
        );

        $this->assertTrue($found, 'Expected cancellation log entry not found.');
    }

    #[Test]
    public function cancel_throws_on_already_canceled_subscription(): void
    {
        $this->expectException(\App\Exceptions\InvalidSubscriptionStateException::class);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->canceled()
            ->create();

        $this->service->cancel($subscription);
    }

    // =========================================================================
    //  7. IDEMPOTENCY
    // =========================================================================

    #[Test]
    public function duplicate_subscription_requests_are_rejected(): void
    {
        $this->service->subscribe(
            $this->user->id,
            $this->planWithTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        $this->expectException(\App\Exceptions\AlreadySubscribedException::class);

        $this->service->subscribe(
            $this->user->id,
            $this->planWithTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );
    }

    #[Test]
    public function duplicate_subscription_attempt_is_logged(): void
    {
        $loggedMessages = [];
        $this->mockLogCalls($loggedMessages);

        // First subscription succeeds
        $this->service->subscribe(
            $this->user->id,
            $this->planWithTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        // Second attempt should fail and log
        try {
            $this->service->subscribe(
                $this->user->id,
                $this->planWithTrial->id,
                Currency::USD->value,
                BillingCycle::MONTHLY->value
            );
        } catch (\App\Exceptions\AlreadySubscribedException) {
            // Expected
        }

        $found = collect($loggedMessages)->contains(fn ($entry) =>
            str_contains($entry['message'], 'already has an active subscription')
            && $entry['context']['user_id'] === $this->user->id
        );

        $this->assertTrue($found, 'Expected duplicate subscription log entry not found.');
    }

    #[Test]
    public function payment_success_on_active_subscription_throws(): void
    {
        $this->expectException(\App\Exceptions\InvalidSubscriptionStateException::class);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->active()
            ->create();

        $this->service->handlePaymentSuccess($subscription);
    }

    #[Test]
    public function payment_failure_on_canceled_subscription_throws(): void
    {
        $this->expectException(\App\Exceptions\InvalidSubscriptionStateException::class);

        $subscription = Subscription::factory()
            ->for($this->user)
            ->for($this->planWithTrial)
            ->canceled()
            ->create();

        $this->service->handlePaymentFailure($subscription);
    }

    // =========================================================================
    //  8. ACCESS LOGIC
    // =========================================================================

    #[Test]
    public function hasAccess_returns_true_for_active_subscription(): void
    {
        $subscription = Subscription::factory()->active()->make();
        $this->assertTrue($subscription->hasAccess());
        $this->assertTrue($subscription->isAccessible());
    }

    #[Test]
    public function hasAccess_returns_true_for_trialing_subscription(): void
    {
        $subscription = Subscription::factory()->trialing()->make();
        $this->assertTrue($subscription->hasAccess());
    }

    #[Test]
    public function hasAccess_returns_true_for_past_due_in_grace_period(): void
    {
        $subscription = Subscription::factory()->pastDue(2)->make();
        $this->assertTrue($subscription->hasAccess());
    }

    #[Test]
    public function hasAccess_returns_false_for_past_due_with_expired_grace(): void
    {
        $subscription = Subscription::factory()->expiredGrace(1)->make();
        $this->assertFalse($subscription->hasAccess());
    }

    #[Test]
    public function hasAccess_returns_false_for_canceled_subscription(): void
    {
        $subscription = Subscription::factory()->canceled()->make();
        $this->assertFalse($subscription->hasAccess());
    }

    // =========================================================================
    //  9. STATE TRANSITION GUARDS
    // =========================================================================

    #[Test]
    public function valid_transitions_are_allowed(): void
    {
        $sub = new Subscription();

        // trialing → active
        $sub->status = SubscriptionStatus::TRIALING;
        $this->assertTrue($sub->canTransitionTo(SubscriptionStatus::ACTIVE));

        // active → past_due
        $sub->status = SubscriptionStatus::ACTIVE;
        $this->assertTrue($sub->canTransitionTo(SubscriptionStatus::PAST_DUE));

        // past_due → active
        $sub->status = SubscriptionStatus::PAST_DUE;
        $this->assertTrue($sub->canTransitionTo(SubscriptionStatus::ACTIVE));

        // past_due → canceled
        $this->assertTrue($sub->canTransitionTo(SubscriptionStatus::CANCELED));
    }

    #[Test]
    public function invalid_transitions_are_blocked(): void
    {
        $sub = new Subscription();

        // canceled → active (terminal state)
        $sub->status = SubscriptionStatus::CANCELED;
        $this->assertFalse($sub->canTransitionTo(SubscriptionStatus::ACTIVE));

        // active → trialing (can't go back to trial)
        $sub->status = SubscriptionStatus::ACTIVE;
        $this->assertFalse($sub->canTransitionTo(SubscriptionStatus::TRIALING));

        // trialing → past_due (trial must be activated first)
        $sub->status = SubscriptionStatus::TRIALING;
        $this->assertFalse($sub->canTransitionTo(SubscriptionStatus::PAST_DUE));
    }

    #[Test]
    public function assertCanTransitionTo_throws_on_invalid_transition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $sub = new Subscription();
        $sub->status = SubscriptionStatus::CANCELED;
        $sub->assertCanTransitionTo(SubscriptionStatus::ACTIVE);
    }

    // =========================================================================
    //  10. BATCH PROCESSING (via scheduler command)
    // =========================================================================

    #[Test]
    public function scheduler_processes_multiple_expired_trials(): void
    {
        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            Subscription::factory()
                ->for($user)
                ->for($this->planWithTrial)
                ->expiredTrial(rand(1, 5))
                ->create();
        }

        $this->artisan('subscriptions:process')->assertExitCode(0);

        $this->assertEquals(
            5,
            Subscription::where('status', SubscriptionStatus::ACTIVE)->count()
        );
    }

    #[Test]
    public function scheduler_processes_multiple_expired_grace_periods(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            Subscription::factory()
                ->for($user)
                ->for($this->planWithTrial)
                ->expiredGrace(rand(1, 3))
                ->create();
        }

        $this->artisan('subscriptions:process')->assertExitCode(0);

        $this->assertEquals(
            3,
            Subscription::where('status', SubscriptionStatus::CANCELED)->count()
        );
    }

    #[Test]
    public function scheduler_handles_mixed_states_correctly(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Expired trial → should activate
        Subscription::factory()
            ->for($user1)
            ->for($this->planWithTrial)
            ->expiredTrial(1)
            ->create();

        // Active trial → should NOT change
        Subscription::factory()
            ->for($user2)
            ->for($this->planWithTrial)
            ->trialing(5)
            ->create();

        // Expired grace → should cancel
        Subscription::factory()
            ->for($user3)
            ->for($this->planWithTrial)
            ->expiredGrace(1)
            ->create();

        $this->artisan('subscriptions:process')->assertExitCode(0);

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
    }

    // =========================================================================
    //  11. FULL LIFECYCLE INTEGRATION TEST
    // =========================================================================

    #[Test]
    public function full_lifecycle_trial_to_cancellation(): void
    {
        Event::fake();

        // Step 1: Subscribe with trial
        $subscription = $this->service->subscribe(
            $this->user->id,
            $this->planWithTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertTrue($subscription->hasAccess());

        // Step 2: Simulate trial expiration
        $subscription->update(['trial_ends_at' => now()->subHour()]);
        $subscription->refresh();

        $subscription = $this->service->handleTrialExpiration($subscription);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);

        // Step 3: Payment fails
        $subscription = $this->service->handlePaymentFailure($subscription);
        $this->assertEquals(SubscriptionStatus::PAST_DUE, $subscription->status);
        $this->assertTrue($subscription->hasAccess()); // Still in grace

        // Step 4: Grace expires
        $subscription->update(['grace_period_ends_at' => now()->subHour()]);
        $subscription->refresh();

        $subscription = $this->service->handleGracePeriodExpiration($subscription);
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);
        $this->assertFalse($subscription->hasAccess());

        // Verify events were dispatched
        Event::assertDispatchedTimes(SubscriptionActivated::class, 1);
        Event::assertDispatchedTimes(SubscriptionPastDue::class, 1);
        Event::assertDispatchedTimes(SubscriptionCanceled::class, 1);
    }

    #[Test]
    public function full_lifecycle_with_recovery(): void
    {
        // Step 1: Subscribe without trial (starts active)
        $subscription = $this->service->subscribe(
            $this->user->id,
            $this->planWithoutTrial->id,
            Currency::USD->value,
            BillingCycle::MONTHLY->value
        );

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);

        // Step 2: Payment fails
        $subscription = $this->service->handlePaymentFailure($subscription);
        $this->assertEquals(SubscriptionStatus::PAST_DUE, $subscription->status);

        // Step 3: User pays during grace period
        $subscription = $this->service->handlePaymentSuccess($subscription);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->hasAccess());
    }

    // =========================================================================
    //  HELPER: Mock Log facade to capture all calls
    // =========================================================================

    private function mockLogCalls(array &$loggedMessages): void
    {
        Log::shouldReceive('info', 'warning', 'error')
            ->andReturnUsing(function (...$args) use (&$loggedMessages) {
                $loggedMessages[] = [
                    'message' => $args[0],
                    'context' => $args[1] ?? [],
                ];
            });
    }
}
