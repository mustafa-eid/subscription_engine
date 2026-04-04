<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for serializing a Plan model.
 *
 * Transforms the Plan model into a consistent JSON structure
 * for API responses. Includes computed fields (has_trial) and
 * conditionally loads the prices relationship.
 *
 * @mixin \App\Models\Plan
 */
class PlanResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'trial_days' => $this->trial_days,
            'has_trial' => $this->hasTrial(),
            'is_active' => $this->is_active,
            'prices' => PlanPriceResource::collection($this->whenLoaded('prices')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
