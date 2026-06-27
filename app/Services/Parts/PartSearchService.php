<?php

namespace App\Services\Parts;

use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\PartModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PartSearchService
{
    public function publicResults(Request $request, int $perPage = 12): LengthAwarePaginator
    {
        return $this->publicQuery($request)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function adminResults(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return $this->adminQuery($request)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function publicQuery(Request $request): Builder
    {
        return Part::query()
            ->with('partBrand', 'partCategory', 'partModel', 'partCategories')
            ->where('is_active', true)
            ->where('status', 'active')
            ->when($request->filled('q'), fn (Builder $query) => $this->applyKeyword($query, $request->string('q')->toString()))
            ->when($request->filled('brand'), function (Builder $query) use ($request): void {
                $brand = $request->string('brand')->toString();
                $query->where(function (Builder $query) use ($brand): void {
                    $query->whereHas('partBrand', fn (Builder $query) => $query->where('slug', $brand))
                        ->orWhere('brand', $brand)
                        ->orWhere('manufacturer_text', $brand);
                });
            })
            ->when($request->filled('model'), function (Builder $query) use ($request): void {
                $model = $request->string('model')->toString();
                $query->where(function (Builder $query) use ($model): void {
                    $query->whereHas('partModel', fn (Builder $query) => $query->where('name', 'like', "%{$model}%"))
                        ->orWhere('model_compatibility', 'like', "%{$model}%")
                        ->orWhere('model_text', 'like', "%{$model}%");
                });
            })
            ->when($request->filled('device_type'), fn (Builder $query) => $query->where('device_type', $request->string('device_type')->toString()))
            ->when($request->filled('part_category'), function (Builder $query) use ($request): void {
                $category = $request->string('part_category')->toString();
                $query->where(function (Builder $query) use ($category): void {
                    $query->whereHas('partCategory', fn (Builder $query) => $query->where('slug', $category))
                        ->orWhereHas('partCategories', fn (Builder $query) => $query->where('slug', $category))
                        ->orWhere('part_category', $category);
                });
            })
            ->when($request->filled('stock'), fn (Builder $query) => $this->applyStock($query, $request->string('stock')->toString()))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->whereRaw('CAST(COALESCE(selling_price, final_price, price) AS DECIMAL(10,2)) >= ?', [(float) $request->input('min_price')]))
            ->when($request->filled('max_price'), fn (Builder $query) => $query->whereRaw('CAST(COALESCE(selling_price, final_price, price) AS DECIMAL(10,2)) <= ?', [(float) $request->input('max_price')]));
    }

    public function adminQuery(Request $request): Builder
    {
        return Part::query()
            ->with('partBrand', 'partCategory', 'partModel', 'partCategories')
            ->when($request->filled('q'), fn (Builder $query) => $this->applyKeyword($query, $request->string('q')->toString(), true))
            ->when($request->filled('brand'), fn (Builder $query) => $query->where('part_brand_id', $request->integer('brand')))
            ->when($request->filled('category'), function (Builder $query) use ($request): void {
                $categoryId = $request->integer('category');
                $query->where(function (Builder $query) use ($categoryId): void {
                    $query->where('part_category_id', $categoryId)
                        ->orWhereHas('partCategories', fn (Builder $query) => $query->whereKey($categoryId));
                });
            })
            ->when($request->filled('model'), fn (Builder $query) => $query->where('part_model_id', $request->integer('model')))
            ->when($request->filled('stock'), fn (Builder $query) => $this->applyStock($query, $request->string('stock')->toString()))
            ->when($request->filled('api_status'), fn (Builder $query) => $query->where('api_status', $request->string('api_status')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()));
    }

    public function publicSuggestions(Request $request, int $limit = 5): array
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return [];
        }

        return array_values(array_filter(array_merge(
            $this->partSuggestions($term, $limit, false),
            $this->brandSuggestions($term, $limit, false),
            $this->modelSuggestions($term, $limit, false),
            $this->categorySuggestions($term, $limit, false),
        )));
    }

    public function adminSuggestions(Request $request, int $limit = 5): array
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return [];
        }

        return array_values(array_filter(array_merge(
            $this->partSuggestions($term, $limit, true),
            $this->brandSuggestions($term, $limit, true),
            $this->modelSuggestions($term, $limit, true),
            $this->categorySuggestions($term, $limit, true),
        )));
    }

    private function applyKeyword(Builder $query, string $search, bool $admin = false): void
    {
        $query->where(function (Builder $query) use ($search, $admin): void {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('new_sku', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('manufacturer_text', 'like', "%{$search}%")
                ->orWhere('model_compatibility', 'like', "%{$search}%")
                ->orWhere('model_text', 'like', "%{$search}%")
                ->orWhere('part_category', 'like', "%{$search}%")
                ->orWhere('front_position_text', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('partBrand', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('partModel', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('partCategory', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('partCategories', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));

            if ($admin) {
                $query->orWhere('mobilesentrix_product_id', 'like', "%{$search}%");
            }
        });
    }

    private function applyStock(Builder $query, string $stock): void
    {
        $stock === 'in'
            ? $query->where(function (Builder $query): void {
                $query->where('is_in_stock', true)->orWhere('quantity', '>', 0)->orWhere('in_stock_qty', '>', 0);
            })
            : $query->where(function (Builder $query): void {
                $query->where('is_in_stock', false)->where('quantity', '<=', 0)->where('in_stock_qty', '<=', 0);
            });
    }

    private function partSuggestions(string $term, int $limit, bool $admin): array
    {
        return Part::query()
            ->when(! $admin, fn (Builder $query) => $query->where('is_active', true)->where('status', 'active'))
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('new_sku', 'like', "%{$term}%");
            })
            ->limit($limit)
            ->get(['name', 'sku', 'new_sku'])
            ->flatMap(function (Part $part): array {
                return array_filter([
                    [
                        'type' => 'part',
                        'label' => $part->name,
                        'value' => $part->name,
                        'field' => 'q',
                    ],
                    $part->sku ? [
                        'type' => 'sku',
                        'label' => $part->sku,
                        'value' => $part->sku,
                        'field' => 'q',
                    ] : null,
                    $part->new_sku ? [
                        'type' => 'sku',
                        'label' => $part->new_sku,
                        'value' => $part->new_sku,
                        'field' => 'q',
                    ] : null,
                ]);
            })
            ->values()
            ->all();
    }

    private function brandSuggestions(string $term, int $limit, bool $admin): array
    {
        return PartBrand::query()
            ->when(! $admin, fn (Builder $query) => $query->active())
            ->where('name', 'like', "%{$term}%")
            ->limit($limit)
            ->get(['id', 'name', 'slug'])
            ->map(fn (PartBrand $brand): array => [
                'type' => 'brand',
                'label' => $brand->name,
                'value' => $admin ? (string) $brand->id : $brand->slug,
                'field' => 'brand',
            ])
            ->all();
    }

    private function modelSuggestions(string $term, int $limit, bool $admin): array
    {
        return PartModel::query()
            ->when(! $admin, fn (Builder $query) => $query->active())
            ->where('name', 'like', "%{$term}%")
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn (PartModel $model): array => [
                'type' => 'model',
                'label' => $model->name,
                'value' => $admin ? (string) $model->id : $model->name,
                'field' => 'model',
            ])
            ->all();
    }

    private function categorySuggestions(string $term, int $limit, bool $admin): array
    {
        return PartCategory::query()
            ->when(! $admin, fn (Builder $query) => $query->active())
            ->where('name', 'like', "%{$term}%")
            ->limit($limit)
            ->get(['id', 'name', 'slug'])
            ->map(fn (PartCategory $category): array => [
                'type' => 'category',
                'label' => $category->name,
                'value' => $admin ? (string) $category->id : $category->slug,
                'field' => $admin ? 'category' : 'part_category',
            ])
            ->all();
    }
}
