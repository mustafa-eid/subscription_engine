<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create the subscription_audit_logs table for tracking
     * all subscription state changes for compliance and debugging.
     */
    public function up(): void
    {
        Schema::create('subscription_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type')->comment('The type of event (e.g., subscribed, activated, canceled)');
            $table->string('old_status')->nullable()->comment('Previous subscription status');
            $table->string('new_status')->nullable()->comment('New subscription status');
            $table->json('metadata')->nullable()->comment('Additional context about the change');
            $table->string('triggered_by')->nullable()->comment('What triggered the change: user, system, webhook, scheduler');
            $table->string('request_id')->nullable()->comment('Associated request ID for tracing');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            // Indexes for common queries
            $table->index('subscription_id');
            $table->index('user_id');
            $table->index('event_type');
            $table->index('occurred_at');
            $table->index(['subscription_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');
    }
};
