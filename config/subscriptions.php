<?php

/**
 * Subscription Engine Configuration
 *
 * Central configuration for all subscription business rules and constants.
 * This allows business rules to be changed without modifying code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Grace Period Configuration
    |--------------------------------------------------------------------------
    |
    | Number of days a user has to resolve a payment failure before their
    | subscription is automatically canceled. During this period, the user
    | retains full access to subscription features.
    |
    */

    'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Scheduler Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the automated subscription processing scheduler.
    | Controls how many subscriptions are processed per database chunk
    | and prevents overlapping scheduler runs.
    |
    */

    'scheduler' => [
        'chunk_size' => env('SUBSCRIPTION_SCHEDULER_CHUNK_SIZE', 100),
        'retry_failed_payments' => env('SUBSCRIPTION_RETRY_FAILED_PAYMENTS', true),
        'payment_retry_delay_hours' => env('SUBSCRIPTION_PAYMENT_RETRY_DELAY_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Default trial period settings for new subscriptions.
    |
    */

    'trial' => [
        'max_days' => env('SUBSCRIPTION_MAX_TRIAL_DAYS', 365),
        'allow_extension' => env('SUBSCRIPTION_ALLOW_TRIAL_EXTENSION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Default payment gateway driver and gateway-specific settings.
    | Supports multiple payment providers through a unified interface.
    |
    */

    'payment' => [
        'default_driver' => env('PAYMENT_DRIVER', 'stripe'),

        'drivers' => [
            'stripe' => [
                'api_key' => env('STRIPE_SECRET_KEY'),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                'api_version' => '2024-12-18.acacia',
            ],
            'paddle' => [
                'vendor_id' => env('PADDLE_VENDOR_ID'),
                'api_key' => env('PADDLE_API_KEY'),
                'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Custom rate limits for specific endpoints beyond the default API limits.
    |
    */

    'rate_limits' => [
        'subscribe' => [
            'max_attempts' => env('SUBSCRIBE_RATE_LIMIT_ATTEMPTS', 10),
            'decay_minutes' => env('SUBSCRIBE_RATE_LIMIT_DECAY', 1),
        ],
        'cancel' => [
            'max_attempts' => env('CANCEL_RATE_LIMIT_ATTEMPTS', 5),
            'decay_minutes' => env('CANCEL_RATE_LIMIT_DECAY', 1),
        ],
        'webhook' => [
            'max_attempts' => env('WEBHOOK_RATE_LIMIT_ATTEMPTS', 100),
            'decay_minutes' => env('WEBHOOK_RATE_LIMIT_DECAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Controls whether subscription state changes are logged to the database
    | for audit trail purposes.
    |
    */

    'audit' => [
        'enabled' => env('SUBSCRIPTION_AUDIT_ENABLED', true),
        'retention_days' => env('SUBSCRIPTION_AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Supported currencies and default currency for new subscriptions.
    |
    */

    'currencies' => [
        'supported' => ['usd', 'aed', 'egp'],
        'default' => env('SUBSCRIPTION_DEFAULT_CURRENCY', 'usd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezone Configuration
    |--------------------------------------------------------------------------
    |
    | Default timezone for all subscription-related datetime operations.
    | All timestamps are stored in UTC regardless of this setting.
    |
    */

    'timezone' => env('SUBSCRIPTION_TIMEZONE', 'UTC'),

];
