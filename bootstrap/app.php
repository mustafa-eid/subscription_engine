<?php

use App\Exceptions\AlreadySubscribedException;
use App\Exceptions\InvalidSubscriptionStateException;
use App\Exceptions\PlanNotFoundException;
use App\Exceptions\PriceNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ModelNotFoundException → consistent 404 format (must be first)
        $exceptions->render(function (ModelNotFoundException $e) {
            $model = class_basename($e->getModel());

            return response()->json([
                'status' => 'error',
                'message' => "{$model} not found.",
                'error' => 'not_found',
            ], 404);
        });

        // Domain exceptions → structured JSON responses
        $exceptions->render(function (AlreadySubscribedException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error' => 'already_subscribed',
            ], 409);
        });

        $exceptions->render(function (PlanNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error' => 'plan_not_found',
            ], 404);
        });

        $exceptions->render(function (PriceNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error' => 'price_not_found',
            ], 404);
        });

        $exceptions->render(function (InvalidSubscriptionStateException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error' => 'invalid_subscription_state',
            ], 422);
        });
    })->create();
