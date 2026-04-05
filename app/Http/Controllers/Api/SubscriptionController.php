<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AlreadySubscribedException;
use App\Exceptions\InvalidSubscriptionStateException;
use App\Exceptions\PlanNotFoundException;
use App\Exceptions\PriceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Traits\ApiResponse;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepositoryInterface;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Subscription Controller
 *
 * Handles all subscription lifecycle operations for authenticated users.
 * All business logic is delegated to SubscriptionLifecycleService.
 * The controller only coordinates authentication, authorization,
 * and response formatting.
 *
 * Endpoints:
 *   GET    /api/v1/subscriptions                          — List user's subscriptions
 *   POST   /api/v1/subscriptions/subscribe                — Subscribe to a plan
 *   POST   /api/v1/subscriptions/{id}/cancel              — Cancel a subscription
 *   POST   /api/v1/subscriptions/{id}/simulate-payment-success — Simulate payment recovery (dev)
 *   POST   /api/v1/subscriptions/{id}/simulate-payment-failure — Simulate payment failure (dev)
 */
class SubscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Create a new SubscriptionController instance.
     *
     * @param  SubscriptionLifecycleService  $lifecycleService  The subscription business logic layer
     * @param  SubscriptionRepositoryInterface  $subscriptionRepository  The subscription data access layer
     */
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycleService,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
    ) {
    }

    /**
     * Subscribe the authenticated user to a plan.
     *
     * @param  SubscribeRequest  $request  Validated subscription data
     * @return JsonResponse  The created subscription
     *
     * @throws AlreadySubscribedException  If the user already has an active subscription
     * @throws PlanNotFoundException  If the requested plan does not exist
     * @throws PriceNotFoundException  If no price exists for the given currency/billing cycle
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = Auth::id();

        $subscription = $this->lifecycleService->subscribe(
            $userId,
            $validated['plan_id'],
            $validated['currency'],
            $validated['billing_cycle']
        );

        return $this->success(
            SubscriptionResource::make($subscription)->resolve(),
            'Subscription created successfully.',
            201
        );
    }

    /**
     * List all subscriptions for the authenticated user.
     *
     * @return JsonResponse  Collection of the user's subscriptions
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        $subscriptions = $this->subscriptionRepository->findByUser(Auth::id());

        return $this->success(
            SubscriptionResource::collection($subscriptions)->resolve(),
            'Subscriptions retrieved successfully.'
        );
    }

    /**
     * Cancel the specified subscription.
     *
     * @param  int  $id  The subscription ID
     * @return JsonResponse  The canceled subscription
     *
     * @throws InvalidSubscriptionStateException  If the subscription is already canceled
     */
    public function cancel(int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->findByIdOrFail($id);

        // Use policy to check authorization
        Gate::authorize('cancel', $subscription);

        $subscription = $this->lifecycleService->cancel($subscription);

        return $this->success(
            SubscriptionResource::make($subscription)->resolve(),
            'Subscription canceled successfully.'
        );
    }

    /**
     * Simulate a successful payment (for testing/development).
     *
     * Recovers a past_due subscription back to active state.
     *
     * @param  int  $id  The subscription ID
     * @return JsonResponse  The reactivated subscription
     *
     * @throws InvalidSubscriptionStateException  If the subscription is not in past_due state
     */
    public function simulatePaymentSuccess(int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->findByIdOrFail($id);

        Gate::authorize('simulatePayment', $subscription);

        $subscription = $this->lifecycleService->handlePaymentSuccess($subscription);

        return $this->success(
            SubscriptionResource::make($subscription)->resolve(),
            'Payment simulated successfully. Subscription reactivated.'
        );
    }

    /**
     * Simulate a payment failure (for testing/development).
     *
     * Moves an active subscription to past_due with a 3-day grace period.
     *
     * @param  int  $id  The subscription ID
     * @return JsonResponse  The past_due subscription
     *
     * @throws InvalidSubscriptionStateException  If the subscription is not in an active state
     */
    public function simulatePaymentFailure(int $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->findByIdOrFail($id);

        Gate::authorize('simulatePayment', $subscription);

        $subscription = $this->lifecycleService->handlePaymentFailure($subscription);

        return $this->success(
            SubscriptionResource::make($subscription)->resolve(),
            'Payment failure simulated. Subscription is now past_due.'
        );
    }
}
