<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

/**
 * Seeds the database with demo subscription plans.
 *
 * Creates three tiers of plans (Starter, Professional, Enterprise)
 * each with pricing configurations for all supported currencies
 * (USD, AED, EGP) and billing cycles (Monthly, Yearly).
 *
 * This seeder is intended for development and demonstration
 * purposes. Production deployments should use real plan data.
 */
class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 3 plans × 3 currencies × 2 billing cycles = 18 price entries.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for individuals and small projects.',
                'trial_days' => 14,
                'is_active' => true,
                'prices' => [
                    [BillingCycle::MONTHLY, Currency::USD, '9.99'],
                    [BillingCycle::YEARLY, Currency::USD, '99.99'],
                    [BillingCycle::MONTHLY, Currency::AED, '36.99'],
                    [BillingCycle::YEARLY, Currency::AED, '369.99'],
                    [BillingCycle::MONTHLY, Currency::EGP, '499.00'],
                    [BillingCycle::YEARLY, Currency::EGP, '4999.00'],
                ],
            ],
            [
                'name' => 'Professional',
                'description' => 'Ideal for growing businesses and teams.',
                'trial_days' => 14,
                'is_active' => true,
                'prices' => [
                    [BillingCycle::MONTHLY, Currency::USD, '29.99'],
                    [BillingCycle::YEARLY, Currency::USD, '299.99'],
                    [BillingCycle::MONTHLY, Currency::AED, '109.99'],
                    [BillingCycle::YEARLY, Currency::AED, '1099.99'],
                    [BillingCycle::MONTHLY, Currency::EGP, '1499.00'],
                    [BillingCycle::YEARLY, Currency::EGP, '14999.00'],
                ],
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large organizations with advanced needs.',
                'trial_days' => 0,
                'is_active' => true,
                'prices' => [
                    [BillingCycle::MONTHLY, Currency::USD, '99.99'],
                    [BillingCycle::YEARLY, Currency::USD, '999.99'],
                    [BillingCycle::MONTHLY, Currency::AED, '369.99'],
                    [BillingCycle::YEARLY, Currency::AED, '3699.99'],
                    [BillingCycle::MONTHLY, Currency::EGP, '4999.00'],
                    [BillingCycle::YEARLY, Currency::EGP, '49999.00'],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $prices = $planData['prices'];
            unset($planData['prices']);

            $plan = Plan::create($planData);

            foreach ($prices as [$billingCycle, $currency, $price]) {
                PlanPrice::create([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                    'price' => $price,
                ]);
            }
        }
    }
}
