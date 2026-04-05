<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Health Check Controller
 *
 * Provides health check endpoints for monitoring service status.
 * Used by load balancers, monitoring systems, and DevOps teams.
 */
class HealthController extends Controller
{
    use ApiResponse;

    /**
     * Basic health check - returns service status.
     *
     * GET /api/health
     */
    public function __invoke(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
            'services' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
            ],
        ];

        // Determine overall status
        $hasError = collect($health['services'])->contains('status', 'error');
        $hasWarning = collect($health['services'])->contains('status', 'warning');

        if ($hasError) {
            $health['status'] = 'error';
        } elseif ($hasWarning) {
            $health['status'] = 'warning';
        }

        $statusCode = match ($health['status']) {
            'ok' => 200,
            'warning' => 200,
            'error' => 503,
            default => 200,
        };

        return response()->json($health, $statusCode);
    }

    /**
     * Detailed health check with metrics.
     *
     * GET /api/health/detailed
     */
    public function detailed(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
            'services' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
            ],
            'metrics' => [
                'total_users' => User::count(),
                'total_plans' => Plan::where('is_active', true)->count(),
                'active_subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
                'past_due_subscriptions' => Subscription::where('status', 'past_due')->count(),
                'canceled_subscriptions' => Subscription::where('status', 'canceled')->count(),
            ],
            'configuration' => [
                'grace_period_days' => config('subscriptions.grace_period_days'),
                'scheduler_chunk_size' => config('subscriptions.scheduler.chunk_size'),
                'payment_driver' => config('subscriptions.payment.default_driver'),
                'audit_enabled' => config('subscriptions.audit.enabled'),
            ],
        ];

        // Determine overall status
        $hasError = collect($health['services'])->contains('status', 'error');
        $hasWarning = collect($health['services'])->contains('status', 'warning');

        if ($hasError) {
            $health['status'] = 'error';
        } elseif ($hasWarning) {
            $health['status'] = 'warning';
        }

        $statusCode = match ($health['status']) {
            'ok' => 200,
            'warning' => 200,
            'error' => 503,
            default => 200,
        };

        return response()->json($health, $statusCode);
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'connection' => DB::connection()->getDriverName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . time();
            cache()->put($key, 'ok', 10);
            $result = cache()->get($key);
            cache()->forget($key);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($result !== 'ok') {
                return [
                    'status' => 'error',
                    'message' => 'Cache read/write mismatch',
                ];
            }

            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Cache unavailable',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Check queue connectivity.
     */
    private function checkQueue(): array
    {
        try {
            $start = microtime(true);

            // Check if queue table is accessible
            $pendingJobs = DB::table('jobs')->count();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'driver' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Queue unavailable',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
}
