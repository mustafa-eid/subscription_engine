<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Timezone Verification Command
 *
 * Verifies that all datetime fields in the database are stored in UTC
 * and provides a report on timezone consistency.
 */
class VerifyTimezones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timezones:verify {--fix : Attempt to fix non-UTC timestamps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that all timestamps are in UTC';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking timezone consistency...');
        $this->newLine();

        $subscriptions = Subscription::all();
        $total = $subscriptions->count();
        $utcCount = 0;
        $nonUtcCount = 0;
        $fixedCount = 0;

        $this->withProgressBar($subscriptions, function ($subscription) use (&$utcCount, &$nonUtcCount, &$fixedCount) {
            $fields = ['trial_ends_at', 'starts_at', 'ends_at', 'grace_period_ends_at', 'created_at', 'updated_at'];

            foreach ($fields as $field) {
                $value = $subscription->$field;

                if ($value instanceof Carbon) {
                    if ($value->timezoneName === 'UTC') {
                        $utcCount++;
                    } else {
                        $nonUtcCount++;

                        // Fix if requested
                        if ($this->option('fix')) {
                            $subscription->$field = $value->tz('UTC');
                            $fixedCount++;
                        }
                    }
                }
            }
        });

        $this->newLine(2);
        $this->info('📊 Timezone Report:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total subscriptions', $total],
                ['UTC timestamps', $utcCount],
                ['Non-UTC timestamps', $nonUtcCount],
                ['Fixed timestamps', $fixedCount],
            ]
        );

        if ($nonUtcCount > 0 && !$this->option('fix')) {
            $this->warn('⚠️  Found non-UTC timestamps. Run with --fix to attempt automatic correction.');
        } elseif ($nonUtcCount === 0) {
            $this->info('✅ All timestamps are in UTC!');
        }

        return Command::SUCCESS;
    }
}
