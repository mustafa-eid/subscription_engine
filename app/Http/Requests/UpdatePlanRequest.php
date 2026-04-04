<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating an existing subscription plan.
 *
 * All fields are optional (partial update). When prices are provided,
 * all existing prices are replaced with the new set. Validates that
 * no duplicate currency + billing cycle combinations exist.
 */
class UpdatePlanRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'prices' => ['sometimes', 'array', 'min:1'],
            'prices.*.currency' => [
                'required_with:prices',
                'string',
                Rule::enum(Currency::class),
            ],
            'prices.*.billing_cycle' => [
                'required_with:prices',
                'string',
                Rule::enum(BillingCycle::class),
            ],
            'prices.*.price' => ['required_with:prices', 'numeric', 'min:0'],
        ];
    }

    /**
     * Configure the validator instance with custom after-hooks.
     *
     * Ensures no duplicate currency + billing cycle combinations
     * exist within the submitted prices array (only when prices
     * are being updated).
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $prices = $this->input('prices', []);

            if (empty($prices)) {
                return;
            }

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
