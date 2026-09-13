<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformUserSeeder extends Seeder
{
    /**
     * The account that runs the platform itself.
     */
    public function run(): void
    {
        PlatformUser::updateOrCreate(
            ['email' => env('PLATFORM_ADMIN_EMAIL', 'admin@pgbiller.test')],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make(env('PLATFORM_ADMIN_PASSWORD', 'password')),
                'is_active' => true,
            ],
        );
    }
}
