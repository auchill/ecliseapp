<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Customer logins for testing. Every seeded order, repair and payment belongs to one of these,
 * so each scenario can be reproduced by signing in.
 */
class CustomerSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const CUSTOMERS = [
        [
            'name' => 'Amara Okafor',
            'email' => 'amara@example.com',
            'phone' => '416-555-0101',
            'street_address' => '18 Yonge Street',
            'address_line_2' => 'Unit 402',
            'city' => 'Toronto',
            'province' => 'ON',
            'postal_code' => 'M5E 1R1',
        ],
        [
            'name' => 'Daniel Boucher',
            'email' => 'daniel@example.com',
            'phone' => '514-555-0142',
            'street_address' => '2200 Rue Sainte-Catherine',
            'city' => 'Montreal',
            'province' => 'QC',
            'postal_code' => 'H3H 1M2',
        ],
        [
            'name' => 'Priya Raman',
            'email' => 'priya@example.com',
            'phone' => '604-555-0177',
            'street_address' => '750 Granville Street',
            'city' => 'Vancouver',
            'province' => 'BC',
            'postal_code' => 'V6Z 1E9',
        ],
        [
            'name' => 'Marcus Bell',
            'email' => 'marcus@example.com',
            'phone' => '403-555-0188',
            'street_address' => '311 8 Avenue SW',
            'city' => 'Calgary',
            'province' => 'AB',
            'postal_code' => 'T2P 1C5',
        ],
    ];

    public function run(): void
    {
        $permissionId = Permission::query()->where('name', 'customer')->value('id');

        foreach (self::CUSTOMERS as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role' => 'customer',
                    'permission_id' => $permissionId,
                    'status' => 'active',
                ],
            );

            Customer::forUser($user)->update([
                'full_name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'street_address' => $data['street_address'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'city' => $data['city'],
                'province' => $data['province'],
                'postal_code' => $data['postal_code'],
                'country' => 'Canada',
                'customer_since' => now()->subMonths(random_int(2, 20)),
                'status' => 'active',
            ]);
        }
    }
}
