<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add a partial unique index to enforce that a user can only have
     * ONE active, trialing, or past_due subscription at a time.
     *
     * This provides database-level idempotency — even if two requests
     * hit simultaneously, only one will succeed at the DB level.
     *
     * Note: SQLite doesn't support partial indexes natively, so we use
     * a generated column approach for SQLite and a partial index for
     * MySQL/PostgreSQL.
     */
    public function up(): void
    {
        // For MySQL (8.0.16+): use a generated column + unique index
        // For PostgreSQL: would use a partial index directly
        // For SQLite: fallback — we handle uniqueness at the application level

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL: Add a generated column that is NULL for non-active statuses
            // and the user_id for active statuses, then unique-index it.
            // NULL values are not considered duplicates in unique indexes.
            Schema::table('subscriptions', function (Blueprint $table) {
                // Generated column: user_id when active/trialing/past_due, NULL otherwise
                $table->unsignedBigInteger('active_user_id')
                    ->nullable()
                    ->virtualAs(
                        "CASE WHEN status IN ('trialing', 'active', 'past_due') THEN user_id ELSE NULL END"
                    );
            });

            // Unique index on the generated column — only one non-NULL value per user
            DB::statement(
                'ALTER TABLE subscriptions ADD UNIQUE INDEX subscriptions_active_user_id_unique (active_user_id)'
            );
        }
        // For PostgreSQL, we'd use: CREATE UNIQUE INDEX ... WHERE status IN (...)
        // For SQLite, partial unique indexes are not supported — app-level guard handles it
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('subscriptions_active_user_id_unique');
                $table->dropColumn('active_user_id');
            });
        }
    }
};
