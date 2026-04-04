<?php

/**
 * Web Routes
 *
 * Defines browser-facing routes for the application.
 * This API-first project primarily serves JSON responses via /api/v1/.
 * The default welcome route is kept for health-check purposes.
 */

use Illuminate\Support\Facades\Route;

/**
 * GET /
 *
 * Default welcome view. In production, this endpoint
 * can be replaced with a dashboard or removed entirely
 * for a pure API application.
 */
Route::get('/', function () {
    return view('welcome');
});
