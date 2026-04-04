<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PlanPrice model representing a price point for a plan
 * in a specific currency and billing cycle.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $currency
 * @property string $billing_cycle
 * @property string $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Plan $plan
 */
class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'currency',
        'billing_cycle',
        'price',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'billing_cycle' => BillingCycle::class,
        'price' => 'decimal:2',
    ];

    /**
     * Get the plan this price belongs to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Scope: Filter by currency.
     */
    public function scopeCurrency($query, Currency|string $currency)
    {
        $value = $currency instanceof Currency ? $currency->value : $currency;

        return $query->where('currency', $value);
    }

    /**
     * Scope: Filter by billing cycle.
     */
    public function scopeBillingCycle($query, BillingCycle|string $billingCycle)
    {
        $value = $billingCycle instanceof BillingCycle ? $billingCycle->value : $billingCycle;

        return $query->where('billing_cycle', $value);
    }

    /**
     * Format price with currency symbol.
     */
    public function getFormattedPriceAttribute(): string
    {
        $currencyEnum = is_string($this->currency) ? Currency::tryFrom($this->currency) : $this->currency;
        $symbol = $currencyEnum?->symbol() ?? '';

        return $symbol . number_format((float) $this->price, 2);
    }
}
