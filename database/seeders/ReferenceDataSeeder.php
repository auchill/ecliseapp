<?php

namespace Database\Seeders;

use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\PartModel;
use App\Models\ProductModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(ProductModel::class, [
            'iPhone 13',
            'iPhone 14',
            'Galaxy S24',
            'ThinkPad T14',
            '30W USB-C',
            'MacBook Pro',
        ]);

        // $this->seed(PartModel::class, [
        //     'iPhone 13',
        //     'Galaxy S22',
        //     'A2337',
        //     'XPS 13 9310',
        //     'MacBook Pro',
        // ]);

        $this->seed(DeviceType::class, [
            'Phone',
            'Laptop',
            'Tablet',
            'Desktop',
            'Game Console',
            'Smart Watch',
        ]);

        $this->seed(DeviceBrand::class, [
            'Apple',
            'Samsung',
            'HP',
            'Dell',
            'Lenovo',
            'Google',
            'Microsoft',
        ]);

        $this->seed(DeviceModel::class, [
            'iPhone 13',
            'iPhone 14 Pro Max',
            'Samsung Galaxy S23',
            'MacBook Pro',
            'HP Pavilion',
            'Dell XPS',
        ]);

        $this->seed(IssueCategory::class, [
            'Screen Replacement',
            'Battery Issue',
            'Charging Port',
            'Water Damage',
            'Software Issue',
            'Keyboard Issue',
            'Data Recovery',
            'General Diagnosis',
        ]);
    }

    private function seed(string $model, array $names): void
    {
        foreach ($names as $index => $name) {
            $model::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'status' => 'active',
                    'sort_order' => $index,
                ],
            );
        }
    }
}
