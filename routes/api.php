<?php

/**
 * API Routes (v1)
 *
 * Defines all RESTful API endpoints for the Subscription Management system.
 * All routes are prefixed with /api/v1/ and rate-limited to 60 requests
 * per minute per user (or per IP for unauthenticated requests).
 *
 * Public endpoints (no authentication):
 *   - GET    /api/v1/plans              — List active plans (paginated)
 *   - GET    /api/v1/plans/{id}         — Get a single plan
 *
 * Protected endpoints (require Sanctum token):
 *   - POST   /api/v1/plans              — Create a new plan
 *   - PUT    /api/v1/plans/{id}         — Update an existing plan
 *   - DELETE /api/v1/plans/{id}         — Soft-delete a plan
 *   - GET    /api/v1/subscriptions      — List authenticated user's subscriptions
 *   - POST   /api/v1/subscriptions/subscribe — Subscribe to a plan
 *   - POST   /api/v1/subscriptions/{id}/cancel — Cancel a subscription
 *   - POST   /api/v1/subscriptions/{id}/simulate-payment-success — Simulate payment recovery (dev)
 *   - POST   /api/v1/subscriptions/{id}/simulate-payment-failure — Simulate payment failure (dev)
 */

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// =========================================================================
//  Health Check Routes - Public, no authentication required
// =========================================================================

/**
 * GET /api/health
 * Basic health check for monitoring service status.
 */
Route::get('/health', HealthController::class);

/**
 * GET /api/health/detailed
 * Detailed health check with metrics and configuration.
 */
Route::get('/health/detailed', [HealthController::class, 'detailed']);

// =========================================================================
//  Webhook Routes - Public but signature-verified
// =========================================================================

/**
 * POST /api/webhooks/payment
 * Handle payment gateway webhook events.
 * Signature verification ensures only valid webhooks are processed.
 */
Route::post('/webhooks/payment', WebhookController::class)->middleware('throttle:webhook');

// =========================================================================
//  API v1 Routes
// =========================================================================

Route::prefix('v1')->group(function () {

    // =========================================================================
    //  Authentication Routes — Public, no authentication required
    // =========================================================================

    Route::prefix('auth')->group(function () {
        /**
         * POST /api/v1/auth/register
         * Register a new user and return an authentication token.
         * Body: { name, email, password, password_confirmation }
         */
        Route::post('/register', [AuthController::class, 'register']);

        /**
         * POST /api/v1/auth/login
         * Authenticate a user and return an access token.
         * Body: { email, password }
         */
        Route::post('/login', [AuthController::class, 'login']);
    });

    // =========================================================================
    //  Public Routes — No authentication required
    // =========================================================================

    Route::prefix('plans')->group(function () {
        /**
         * GET /api/v1/plans
         * List all active plans with pricing, paginated.
         * Query params: ?per_page=N (default: 10, max: 50), ?page=N
         */
        Route::get('/', [PlanController::class, 'index']);

        /**
         * GET /api/v1/plans/{id}
         * Retrieve a single plan by ID with all pricing configurations.
         */
        Route::get('/{id}', [PlanController::class, 'show']);
    });

    // =========================================================================
    //  Protected Routes — Require Sanctum authentication (Bearer token)
    // =========================================================================

    Route::middleware('auth:sanctum')->group(function () {

        // --- Plan Management (admin-level CRUD operations) ---
        Route::prefix('plans')->group(function () {
            /**
             * POST /api/v1/plans
             * Create a new subscription plan with pricing configurations.
             * Body: { name, description?, trial_days, is_active?, prices: [{ currency, billing_cycle, price }] }
             */
            Route::post('/', [PlanController::class, 'store']);

            /**
             * PUT /api/v1/plans/{id}
             * Update an existing plan's attributes and/or replace all prices.
             * Body: { name?, description?, trial_days?, is_active?, prices? }
             */
            Route::put('/{id}', [PlanController::class, 'update']);

            /**
             * DELETE /api/v1/plans/{id}
             * Soft-delete a plan (can be restored).
             */
            Route::delete('/{id}', [PlanController::class, 'destroy']);
        });

        // --- Subscription Lifecycle Management ---
        Route::prefix('subscriptions')->group(function () {
            /**
             * GET /api/v1/subscriptions
             * List all subscriptions for the authenticated user, ordered by newest first.
             */
            Route::get('/', [SubscriptionController::class, 'index']);

            /**
             * POST /api/v1/subscriptions/subscribe
             * Subscribe the authenticated user to a plan.
             * Body: { plan_id, currency, billing_cycle }
             * Returns 409 if the user already has an active subscription (idempotency).
             */
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);

            /**
             * POST /api/v1/subscriptions/{id}/cancel
             * Immediately cancel the specified subscription.
             * User must own the subscription (403 otherwise).
             */
            Route::post('/{id}/cancel', [SubscriptionController::class, 'cancel']);

            /**
             * POST /api/v1/subscriptions/{id}/simulate-payment-success
             * Simulate a successful payment to recover a past_due subscription.
             * For development/testing purposes only.
             */
            Route::post('/{id}/simulate-payment-success', [SubscriptionController::class, 'simulatePaymentSuccess']);

            /**
             * POST /api/v1/subscriptions/{id}/simulate-payment-failure
             * Simulate a payment failure, moving an active subscription to past_due.
             * For development/testing purposes only.
             */
            Route::post('/{id}/simulate-payment-failure', [SubscriptionController::class, 'simulatePaymentFailure']);
        });
    });
});
