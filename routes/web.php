<?php

/**
 * Web Routes
 *
 * Defines browser-facing routes for the application.
 * This API-first project primarily serves JSON responses via /api/v1/.
 * The root route returns a JSON welcome message for API consumers.
 */

use Illuminate\Support\Facades\Route;

/**
 * GET /
 *
 * API welcome endpoint. Returns project info and version.
 * Useful for quick health checks and confirming the API is running.
 */
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'Subscription Engine'),
        'version' => '1.0.0',
        'environment' => config('app.env', 'local'),
        'documentation' => '/api/v1/plans',
        'health' => '/api/health',
    ]);
});
