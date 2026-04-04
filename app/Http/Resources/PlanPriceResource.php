<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for serializing a PlanPrice model.
 *
 * Transforms the PlanPrice model into a consistent JSON structure
 * including the human-readable formatted_price with currency symbol.
 *
 * @mixin \App\Models\PlanPrice
 */
class PlanPriceResource extends JsonResource
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
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
        ];
    }
}
