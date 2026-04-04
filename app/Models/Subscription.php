<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Subscription model representing a user's subscription to a plan.
 *
 * =========================================================================
 *  ACCESS RULES — When does a user have access?
 * =========================================================================
 *
 * A user has access (hasAccess() returns true) when:
 *   - status = 'active'          → normal paid access
 *   - status = 'trialing'        → trial period access
 *   - status = 'past_due' AND
 *     grace_period_ends_at is in the future → grace period access
 *
 * A user does NOT have access when:
 *   - status = 'canceled'        → subscription ended
 *   - status = 'past_due' AND
 *     grace_period_ends_at has passed → grace expired, no access
 *
 * This ensures users retain access during the grace period even though
 * their payment has failed, giving them time to resolve the issue.
 * =========================================================================
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property string $status
 * @property string $currency
 * @property string $billing_cycle
 * @property string $price
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Plan $plan
 * @property-read User $user
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'currency',
        'billing_cycle',
        'price',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'grace_period_ends_at',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'currency' => Currency::class,
        'billing_cycle' => BillingCycle::class,
        'price' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
    ];

    // =========================================================================
    //  ACCESS LOGIC
    // =========================================================================

    /**
     * Determine if the user currently has access to the subscription features.
     *
     * Access is granted when:
     *   - Status is 'active' (normal paid access)
     *   - Status is 'trialing' (trial period access)
     *   - Status is 'past_due' but still within the grace period
     *
     * This is the single source of truth for access determination.
     */
    public function hasAccess(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::TRIALING => true,

            SubscriptionStatus::PAST_DUE => $this->isInGracePeriod(),

            SubscriptionStatus::CANCELED => false,
        };
    }

    /**
     * Alias of hasAccess() for more readable conditional checks.
     *
     * Usage: if ($subscription->isAccessible()) { ... }
     */
    public function isAccessible(): bool
    {
        return $this->hasAccess();
    }

    // =========================================================================
    //  STATE HELPERS
    // =========================================================================

    /**
     * Check if the subscription is currently in trial.
     *
     * Requires both the status to be 'trialing' AND the trial_ends_at
     * timestamp to be in the future.
     */
    public function isInTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the subscription is in the grace period.
     *
     * The grace period starts when a payment fails (past_due) and lasts
     * until grace_period_ends_at. The user retains access during this window.
     */
    public function isInGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::PAST_DUE
            && $this->grace_period_ends_at !== null
            && $this->grace_period_ends_at->isFuture();
    }

    /**
     * Check if the subscription has permanently ended.
     */
    public function hasEnded(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
    }

    // =========================================================================
    //  STATE TRANSITION GUARD MATRIX
    // =========================================================================

    /**
     * Define which state transitions are allowed.
     *
     * This matrix prevents invalid transitions such as:
     *   - canceled → active   (terminal state, no recovery)
     *   - trialing → past_due (trial hasn't been activated yet)
     *   - active → trialing   (can't go back to trial)
     *
     * Keys are the FROM state (as string values), values are arrays of
     * allowed TO state values.
     */
    private const ALLOWED_TRANSITIONS = [
        'trialing' => ['active', 'canceled'],
        'active' => ['past_due', 'canceled'],
        'past_due' => ['active', 'canceled'],
        // 'canceled' is a terminal state — no transitions allowed from it
        'canceled' => [],
    ];

    /**
     * Check if transitioning from the current status to the target status is valid.
     */
    public function canTransitionTo(SubscriptionStatus $targetStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value] ?? [];

        return in_array($targetStatus->value, $allowed, true);
    }

    /**
     * Assert that a transition is valid, or throw an exception.
     *
     * @throws \InvalidArgumentException
     */
    public function assertCanTransitionTo(SubscriptionStatus $targetStatus): void
    {
        if (! $this->canTransitionTo($targetStatus)) {
            throw new \InvalidArgumentException(
                "Invalid state transition from '{$this->status->value}' to '{$targetStatus->value}'."
            );
        }
    }

    // =========================================================================
    //  RELATIONSHIPS & SCOPES
    // =========================================================================

    /**
     * Get the user who owns this subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeStatus($query, SubscriptionStatus|string $status)
    {
        $value = $status instanceof SubscriptionStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope: Only active subscriptions (including trialing).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            SubscriptionStatus::TRIALING->value,
            SubscriptionStatus::ACTIVE->value,
        ]);
    }

    /**
     * Scope: Subscriptions with expired trials (trial ended but still trialing).
     */
    public function scopeExpiredTrials($query)
    {
        return $query->where('status', SubscriptionStatus::TRIALING->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now());
    }

    /**
     * Scope: Subscriptions with expired grace period.
     */
    public function scopeExpiredGracePeriod($query)
    {
        return $query->where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now());
    }
}
