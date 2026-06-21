<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL');
        $password = env('ADMIN_SEED_PASSWORD');
        $name = env('ADMIN_SEED_NAME', 'Eclise Admin');

        if (! $email || ! $password) {
            if (app()->environment('production')) {
                throw new RuntimeException('Set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD before seeding the first admin user.');
            }

            $email = $email ?: 'admin@eclisetech.com';
            $password = $password ?: 'password';
        }

        $permission = Permission::query()
            ->where('name', 'admin')
            ->where('status', 'active')
            ->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'permission_id' => $permission->id,
                'status' => 'active',
            ],
        );
    }
}
