<?php

namespace App\Http\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ApiResponse Trait
 *
 * Provides a consistent JSON response format across all API endpoints.
 *
 * All controllers that return API responses should use this trait to
 * ensure a uniform response structure. This makes the API predictable
 * and easier to consume for frontend and third-party clients.
 *
 * Standard success response:
 * ```json
 * {
 *   "status": "success",
 *   "message": "Plans retrieved successfully.",
 *   "data": { ... }
 * }
 * ```
 *
 * Standard paginated response:
 * ```json
 * {
 *   "status": "success",
 *   "message": "Plans retrieved successfully.",
 *   "data": [ ... ],
 *   "meta": {
 *     "current_page": 1,
 *     "last_page": 5,
 *     "per_page": 10,
 *     "total": 50
 *   }
 * }
 * ```
 *
 * Standard error response:
 * ```json
 * {
 *   "status": "error",
 *   "message": "Plan not found.",
 *   "error": "plan_not_found"
 * }
 * ```
 */
trait ApiResponse
{
    /**
     * Return a standardized success response.
     *
     * Automatically detects paginated collections and injects
     * a `meta` key with pagination information.
     *
     * @param  mixed  $data  The response payload (resource, collection, or raw data)
     * @param  string|null  $message  Optional success message (omitted when null)
     * @param  int  $code  HTTP status code (default: 200)
     * @return JsonResponse
     */
    protected function success(mixed $data, ?string $message = null, int $code = 200): JsonResponse
    {
        $responseData = [
            'status' => 'success',
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
        ];

        if ($message !== null) {
            $responseData['message'] = $message;
        }

        // Include pagination metadata if the underlying resource is a LengthAwarePaginator
        if ($data instanceof AnonymousResourceCollection) {
            $paginator = $data->resource;

            if ($paginator instanceof LengthAwarePaginator) {
                $responseData['meta'] = [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ];
            }
        }

        return response()->json($responseData, $code);
    }

    /**
     * Return a standardized error response.
     *
     * @param  string  $message  The error message
     * @param  int  $code  HTTP status code (default: 400)
     * @param  mixed|null  $data  Optional additional error data
     * @return JsonResponse
     */
    protected function error(string $message, int $code = 400, mixed $data = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }
}
