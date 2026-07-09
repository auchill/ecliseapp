<?php

namespace App\Support;

use App\Models\PartCategory;
use Illuminate\Support\Collection;

class CustomerFacingPartCategories
{
    public const REPLACEMENT_PARTS_CATEGORY_ID = 165;

    public const REPLACEMENT_PART_CHILD_IDS = [756, 757, 779, 570, 167];

    public const DIRECT_TOP_LEVEL_CATEGORY_IDS = [630, 227, 587];

    public const EXCLUDED_CATEGORY_IDS = [8363, 3958, 1505];

    private ?Collection $allowedIds = null;

    public function allowedIds(): Collection
    {
        if ($this->allowedIds instanceof Collection) {
            return $this->allowedIds;
        }

        $allowed = collect(array_merge(
            [self::REPLACEMENT_PARTS_CATEGORY_ID],
            self::REPLACEMENT_PART_CHILD_IDS,
            self::DIRECT_TOP_LEVEL_CATEGORY_IDS,
        ))->map(fn (int $id): int => $id)->unique()->values();

        $frontier = $allowed;

        while ($frontier->isNotEmpty()) {
            $children = PartCategory::query()
                ->whereIn('parent_id', $frontier->all())
                ->whereNotIn('id', self::EXCLUDED_CATEGORY_IDS)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->diff($allowed)
                ->values();

            $allowed = $allowed->merge($children)->unique()->values();
            $frontier = $children;
        }

        return $this->allowedIds = $allowed
            ->diff(self::EXCLUDED_CATEGORY_IDS)
            ->values();
    }

    public function contains(int $categoryId): bool
    {
        return $this->allowedIds()->contains($categoryId);
    }
}
