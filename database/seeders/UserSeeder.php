<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the two staff accounts the store starts with.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@fathimasupermarket.test'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'phone' => '0551000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@fathimasupermarket.test'],
            [
                'name' => 'Store Manager',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'phone' => '0551000002',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
