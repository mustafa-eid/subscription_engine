<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Http\Traits\ApiResponse;
use App\Models\Plan;
use App\Repositories\PlanRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Plan Controller
 *
 * Handles CRUD operations for subscription plans.
 * All business logic is delegated to the PlanRepositoryInterface.
 * The controller remains thin — it only coordinates requests,
 * repository calls, and resource formatting.
 *
 * Endpoints:
 *   GET    /api/v1/plans         — List active plans (paginated, public)
 *   GET    /api/v1/plans/{id}    — Get single plan (public)
 *   POST   /api/v1/plans         — Create plan (authenticated)
 *   PUT    /api/v1/plans/{id}    — Update plan (authenticated)
 *   DELETE /api/v1/plans/{id}    — Delete plan (authenticated)
 */
class PlanController extends Controller
{
    use ApiResponse;

    /**
     * Create a new PlanController instance.
     *
     * @param  PlanRepositoryInterface  $planRepository  The plan data access layer
     */
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
    ) {
    }

    /**
     * Display a paginated listing of active plans with prices.
     *
     * @param  Request  $request  The HTTP request (supports ?per_page=N query param)
     * @return JsonResponse  Paginated list of active plans
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 50);

        $plans = $this->planRepository->paginateActive($perPage);

        return $this->success(
            PlanResource::collection($plans),
            'Plans retrieved successfully.'
        );
    }

    /**
     * Store a newly created plan with prices.
     *
     * @param  StorePlanRequest  $request  Validated plan data
     * @return JsonResponse  The created plan with prices
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        Gate::authorize('create', Plan::class);

        $validated = $request->validated();

        $plan = Plan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trial_days' => $validated['trial_days'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Create associated prices
        foreach ($validated['prices'] as $priceData) {
            $plan->prices()->create([
                'currency' => $priceData['currency'],
                'billing_cycle' => $priceData['billing_cycle'],
                'price' => $priceData['price'],
            ]);
        }

        // Reload with prices for the response
        $plan->load('prices');

        return $this->success(
            PlanResource::make($plan),
            'Plan created successfully.',
            201
        );
    }

    /**
     * Display the specified plan.
     *
     * @param  int  $id  The plan ID
     * @return JsonResponse  The plan with prices
     */
    public function show(int $id): JsonResponse
    {
        $plan = $this->planRepository->findByIdOrFail($id);

        Gate::authorize('view', $plan);

        return $this->success(
            PlanResource::make($plan),
            'Plan retrieved successfully.'
        );
    }

    /**
     * Update the specified plan.
     *
     * When prices are provided, all existing prices are replaced
     * with the new set (atomic replace operation).
     *
     * @param  UpdatePlanRequest  $request  Validated update data
     * @param  int  $id  The plan ID
     * @return JsonResponse  The updated plan with prices
     */
    public function update(UpdatePlanRequest $request, int $id): JsonResponse
    {
        $plan = $this->planRepository->findByIdOrFail($id);

        Gate::authorize('update', $plan);

        $validated = $request->validated();

        // Update plan attributes
        $plan->update(array_filter($validated, fn ($_, $key) => $key !== 'prices', ARRAY_FILTER_USE_BOTH));

        // Update prices if provided — replace all prices atomically
        if (isset($validated['prices'])) {
            $plan->prices()->delete();

            foreach ($validated['prices'] as $priceData) {
                $plan->prices()->create([
                    'currency' => $priceData['currency'],
                    'billing_cycle' => $priceData['billing_cycle'],
                    'price' => $priceData['price'],
                ]);
            }
        }

        $plan->load('prices');

        return $this->success(
            PlanResource::make($plan),
            'Plan updated successfully.'
        );
    }

    /**
     * Remove the specified plan (soft delete).
     *
     * @param  int  $id  The plan ID
     * @return JsonResponse  Success confirmation
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = $this->planRepository->findByIdOrFail($id);

        Gate::authorize('delete', $plan);

        $plan->delete();

        return $this->success(
            null,
            'Plan deleted successfully.'
        );
    }
}
