<?php

namespace App\Services\Parts;

use App\Models\PartCategory;
use App\Support\CustomerFacingPartCategories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PartsMenuService
{
    private const DISPLAY_NAME_OVERRIDES = [
        'Others' => 'Other Parts',
        'Game Console Parts' => 'Game Console',
    ];

    private const MAIN_MENU_ORDER = [
        'Apple' => 10,
        'Samsung' => 20,
        'Motorola' => 30,
        'Google' => 40,
        'Other Parts' => 50,
        'Game Console' => 60,
        'Refurbishing' => 70,
        'Board Components' => 80,
    ];

    public function __construct(private readonly CustomerFacingPartCategories $customerFacingCategories) {}

    public function mainMenu(): Collection
    {
        return $this->replacementPartChildren()
            ->merge($this->directTopLevelCategories())
            ->map(fn (PartCategory $category): array => $this->categoryPayload($category))
            ->sortBy(fn (array $category): int => self::MAIN_MENU_ORDER[$category['name']] ?? 999)
            ->values();
    }

    public function childrenFor(PartCategory $category): Collection
    {
        return $this->visibleCategoryQuery()
            ->where('parent_id', $category->id)
            ->withCount(['childCategories as active_children_count' => fn (Builder $query) => $this->applyVisibleCategoryConstraints($query)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PartCategory $child): array => $this->categoryPayload($child))
            ->values();
    }

    public function searchCategories(string $keyword, int $limit = 8): Collection
    {
        $keyword = trim($keyword);

        if (mb_strlen($keyword) < 2) {
            return collect();
        }

        return $this->visibleCategoryQuery()
            ->where('name', 'like', "%{$keyword}%")
            ->withCount(['childCategories as active_children_count' => fn (Builder $query) => $this->applyVisibleCategoryConstraints($query)])
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (PartCategory $category): array => $this->categoryPayload($category))
            ->values();
    }

    public function categoryPath(PartCategory $category): Collection
    {
        $path = collect();
        $current = $category;

        while ($current instanceof PartCategory) {
            $path->prepend($current);

            if (blank($current->parent_id)) {
                break;
            }

            $current = $current->parentCategory()->first();
        }

        return $path
            ->filter(fn (PartCategory $category): bool => $this->isBreadcrumbCategory($category))
            ->map(fn (PartCategory $category): array => $this->categoryPayload($category))
            ->values();
    }

    public function isVisible(PartCategory $category): bool
    {
        return (bool) $category->is_active
            && $category->status === 'active'
            && (bool) $category->is_part
            && $this->customerFacingCategories->contains((int) $category->id);
    }

    public function categoryPayload(PartCategory $category): array
    {
        $activeChildrenCount = $this->activeChildrenCount($category);
        $displayName = $this->displayName((string) $category->name);

        return [
            'id' => (int) $category->id,
            'name' => $displayName,
            'display_name' => $displayName,
            'original_name' => $category->name,
            'image_url' => $category->image_url,
            'has_children' => (bool) $category->has_children || $activeChildrenCount > 0,
            'children_count' => $activeChildrenCount,
            'children_url' => route('parts.category.children', $category),
            'parts_url' => route('parts.category.parts', $category),
            'path_url' => route('parts.category.path', $category),
        ];
    }

    private function replacementPartChildren(): Collection
    {
        return $this->visibleCategoryQuery()
            ->where('parent_id', CustomerFacingPartCategories::REPLACEMENT_PARTS_CATEGORY_ID)
            ->whereIn('id', CustomerFacingPartCategories::REPLACEMENT_PART_CHILD_IDS)
            ->withCount(['childCategories as active_children_count' => fn (Builder $query) => $this->applyVisibleCategoryConstraints($query)])
            ->get()
            ->sortBy(function (PartCategory $category): int {
                $position = array_search((int) $category->id, CustomerFacingPartCategories::REPLACEMENT_PART_CHILD_IDS, true);

                return $position === false ? 999 : $position;
            })
            ->values();
    }

    private function directTopLevelCategories(): Collection
    {
        return $this->visibleCategoryQuery()
            ->whereNull('parent_id')
            ->whereIn('id', CustomerFacingPartCategories::DIRECT_TOP_LEVEL_CATEGORY_IDS)
            ->withCount(['childCategories as active_children_count' => fn (Builder $query) => $this->applyVisibleCategoryConstraints($query)])
            ->get()
            ->sortBy(function (PartCategory $category): int {
                $position = array_search((int) $category->id, CustomerFacingPartCategories::DIRECT_TOP_LEVEL_CATEGORY_IDS, true);

                return $position === false ? 999 : $position;
            })
            ->values();
    }

    private function visibleCategoryQuery(): Builder
    {
        return $this->applyVisibleCategoryConstraints(PartCategory::query());
    }

    private function applyVisibleCategoryConstraints(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('is_part', true)
            ->whereIn('id', $this->customerFacingCategories->allowedIds()->all());
    }

    private function isBreadcrumbCategory(PartCategory $category): bool
    {
        return (int) $category->id !== CustomerFacingPartCategories::REPLACEMENT_PARTS_CATEGORY_ID
            && $this->isVisible($category);
    }

    private function activeChildrenCount(PartCategory $category): int
    {
        if (isset($category->active_children_count)) {
            return (int) $category->active_children_count;
        }

        return $this->visibleCategoryQuery()
            ->where('parent_id', $category->id)
            ->count();
    }

    private function displayName(string $name): string
    {
        return self::DISPLAY_NAME_OVERRIDES[$name] ?? $name;
    }
}
