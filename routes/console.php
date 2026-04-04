<?php

/**
 * Console / Scheduler Routes
 *
 * Defines Artisan commands and scheduled tasks for the application.
 *
 * The Laravel scheduler must be triggered by adding this cron entry
 * to your server's crontab:
 *
 *   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
 *
 * Once the cron is configured, all scheduled commands below will
 * execute automatically at their defined intervals.
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Inspire Command
 *
 * A built-in Laravel artisan command that displays an inspiring quote.
 * Usage: php artisan inspire
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
*/

/**
 * Process Expired Trials and Grace Periods
 *
 * Runs daily at midnight UTC. This command:
 *   1. Finds subscriptions with expired trials (trialing → active)
 *   2. Finds subscriptions with expired grace periods (past_due → canceled)
 *
 * Options:
 *   --chunk-size=100  Number of records to process per batch
 *   --dry-run         Preview transitions without applying them
 *
 * Safeguards:
 *   - withoutOverlapping() prevents concurrent executions
 *   - onOneServer() ensures single execution in multi-server deployments
 *   - Output is appended to storage/logs/scheduler-subscriptions.log
 */
Schedule::command('subscriptions:process --chunk-size=100')
    ->dailyAt('00:00')
    ->timezone('UTC')
    ->withoutOverlapping()    // Prevent concurrent executions
    ->onOneServer()           // Only runs on one server in multi-server deployments
    ->appendOutputTo(storage_path('logs/scheduler-subscriptions.log'));
