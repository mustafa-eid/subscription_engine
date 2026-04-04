<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating a new subscription plan.
 *
 * Validates the plan name, trial period, and pricing configurations.
 * Also ensures no duplicate currency + billing cycle combinations
 * are submitted within the same request.
 */
class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All authenticated users can create plans (admin-level operation
     * can be enforced via middleware if needed).
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.currency' => [
                'required',
                'string',
                Rule::enum(Currency::class),
            ],
            'prices.*.billing_cycle' => [
                'required',
                'string',
                Rule::enum(BillingCycle::class),
            ],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Configure the validator instance with custom after-hooks.
     *
     * Ensures no duplicate currency + billing cycle combinations
     * exist within the submitted prices array.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $prices = $this->input('prices', []);
            $seen = [];

            foreach ($prices as $index => $price) {
                $key = "{$price['currency']}:{$price['billing_cycle']}";

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "prices.{$index}",
                        "Duplicate currency and billing cycle combination: {$price['currency']} / {$price['billing_cycle']}."
                    );
                }

                $seen[$key] = true;
            }
        });
    }
}
