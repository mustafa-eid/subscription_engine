<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for serializing a Subscription model.
 *
 * Transforms the Subscription model into a consistent JSON structure
 * for API responses. Includes computed access state fields:
 *   - has_access: Whether the user currently has access
 *   - is_in_trial: Whether the subscription is in an active trial
 *   - is_in_grace_period: Whether the subscription is in the grace period
 *
 * @mixin \App\Models\Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'status' => $this->status,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'has_access' => $this->hasAccess(),
            'is_in_trial' => $this->isInTrial(),
            'is_in_grace_period' => $this->isInGracePeriod(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
