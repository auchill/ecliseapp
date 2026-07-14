<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ShopProductFilters
{
    public const SORT_OPTIONS = [
        'newest' => 'Newest',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'name_asc' => 'Name: A to Z',
        'name_desc' => 'Name: Z to A',
        'featured' => 'Featured',
        'on_sale' => 'On Sale',
    ];

    private const MULTI_FILTERS = [
        'brands' => [
            'model' => ProductBrand::class,
            'column' => 'product_brand_id',
            'label' => 'Brand',
            'legacy' => 'brand',
        ],
        'conditions' => [
            'model' => ProductCondition::class,
            'column' => 'product_condition_id',
            'label' => 'Condition',
            'legacy' => 'condition',
        ],
        'grades' => [
            'model' => ProductGrade::class,
            'column' => 'product_grade_id',
            'label' => 'Grade',
            'legacy' => 'grade',
        ],
        'colors' => [
            'model' => ProductColor::class,
            'column' => 'product_color_id',
            'label' => 'Color',
            'legacy' => 'color',
        ],
    ];

    public function query(Request $request): Builder
    {
        $query = Product::query()
            ->with('category', 'productBrand', 'productModel', 'sizes', 'productGrade', 'productCondition', 'productColor', 'network', 'primaryImage')
            ->active();

        $this->applyFilters($query, $request);

        return $this->sort($query, (string) $request->query('sort', 'newest'));
    }

    public function filterOptions(Request $request): array
    {
        return [
            'categories' => $this->categoryOptions($request),
            'brands' => $this->multiOptions($request, 'brands'),
            'conditions' => $this->multiOptions($request, 'conditions'),
            'grades' => $this->multiOptions($request, 'grades'),
            'colors' => $this->multiOptions($request, 'colors'),
        ];
    }

    public function selectedChips(Request $request): array
    {
        $chips = [];

        if (filled($request->query('q'))) {
            $chips[] = $this->chip('q', null, 'Search: '.$request->query('q'), $request);
        }

        if (filled($request->query('category'))) {
            $category = ProductCategory::query()
                ->where('slug', $request->query('category'))
                ->first();
            $chips[] = $this->chip('category', null, $category?->name ?: (string) $request->query('category'), $request);
        }

        foreach (array_keys(self::MULTI_FILTERS) as $key) {
            $selected = $this->selectedValues($request, $key);
            $labels = $this->selectedOptionLabels($key, $selected);

            foreach ($selected as $value) {
                $chips[] = $this->chip($key, $value, $labels[$value] ?? $value, $request);
            }
        }

        foreach (['min_price' => 'Min', 'max_price' => 'Max'] as $key => $label) {
            if (filled($request->query($key))) {
                $chips[] = $this->chip($key, null, $label.': $'.number_format((float) $request->query($key), 2), $request);
            }
        }

        return $chips;
    }

    public function activeFilterCount(Request $request): int
    {
        $count = collect(['q', 'category', 'min_price', 'max_price'])
            ->filter(fn (string $key): bool => filled($request->query($key)))
            ->count();

        foreach (array_keys(self::MULTI_FILTERS) as $key) {
            $count += count($this->selectedValues($request, $key));
        }

        return $count;
    }

    public function priceBounds(Request $request): array
    {
        $query = Product::query()->active();
        $this->applyFilters($query, $request, ['min_price', 'max_price']);

        return [
            'min' => (float) ((clone $query)->selectRaw('MIN(COALESCE(sale_price, regular_price)) as price_min')->value('price_min') ?? 0),
            'max' => (float) ((clone $query)->selectRaw('MAX(COALESCE(sale_price, regular_price)) as price_max')->value('price_max') ?? 0),
        ];
    }

    public function applyFilters(Builder $query, Request $request, array $except = []): void
    {
        if (! in_array('q', $except, true) && filled($request->query('q'))) {
            $search = trim((string) $request->query('q'));
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('productBrand', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('productCondition', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('productGrade', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('productColor', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        if (! in_array('category', $except, true) && filled($request->query('category'))) {
            $query->whereHas('category', fn (Builder $query) => $query->where('slug', $request->query('category')));
        }

        foreach (self::MULTI_FILTERS as $key => $config) {
            if (in_array($key, $except, true)) {
                continue;
            }

            $ids = $this->idsForSelectedValues($config['model'], $this->selectedValues($request, $key));

            if ($ids !== []) {
                $query->whereIn($config['column'], $ids);
            }
        }

        if (! in_array('min_price', $except, true) && is_numeric($request->query('min_price'))) {
            $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [(float) $request->query('min_price')]);
        }

        if (! in_array('max_price', $except, true) && is_numeric($request->query('max_price'))) {
            $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [(float) $request->query('max_price')]);
        }
    }

    public function selectedValues(Request $request, string $key): array
    {
        $value = $request->query($key);

        if (blank($value) && isset(self::MULTI_FILTERS[$key]['legacy'])) {
            $value = $request->query(self::MULTI_FILTERS[$key]['legacy']);
        }

        if (is_string($value)) {
            $value = str_contains($value, ',') ? explode(',', $value) : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatten()
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function categoryOptions(Request $request): array
    {
        $selected = (string) $request->query('category', '');
        $query = Product::query()->active();
        $this->applyFilters($query, $request, ['category']);

        $counts = (clone $query)
            ->whereNotNull('product_category_id')
            ->selectRaw('product_category_id, COUNT(*) as aggregate')
            ->groupBy('product_category_id')
            ->pluck('aggregate', 'product_category_id');

        return ProductCategory::query()
            ->active()
            ->where(function (Builder $query) use ($counts, $selected): void {
                $query->whereIn('id', $counts->keys()->all());

                if ($selected !== '') {
                    $query->orWhere('slug', $selected);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'value' => $category->slug,
                'label' => $category->name,
                'count' => (int) ($counts[$category->id] ?? 0),
                'selected' => $selected === $category->slug,
            ])
            ->filter(fn (array $option): bool => $option['count'] > 0 || $option['selected'])
            ->values()
            ->all();
    }

    private function multiOptions(Request $request, string $key): array
    {
        $config = self::MULTI_FILTERS[$key];
        $selected = $this->selectedValues($request, $key);
        $selectedIds = $this->idsForSelectedValues($config['model'], $selected);
        $query = Product::query()->active();
        $this->applyFilters($query, $request, [$key]);

        $counts = (clone $query)
            ->whereNotNull($config['column'])
            ->selectRaw($config['column'].' as option_id, COUNT(*) as aggregate')
            ->groupBy($config['column'])
            ->pluck('aggregate', 'option_id');

        $ids = collect($counts->keys())
            ->merge($selectedIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return $this->activeLookupQuery($config['model'])
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Model $item) use ($counts, $selected): array {
                $value = (string) $item->slug;

                return [
                    'value' => $value,
                    'label' => $item->name,
                    'count' => (int) ($counts[$item->id] ?? 0),
                    'selected' => in_array($value, $selected, true) || in_array((string) $item->id, $selected, true),
                ];
            })
            ->filter(fn (array $option): bool => $option['count'] > 0 || $option['selected'])
            ->values()
            ->all();
    }

    private function selectedOptionLabels(string $key, array $selected): array
    {
        if ($selected === []) {
            return [];
        }

        return $this->activeLookupQuery(self::MULTI_FILTERS[$key]['model'])
            ->where(function (Builder $query) use ($selected): void {
                $query->whereIn('slug', $selected)
                    ->orWhereIn('id', collect($selected)->filter(fn (string $value): bool => ctype_digit($value))->all());
            })
            ->get()
            ->mapWithKeys(fn (Model $item): array => [(string) $item->slug => $item->name, (string) $item->id => $item->name])
            ->all();
    }

    private function idsForSelectedValues(string $modelClass, array $values): array
    {
        if ($values === []) {
            return [];
        }

        return $this->activeLookupQuery($modelClass)
            ->where(function (Builder $query) use ($values): void {
                $query->whereIn('slug', $values)
                    ->orWhereIn('id', collect($values)->filter(fn (string $value): bool => ctype_digit($value))->all());
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function activeLookupQuery(string $modelClass): Builder
    {
        return $modelClass::query()->where('status', 'active');
    }

    private function sort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, regular_price) IS NULL')->orderByRaw('COALESCE(sale_price, regular_price) asc')->orderBy('id'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, regular_price) IS NULL')->orderByRaw('COALESCE(sale_price, regular_price) desc')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderBy('id'),
            'featured' => $query->orderByDesc('is_featured')->latest(),
            'on_sale' => $query->orderByRaw('(sale_price IS NOT NULL AND sale_price < regular_price) desc')->latest(),
            default => $query->latest(),
        };
    }

    private function chip(string $key, ?string $value, string $label, Request $request): array
    {
        $query = $request->query();
        unset($query['page']);

        if ($value !== null) {
            $remaining = collect($this->selectedValues($request, $key))
                ->reject(fn (string $item): bool => $item === $value)
                ->values()
                ->all();

            if ($remaining === []) {
                unset($query[$key]);
                unset($query[self::MULTI_FILTERS[$key]['legacy']]);
            } else {
                $query[$key] = $remaining;
                unset($query[self::MULTI_FILTERS[$key]['legacy']]);
            }
        } else {
            unset($query[$key]);
        }

        return [
            'key' => $key,
            'value' => $value,
            'label' => $label,
            'url' => route('shop.index', $query),
            'aria' => 'Remove '.$label.' filter',
        ];
    }
}
