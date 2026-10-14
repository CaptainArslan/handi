<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'handi:create-admin {email} {--name=Admin}';

    protected $description = 'Create an admin user for /admin, or promote an existing user to admin.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = (string) $this->secret('Password (min 8 characters)');

            if (strlen($password) < 8) {
                $this->error('Password must be at least 8 characters.');

                return self::FAILURE;
            }

            $user = User::create([
                'name' => (string) $this->option('name'),
                'email' => $email,
                'password' => $password,
            ]);
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info("{$email} can now sign in at /admin.");

        return self::SUCCESS;
    }
}
