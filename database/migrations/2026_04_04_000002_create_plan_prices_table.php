<?php

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create the plan_prices table for dynamic pricing
     * per currency and billing cycle.
     */
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('currency', 3)->comment('ISO currency code');
            $table->string('billing_cycle')->comment('monthly or yearly');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            // Each plan can only have one price per currency + billing cycle combo
            $table->unique(['plan_id', 'currency', 'billing_cycle'], 'plan_currency_cycle_unique');

            // Indexes for common query patterns
            $table->index('currency');
            $table->index('billing_cycle');

            // Composite index for price lookups by currency and billing cycle
            $table->index(['currency', 'billing_cycle'], 'idx_currency_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
