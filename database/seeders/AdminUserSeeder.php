<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'marcel@haymail.ca'],
            [
                'name' => 'Marcel (Admin)',
                'email' => 'marcel@haymail.ca',
                'password' => Hash::make('TempPassword123!'),
                'role' => 'admin',
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Admin user created: marcel@haymail.ca');
        $this->command->warn('⚠ Temporary password: TempPassword123!');
        $this->command->warn('⚠ User will be forced to change password on first login');
    }
}
