<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for subscribing a user to a plan.
 *
 * Validates that the plan exists, the currency and billing cycle
 * are supported enum values, and all required fields are present.
 *
 * Note: The actual price lookup and idempotency check happen
 * in the SubscriptionLifecycleService, not in validation.
 */
class SubscribeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'currency' => [
                'required',
                'string',
                Rule::enum(Currency::class),
            ],
            'billing_cycle' => [
                'required',
                'string',
                Rule::enum(BillingCycle::class),
            ],
        ];
    }
}
