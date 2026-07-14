<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCondition;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use App\Models\Repair;
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
        $this->call(DefaultPermissionsSeeder::class);
        $this->call(ShippingSeeder::class);
        $this->call(CatalogTaxonomySeeder::class);
        $this->call(ProductLookupSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(ReferenceDataSeeder::class);

        $customerPermissionId = Permission::query()->where('name', 'customer')->value('id');

        $this->call(AdminUserSeeder::class);

        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'permission_id' => $customerPermissionId,
                'status' => 'active',
            ],
        );
        // sh:codes - Use the current lookup for the seeds
        // $products = [
        //     [
        //         'category' => 'Used Phones',
        //         'name' => 'Apple iPhone 14 128GB',
        //         'sku' => 'ECL-PHN-IPH14-128',
        //         'brand' => 'Apple',
        //         'model' => 'iPhone 14',
        //         'condition' => 'Used',
        //         'regular_price' => 679.00,
        //         'sale_price' => 629.00,
        //         'quantity' => 4,
        //         'description' => 'Inspected used iPhone with clean display, tested battery health, and charger cable included.',
        //     ],
        //     [
        //         'category' => 'New Phones',
        //         'name' => 'Samsung Galaxy S24 256GB',
        //         'sku' => 'ECL-PHN-S24-256',
        //         'brand' => 'Samsung',
        //         'model' => 'Galaxy S24',
        //         'condition' => 'New',
        //         'regular_price' => 999.00,
        //         'sale_price' => null,
        //         'quantity' => 3,
        //         'description' => 'Factory-new Galaxy phone with manufacturer warranty and Eclise setup support.',
        //     ],
        //     [
        //         'category' => 'Used Computers',
        //         'name' => 'Lenovo ThinkPad T14',
        //         'sku' => 'ECL-PC-T14',
        //         'brand' => 'Lenovo',
        //         'model' => 'ThinkPad T14',
        //         'condition' => 'Refurbished',
        //         'regular_price' => 749.00,
        //         'sale_price' => 699.00,
        //         'quantity' => 2,
        //         'description' => 'Business laptop refurbished for everyday productivity with SSD storage and Windows installed.',
        //     ],
        //     [
        //         'category' => 'Phone Accessories',
        //         'name' => 'USB-C Fast Charger Kit',
        //         'sku' => 'ECL-ACC-USBC-KIT',
        //         'brand' => 'Eclise',
        //         'model' => '30W USB-C',
        //         'condition' => 'New',
        //         'regular_price' => 29.99,
        //         'sale_price' => null,
        //         'quantity' => 20,
        //         'description' => 'Compact charger and USB-C cable for compatible phones, tablets, and accessories.',
        //     ],
        // ];

        // foreach ($products as $product) {
        //     $brand = ProductBrand::query()->updateOrCreate(
        //         ['slug' => Str::slug($product['brand'])],
        //         ['name' => $product['brand'], 'status' => 'active'],
        //     );
        //     $model = ProductModel::query()->updateOrCreate(
        //         ['slug' => Str::slug($product['model'])],
        //         ['name' => $product['model'], 'product_brand_id' => $brand->id, 'status' => 'active'],
        //     );
        //     $condition = ProductCondition::query()->where('slug', Str::slug($product['condition']))->first();
        //     $storage = Str::contains($product['name'], ['128GB', '256GB'])
        //         ? ProductSize::query()->updateOrCreate(
        //             ['slug' => Str::slug(Str::contains($product['name'], '256GB') ? '256GB' : '128GB')],
        //             ['name' => Str::contains($product['name'], '256GB') ? '256GB' : '128GB', 'type' => 'storage', 'is_active' => true],
        //         )
        //         : null;
        //     $network = ProductNetwork::query()->updateOrCreate(
        //         ['slug' => 'unlocked'],
        //         ['name' => 'Unlocked', 'status' => 'active'],
        //     );

        //     $createdProduct = Product::query()->updateOrCreate(
        //         ['sku' => $product['sku']],
        //         [
        //             'product_category_id' => ProductCategory::query()->where('slug', Str::slug(match ($product['category']) {
        //                 'Used Phones', 'New Phones' => 'Phone',
        //                 'Used Computers', 'New Computers' => 'Laptop',
        //                 'Phone Accessories', 'Computer Accessories' => 'Accessories',
        //                 default => $product['category'],
        //             }))->value('id'),
        //             'product_brand_id' => $brand->id,
        //             'product_model_id' => $model->id,
        //             'product_condition_id' => $condition?->id,
        //             'product_network_id' => $network->id,
        //             'name' => $product['name'],
        //             'slug' => Str::slug($product['name']),
        //             'description' => $product['description'],
        //             'regular_price' => $product['regular_price'],
        //             'sale_price' => $product['sale_price'],
        //             'quantity' => $product['quantity'],
        //             'source' => 'manual',
        //             'is_active' => true,
        //         ],
        //     );

        //     $createdProduct->sizes()->sync($storage ? [$storage->id] : []);
        // }

        $customerProfile = Customer::forUser($customer);
        $customerProfile->update(['phone' => $customerProfile->phone ?: '416-555-0199']);

        $booking = Repair::query()->updateOrCreate(
            ['repair_number' => 'ECL-REP-2026-0000001'],
            [
                'customer_id' => $customerProfile->id,
                'device_type' => 'Phone',
                'device_type_id' => DeviceType::query()->where('slug', 'phone')->value('id'),
                'device_brand' => 'Apple',
                'device_model' => 'iPhone 13',
                'issue_category' => 'Screen repair',
                'issue_category_id' => IssueCategory::query()->where('slug', 'screen-replacement')->value('id'),
                'issue_description' => 'Cracked display after a drop. Touch still works.',
                'preferred_appointment_date' => now()->addDays(2)->toDateString(),
                'preferred_appointment_time' => '10:30',
                'terms_accepted' => true,
                'status' => 'diagnosis_in_progress',
                'repair_status' => 'diagnosis_in_progress',
                'payment_status' => 'unpaid',
                'subtotal' => 200,
                'tax_amount' => 26,
                'shipping_amount' => 0,
                'repair_total' => 226,
                'total_amount' => 226,
                'amount_paid' => 0,
                'balance_due' => 226,
                'repair_items' => [
                    ['type' => 'workmanship', 'name' => 'Screen replacement labour', 'quantity' => 1, 'unit_price' => 80, 'total' => 80],
                    ['type' => 'part', 'name' => 'iPhone 13 screen', 'quantity' => 1, 'unit_price' => 120, 'total' => 120],
                ],
                'estimated_completion_date' => now()->addDays(4)->toDateString(),
                'customer_notes' => 'Device inspection started. We will confirm parts availability before repair.',
            ],
        );

        $booking->statusUpdates()->updateOrCreate(
            ['status' => 'booking_created'],
            ['note' => 'Repair request received.', 'is_customer_visible' => true],
        );

        $booking->statusUpdates()->updateOrCreate(
            ['status' => 'diagnosis_in_progress'],
            ['note' => 'Technician is checking display and frame condition.', 'is_customer_visible' => true],
        );
    }
}
