<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root database seeder.
 *
 * Orchestrates the execution of all individual seeders.
 * Run with: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeds demo plan data with multi-currency pricing
     * and a test user for authenticated endpoint testing.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            TestUserSeeder::class,
        ]);
    }
}
