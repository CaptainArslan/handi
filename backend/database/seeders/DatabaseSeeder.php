<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('handi.admin');

        if (filled($admin['email']) && filled($admin['password'])) {
            User::updateOrCreate(
                ['email' => strtolower($admin['email'])],
                ['name' => $admin['name'], 'password' => $admin['password']],
            )->forceFill(['is_admin' => true])->save();
        } else {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set in .env, so no admin was created. Run: php artisan handi:create-admin you@example.com');
        }

        $this->call(CatalogSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
