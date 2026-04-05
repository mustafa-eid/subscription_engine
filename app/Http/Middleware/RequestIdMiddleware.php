<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request ID Middleware
 *
 * Adds a unique request ID to each request for distributed tracing.
 * The ID is added to the request headers and response headers,
 * making it easier to track requests across services and logs.
 */
class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Use existing request ID if present, otherwise generate a new one
        $requestId = $request->header('X-Request-ID', (string) Str::uuid());

        // Add to request instance for use in controllers/logging
        $request->headers->set('X-Request-ID', $requestId);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Response-Time', $this->getResponseTime($request));

        return $response;
    }

    /**
     * Calculate response time in milliseconds.
     */
    protected function getResponseTime(Request $request): string
    {
        $start = $request->server->get('REQUEST_TIME_FLOAT');

        if ($start === null) {
            return '0';
        }

        return (string) round((microtime(true) - $start) * 1000, 2);
    }
}
