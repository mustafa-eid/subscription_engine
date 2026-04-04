<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ProcessSubscriptions
 *
 * Scheduled daily command that processes subscription lifecycle transitions:
 *  1. Expired trials → active (simulates successful payment)
 *  2. Expired grace periods → canceled
 *
 * Design decisions:
 *  - Uses chunked queries to handle large datasets without memory exhaustion.
 *  - Each subscription is re-fetched inside the transaction to ensure we're
 *    working with the latest state (idempotency guard against concurrent runs).
 *  - All state transitions go through SubscriptionLifecycleService, which
 *    handles event dispatch, logging, and database transactions.
 *  - Errors are caught and logged — one failure does not stop the batch.
 */
class ProcessSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process
                            {--chunk-size=100 : Number of subscriptions to process per chunk}
                            {--dry-run : Preview transitions without applying them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired trials and grace period subscriptions';

    /**
     * Number of subscriptions processed per database chunk.
     */
    private int $chunkSize;

    /**
     * Whether to actually apply transitions or just preview them.
     */
    private bool $dryRun;

    /**
     * Counters for the summary report.
     */
    private int $trialsProcessed = 0;
    private int $trialsActivated = 0;
    private int $trialsFailed = 0;
    private int $gracePeriodsProcessed = 0;
    private int $gracePeriodsCanceled = 0;
    private int $gracePeriodsFailed = 0;

    /**
     * Execute the console command.
     */
    public function handle(
        SubscriptionLifecycleService $lifecycleService,
    ): int {
        $this->chunkSize = (int) $this->option('chunk-size');
        $this->dryRun = (bool) $this->option('dry-run');

        $this->info('Starting subscription processing...');
        $this->info("Chunk size: {$this->chunkSize} | Dry run: " . ($this->dryRun ? 'YES' : 'NO'));
        $this->newLine();

        // Process expired trials
        $this->processExpiredTrials($lifecycleService);

        $this->newLine();

        // Process expired grace periods
        $this->processExpiredGracePeriods($lifecycleService);

        $this->newLine();
        $this->printSummary();

        return Command::SUCCESS;
    }

    /**
     * Process subscriptions with expired trials.
     *
     * Uses chunked iteration to avoid loading all records into memory.
     * Each subscription is re-fetched within the service method's transaction
     * to ensure idempotency — if another process already transitioned it,
     * the service will throw InvalidSubscriptionStateException which we catch.
     */
    private function processExpiredTrials(SubscriptionLifecycleService $lifecycleService): void
    {
        $this->info('Processing expired trials...');

        // Count total for progress bar
        $total = Subscription::query()
            ->where('status', SubscriptionStatus::TRIALING->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->count();

        if ($total === 0) {
            $this->info('No expired trials found.');

            return;
        }

        $this->info("Found {$total} expired trial(s) to process.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Chunk through expired trials — avoids loading everything into memory
        Subscription::query()
            ->where('status', SubscriptionStatus::TRIALING->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->orderBy('trial_ends_at') // Process oldest first
            ->chunk($this->chunkSize, function ($subscriptions) use ($lifecycleService, $bar) {
                foreach ($subscriptions as $subscription) {
                    $this->trialsProcessed++;

                    if ($this->dryRun) {
                        $this->line("  [DRY RUN] Would activate subscription {$subscription->id} (user: {$subscription->user_id})");
                        $bar->advance();

                        continue;
                    }

                    try {
                        $lifecycleService->handleTrialExpiration($subscription);
                        $this->trialsActivated++;

                        Log::info('Scheduler: Trial expired — subscription activated.', [
                            'subscription_id' => $subscription->id,
                            'user_id' => $subscription->user_id,
                            'old_status' => SubscriptionStatus::TRIALING->value,
                            'new_status' => SubscriptionStatus::ACTIVE->value,
                            'processed_at' => now()->toIso8601String(),
                        ]);
                    } catch (\App\Exceptions\InvalidSubscriptionStateException $e) {
                        // Idempotency: another process may have already handled this subscription.
                        // This is expected in concurrent scenarios — log and continue.
                        $this->trialsFailed++;

                        Log::warning('Scheduler: Skipped expired trial — invalid state (possibly already processed).', [
                            'subscription_id' => $subscription->id,
                            'user_id' => $subscription->user_id,
                            'current_status' => $subscription->status->value,
                            'error' => $e->getMessage(),
                        ]);
                    } catch (\Throwable $e) {
                        // Unexpected error — log but continue processing remaining subscriptions
                        $this->trialsFailed++;

                        Log::error('Scheduler: Failed to process expired trial.', [
                            'subscription_id' => $subscription->id,
                            'user_id' => $subscription->user_id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
    }

    /**
     * Process subscriptions with expired grace periods.
     *
     * Same chunked, idempotent approach as expired trials.
     */
    private function processExpiredGracePeriods(SubscriptionLifecycleService $lifecycleService): void
    {
        $this->info('Processing expired grace periods...');

        // Count total for progress bar
        $total = Subscription::query()
            ->where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->count();

        if ($total === 0) {
            $this->info('No expired grace periods found.');

            return;
        }

        $this->info("Found {$total} expired grace period(s) to process.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Chunk through expired grace periods
        Subscription::query()
            ->where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->orderBy('grace_period_ends_at') // Process oldest first
            ->chunk($this->chunkSize, function ($subscriptions) use ($lifecycleService, $bar) {
                foreach ($subscriptions as $subscription) {
                    $this->gracePeriodsProcessed++;

                    if ($this->dryRun) {
                        $this->line("  [DRY RUN] Would cancel subscription {$subscription->id} (user: {$subscription->user_id})");
                        $bar->advance();

                        continue;
                    }

                    try {
                        $lifecycleService->handleGracePeriodExpiration($subscription);
                        $this->gracePeriodsCanceled++;

                        Log::warning('Scheduler: Grace period expired — subscription canceled.', [
                            'subscription_id' => $subscription->id,
                            'user_id' => $subscription->user_id,
                            'old_status' => SubscriptionStatus::PAST_DUE->value,
                            'new_status' => SubscriptionStatus::CANCELED->value,
                            'processed_at' => now()->toIso8601String(),
                        ]);
                    } catch (\App\Exceptions\InvalidSubscriptionStateException $e) {
                        // Idempotency: another process may have already handled this subscription.
                        $this->gracePeriodsFailed++;

                        Log::warning('Scheduler: Skipped expired grace period — invalid state (possibly already processed).', [
                            'subscription_id' => $subscription->id,
                            'user_id' => $subscription->user_id,
                            'current_status' => $subscription->status->value,
                            'error' => $e->getMessage(),
                        ]);
                    } catch (\Throwable $e) {
                        // Unexpected error — log but continue processing remaining subscriptions
                        $this->gracePeriodsFailed++;

                        Log::error('Scheduler: Failed to process expired grace period.', [
                            'subscription_id' => $subscription->id,
                            'user_id' => $subscription->user_id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
    }

    /**
     * Print a summary table of processing results.
     */
    private function printSummary(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('           PROCESSING SUMMARY');
        $this->info('═══════════════════════════════════════════');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Expired trials processed', $this->trialsProcessed],
                ['  → Activated', "<info>{$this->trialsActivated}</info>"],
                ['  → Failed/Skipped', $this->trialsFailed > 0 ? "<comment>{$this->trialsFailed}</comment>" : 0],
                ['Expired grace periods processed', $this->gracePeriodsProcessed],
                ['  → Canceled', "<info>{$this->gracePeriodsCanceled}</info>"],
                ['  → Failed/Skipped', $this->gracePeriodsFailed > 0 ? "<comment>{$this->gracePeriodsFailed}</comment>" : 0],
            ]
        );

        $this->info('═══════════════════════════════════════════');

        // Log the summary for audit trail
        Log::info('Scheduler: Subscription processing complete.', [
            'trials_processed' => $this->trialsProcessed,
            'trials_activated' => $this->trialsActivated,
            'trials_failed' => $this->trialsFailed,
            'grace_periods_processed' => $this->gracePeriodsProcessed,
            'grace_periods_canceled' => $this->gracePeriodsCanceled,
            'grace_periods_failed' => $this->gracePeriodsFailed,
        ]);
    }
}
