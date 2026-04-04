<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan model representing a subscription plan.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $trial_days
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlanPrice> $prices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Subscription> $subscriptions
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'trial_days',
        'is_active',
    ];

    protected $casts = [
        'trial_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get all pricing configurations for this plan.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * Get all subscriptions associated with this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope: Only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Plans that offer a trial.
     */
    public function scopeWithTrial($query)
    {
        return $query->where('trial_days', '>', 0);
    }

    /**
     * Check if this plan has a trial period.
     */
    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    /**
     * Get the price for a specific currency and billing cycle.
     */
    public function getPriceFor(string $currency, string $billingCycle): ?PlanPrice
    {
        return $this->prices
            ->where('currency', $currency)
            ->where('billing_cycle', $billingCycle)
            ->first();
    }
}
