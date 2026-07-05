<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\ProductCondition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(ProductCondition::class, [
            'New',
            'Used',
            'Refurbished',
        ]);

        $this->seed(DeviceType::class, [
            'Phone',
            'Laptop',
            'Tablet',
            'Desktop',
            'Game Console',
            'Smart Watch',
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
