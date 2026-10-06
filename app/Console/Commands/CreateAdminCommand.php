<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'paystack:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create (or promote) an administrator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name', 'Administrator');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min 10 chars, mixed case, numbers)');

        $existing = User::where('email', $email)->first();

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => $existing ? ['nullable'] : ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $existing ?? new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = Role::Admin;
        $user->status = UserStatus::Active;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info($existing ? "{$email} promoted to administrator." : "Administrator {$email} created.");

        return self::SUCCESS;
    }
}
