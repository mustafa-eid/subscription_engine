<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Controller
 *
 * Handles incoming webhook events from payment gateways.
 * Processes payment success/failure events and updates subscriptions accordingly.
 */
class WebhookController extends Controller
{
    use ApiResponse;

    /**
     * Create a new WebhookController instance.
     */
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway,
        protected SubscriptionLifecycleService $lifecycleService,
    ) {
    }

    /**
     * Handle incoming webhook events.
     *
     * POST /api/webhooks/payment
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Webhook-Signature', '');

        // Verify webhook signature
        if (!$this->paymentGateway->verifyWebhook($payload, $signature)) {
            Log::warning('Invalid webhook signature', [
                'ip' => $request->ip(),
                'payload' => $payload,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook signature',
            ], 401);
        }

        try {
            // Parse the webhook event
            $event = $this->paymentGateway->handleWebhook($payload);

            Log::info('Webhook event received', [
                'type' => $event->type,
                'subscription_id' => $event->subscriptionId,
                'transaction_id' => $event->transactionId,
            ]);

            // Process the event based on its type
            $this->processWebhookEvent($event);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Process a webhook event based on its type.
     *
     * @param \App\Dto\WebhookEvent $event The webhook event to process
     */
    protected function processWebhookEvent(\App\Dto\WebhookEvent $event): void
    {
        if ($event->isPaymentSuccess()) {
            $this->handlePaymentSuccess($event);
        } elseif ($event->isPaymentFailure()) {
            $this->handlePaymentFailure($event);
        } elseif ($event->isSubscriptionCanceled()) {
            $this->handleSubscriptionCanceled($event);
        } elseif ($event->isSubscriptionUpdated()) {
            $this->handleSubscriptionUpdated($event);
        } elseif ($event->isTrialEnding()) {
            $this->handleTrialEnding($event);
        } else {
            Log::info('Unhandled webhook event', [
                'type' => $event->type,
            ]);
        }
    }

    /**
     * Handle payment success event.
     */
    protected function handlePaymentSuccess(\App\Dto\WebhookEvent $event): void
    {
        // TODO: Find subscription by gateway subscription ID
        // For now, log the event. In production, you'd map gateway IDs to your subscription IDs
        Log::info('Payment succeeded', [
            'transaction_id' => $event->transactionId,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);
    }

    /**
     * Handle payment failure event.
     */
    protected function handlePaymentFailure(\App\Dto\WebhookEvent $event): void
    {
        Log::warning('Payment failed', [
            'transaction_id' => $event->transactionId,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);

        // TODO: Find subscription and call handlePaymentFailure
        // $subscription = $this->findSubscriptionByGatewayId($event->subscriptionId);
        // if ($subscription) {
        //     $this->lifecycleService->handlePaymentFailure($subscription);
        // }
    }

    /**
     * Handle subscription cancellation event.
     */
    protected function handleSubscriptionCanceled(\App\Dto\WebhookEvent $event): void
    {
        Log::info('Subscription canceled via gateway', [
            'subscription_id' => $event->subscriptionId,
        ]);

        // TODO: Find subscription and cancel it
    }

    /**
     * Handle subscription update event.
     */
    protected function handleSubscriptionUpdated(\App\Dto\WebhookEvent $event): void
    {
        Log::info('Subscription updated', [
            'subscription_id' => $event->subscriptionId,
        ]);

        // TODO: Update subscription details
    }

    /**
     * Handle trial ending soon event.
     */
    protected function handleTrialEnding(\App\Dto\WebhookEvent $event): void
    {
        Log::info('Trial ending soon', [
            'subscription_id' => $event->subscriptionId,
        ]);

        // TODO: Send notification to user
    }
}
