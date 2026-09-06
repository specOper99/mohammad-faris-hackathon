<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('exoplanet.seed_admin_email', 'admin@localhost');
        $password = config('exoplanet.seed_admin_password');
        if (! is_string($password) || strlen($password) < 10) {
            return;
        }

        $user = User::query()->withDeleted()->whereRaw('lower(email) = ?', [strtolower($email)])->first();
        if ($user === null) {
            $user = User::query()->create([
                'first_name' => 'Challenge',
                'last_name' => 'Admin',
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'is_active' => true,
                'locale' => 'en',
            ]);
        }

        $user->syncRoles(['admin']);
    }
}
