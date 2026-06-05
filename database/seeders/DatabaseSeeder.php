<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Part;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@eclisetech.com'],
            [
                'name' => 'Eclise Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        );

        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ],
        );

        $categoryNames = [
            'New Phones',
            'Used Phones',
            'Phone Accessories',
            'New Computers',
            'Used Computers',
            'Computer Accessories',
        ];

        $categories = collect($categoryNames)->mapWithKeys(function (string $name): array {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => 'product'],
            );

            return [$name => $category];
        });

        $products = [
            [
                'category' => 'Used Phones',
                'name' => 'Apple iPhone 14 128GB',
                'sku' => 'ECL-PHN-IPH14-128',
                'brand' => 'Apple',
                'model' => 'iPhone 14',
                'condition' => 'Used',
                'price' => 679.00,
                'sale_price' => 629.00,
                'quantity' => 4,
                'description' => 'Inspected used iPhone with clean display, tested battery health, and charger cable included.',
            ],
            [
                'category' => 'New Phones',
                'name' => 'Samsung Galaxy S24 256GB',
                'sku' => 'ECL-PHN-S24-256',
                'brand' => 'Samsung',
                'model' => 'Galaxy S24',
                'condition' => 'New',
                'price' => 999.00,
                'sale_price' => null,
                'quantity' => 3,
                'description' => 'Factory-new Galaxy phone with manufacturer warranty and Eclise setup support.',
            ],
            [
                'category' => 'Used Computers',
                'name' => 'Lenovo ThinkPad T14',
                'sku' => 'ECL-PC-T14',
                'brand' => 'Lenovo',
                'model' => 'ThinkPad T14',
                'condition' => 'Refurbished',
                'price' => 749.00,
                'sale_price' => 699.00,
                'quantity' => 2,
                'description' => 'Business laptop refurbished for everyday productivity with SSD storage and Windows installed.',
            ],
            [
                'category' => 'Phone Accessories',
                'name' => 'USB-C Fast Charger Kit',
                'sku' => 'ECL-ACC-USBC-KIT',
                'brand' => 'Eclise',
                'model' => '30W USB-C',
                'condition' => 'New',
                'price' => 29.99,
                'sale_price' => null,
                'quantity' => 20,
                'description' => 'Compact charger and USB-C cable for compatible phones, tablets, and accessories.',
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $categories[$product['category']]->id,
                    'name' => $product['name'],
                    'slug' => Str::slug($product['name']),
                    'brand' => $product['brand'],
                    'model' => $product['model'],
                    'condition' => $product['condition'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'sale_price' => $product['sale_price'],
                    'quantity' => $product['quantity'],
                    'status' => 'Active',
                ],
            );
        }

        $parts = [
            ['iPhone 13 OLED Screen', 'Phone', 'Apple', 'iPhone 13', 'Screens', 139.99, 'In stock'],
            ['Galaxy S22 Battery', 'Phone', 'Samsung', 'Galaxy S22', 'Batteries', 64.99, 'In stock'],
            ['MacBook Air 13-inch Keyboard', 'Laptop', 'Apple', 'A2337', 'Keyboards', 189.00, 'Special order'],
            ['Dell XPS 13 Laptop Screen', 'Laptop', 'Dell', 'XPS 13 9310', 'Laptop Screens', 249.00, 'Check availability'],
        ];

        foreach ($parts as [$name, $deviceType, $brand, $modelCompatibility, $partCategory, $price, $stockStatus]) {
            Part::query()->updateOrCreate(
                [
                    'name' => $name,
                    'brand' => $brand,
                    'model_compatibility' => $modelCompatibility,
                ],
                [
                    'device_type' => $deviceType,
                    'part_category' => $partCategory,
                    'price' => $price,
                    'stock_status' => $stockStatus,
                    'supplier' => 'MobileSentrix',
                    'last_synced_at' => now(),
                ],
            );
        }

        $booking = RepairBooking::query()->updateOrCreate(
            ['tracking_number' => 'ECL-REP-2026-0001'],
            [
                'user_id' => $customer->id,
                'customer_name' => $customer->name,
                'email' => $customer->email,
                'phone' => '416-555-0199',
                'device_type' => 'Phone',
                'device_brand' => 'Apple',
                'device_model' => 'iPhone 13',
                'issue_category' => 'Screen repair',
                'issue_description' => 'Cracked display after a drop. Touch still works.',
                'preferred_appointment_date' => now()->addDays(2)->toDateString(),
                'preferred_appointment_time' => '10:30',
                'terms_accepted' => true,
                'status' => 'Diagnosis in Progress',
                'estimated_completion_date' => now()->addDays(4)->toDateString(),
                'customer_notes' => 'Device inspection started. We will confirm parts availability before repair.',
            ],
        );

        $booking->statusUpdates()->updateOrCreate(
            ['status' => 'Submitted'],
            ['note' => 'Repair request received.', 'is_customer_visible' => true],
        );

        $booking->statusUpdates()->updateOrCreate(
            ['status' => 'Diagnosis in Progress'],
            ['note' => 'Technician is checking display and frame condition.', 'is_customer_visible' => true],
        );
    }
}
