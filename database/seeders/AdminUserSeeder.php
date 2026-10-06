<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the first administrator from ADMIN_EMAIL / ADMIN_PASSWORD when both
 * are set. Otherwise use `php artisan paystack:create-admin`.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set: no admin created. Run `php artisan paystack:create-admin`.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Admin {$email} already exists.");

            return;
        }

        $user = new User([
            'name' => env('ADMIN_NAME', 'Administrator'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->role = Role::Admin;
        $user->status = UserStatus::Active;
        $user->can_use_live = false;
        $user->email_verified_at = now();
        $user->save();

        $this->command?->info("Admin {$email} created.");
    }
}
