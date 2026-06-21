<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Permission::query()->updateOrCreate(
            ['name' => 'admin'],
            ['status' => 'active'],
        );

        $customer = Permission::query()->updateOrCreate(
            ['name' => 'customer'],
            ['status' => 'active'],
        );

        User::query()
            ->whereNull('permission_id')
            ->where('role', 'admin')
            ->update([
                'permission_id' => $admin->id,
                'status' => 'active',
            ]);

        User::query()
            ->whereNull('permission_id')
            ->update([
                'permission_id' => $customer->id,
                'status' => 'active',
            ]);
    }
}
