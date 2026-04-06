<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Test User Seeder
 *
 * Creates a demo user with a known password for testing authenticated endpoints.
 * After seeding, the token is displayed in the console for easy copy-paste.
 *
 * Credentials:
 *   Email: test@example.com
 *   Password: TestPass123
 */
class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('TestPass123'),
            ]
        );

        // Create a Sanctum token for testing
        $token = $user->createToken('test-token')->plainTextToken;

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Test User Created');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Email:    test@example.com');
        $this->command->info('  Password: TestPass123');
        $this->command->info('  Token:    '.$token);
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('  Use this token in the Authorization header:');
        $this->command->info('  Authorization: Bearer '.$token);
        $this->command->info('');
    }
}
