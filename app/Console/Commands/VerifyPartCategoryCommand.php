<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Models\PartCategory;
use App\Services\Parts\PartsMenuService;
use App\Support\MobileSentrixCategoryIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyPartCategoryCommand extends Command
{
    protected $signature = 'parts:verify-category
        {categoryId : MobileSentrix category ID stored in part_categories.id}
        {--samples=10 : Number of sample parts to show}';

    protected $description = 'Verify a MobileSentrix parts category from category tree through pivot and customer listing query.';

    public function handle(PartsMenuService $partsMenu): int
    {
        $categoryId = (int) $this->argument('categoryId');
        $sampleLimit = max(1, min((int) $this->option('samples'), 50));
        $category = PartCategory::query()->find($categoryId);

        $this->line("Category ID: {$categoryId}");
        $this->line('Category found in part_categories: '.($category ? 'yes' : 'no'));

        if ($category) {
            $this->line('Category name: '.$category->name);
            $this->line('Parent ID: '.($category->parent_id ?: 'none'));
            $this->line('Status: '.$category->status);
            $this->line('Active part category: '.((bool) $category->is_active && (bool) $category->is_part ? 'yes' : 'no'));
            $this->line('Customer-facing visible: '.($partsMenu->isVisible($category) ? 'yes' : 'no'));
            $this->line('Path: '.$this->categoryPath($category));
        }

        $partsCategoryIdsCount = $this->countPartsContainingCategoryId($categoryId);
        $pivotRowsCount = DB::table('part_category_part')->where('category_id', $categoryId)->count();
        $joinablePartsCount = Part::query()
            ->join('part_category_part', 'part_category_part.part_id', '=', 'parts.id')
            ->where('part_category_part.category_id', $categoryId)
            ->count('parts.id');
        $listingQueryCount = $category
            ? $category->parts()
                ->where('parts.is_active', true)
                ->where('parts.status', 'active')
                ->count()
            : 0;

        $this->line("Parts where parts.category_ids contains {$categoryId}: {$partsCategoryIdsCount}");
        $this->line("Pivot rows in part_category_part for category_id {$categoryId}: {$pivotRowsCount}");
        $this->line("Joinable parts through pivot table: {$joinablePartsCount}");
        $this->line("Frontend listing query count: {$listingQueryCount}");

        $samples = Part::query()
            ->select(['parts.id', 'parts.sku', 'parts.new_sku', 'parts.name'])
            ->join('part_category_part', 'part_category_part.part_id', '=', 'parts.id')
            ->where('part_category_part.category_id', $categoryId)
            ->orderBy('parts.name')
            ->limit($sampleLimit)
            ->get();

        if ($samples->isEmpty()) {
            $this->warn('No sample parts found through the pivot table.');
        } else {
            $this->table(['Part ID', 'SKU', 'Name'], $samples->map(fn (Part $part): array => [
                $part->id,
                $part->sku ?: $part->new_sku ?: 'N/A',
                $part->name,
            ])->all());
        }

        return self::SUCCESS;
    }

    private function countPartsContainingCategoryId(int $categoryId): int
    {
        $count = 0;
        $categoryId = (string) $categoryId;

        Part::query()
            ->select(['id', 'category_ids'])
            ->orderBy('id')
            ->chunkById(500, function ($parts) use (&$count, $categoryId): void {
                foreach ($parts as $part) {
                    if (in_array($categoryId, MobileSentrixCategoryIds::values($part->category_ids), true)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function categoryPath(PartCategory $category): string
    {
        $path = collect();
        $current = $category;

        while ($current instanceof PartCategory) {
            $path->prepend($current->name.' (#'.$current->id.')');

            if (! $current->parent_id) {
                break;
            }

            $current = $current->parentCategory()->first();
        }

        return $path->implode(' > ');
    }
}
