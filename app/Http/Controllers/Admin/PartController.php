<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartRequest;
use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\PartModel;
use App\Services\MobileSentrixService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $parts = Part::query()
            ->with('partBrand', 'partCategory', 'partModel', 'partCategories')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('new_sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('manufacturer_text', 'like', "%{$search}%")
                        ->orWhere('model_compatibility', 'like', "%{$search}%")
                        ->orWhere('model_text', 'like', "%{$search}%")
                        ->orWhere('part_category', 'like', "%{$search}%")
                        ->orWhere('front_position_text', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('brand'), fn ($query) => $query->where('part_brand_id', $request->integer('brand')))
            ->when($request->filled('category'), function ($query) use ($request): void {
                $categoryId = $request->integer('category');
                $query->where(function ($query) use ($categoryId): void {
                    $query->where('part_category_id', $categoryId)
                        ->orWhereHas('partCategories', fn ($query) => $query->whereKey($categoryId));
                });
            })
            ->when($request->filled('model'), fn ($query) => $query->where('part_model_id', $request->integer('model')))
            ->when($request->filled('stock'), function ($query) use ($request): void {
                $request->string('stock')->toString() === 'in'
                    ? $query->where(function ($query): void {
                        $query->where('is_in_stock', true)->orWhere('quantity', '>', 0)->orWhere('in_stock_qty', '>', 0);
                    })
                    : $query->where(function ($query): void {
                        $query->where('is_in_stock', false)->where('quantity', '<=', 0)->where('in_stock_qty', '<=', 0);
                    });
            })
            ->when($request->filled('api_status'), fn ($query) => $query->where('api_status', $request->string('api_status')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.parts.index', [
            'parts' => $parts,
            'partBrands' => PartBrand::query()->active()->orderBy('name')->get(),
            'partCategories' => PartCategory::query()->active()->orderBy('name')->get(),
            'partModels' => PartModel::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.parts.form', [
            'part' => new Part,
            'partBrands' => PartBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partCategories' => PartCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partModels' => PartModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'markupTypes' => Part::MARKUP_TYPES,
        ]);
    }

    public function store(StorePartRequest $request)
    {
        $part = Part::query()->create($this->validatedData($request));
        $this->syncPrimaryCategory($part);

        return redirect()->route('admin.parts.index')->with('status', 'Part created.');
    }

    public function edit(Part $part)
    {
        return view('admin.parts.form', [
            'part' => $part,
            'partBrands' => PartBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partCategories' => PartCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partModels' => PartModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'markupTypes' => Part::MARKUP_TYPES,
        ]);
    }

    public function update(StorePartRequest $request, Part $part)
    {
        $part->update($this->validatedData($request));
        $this->syncPrimaryCategory($part);

        return redirect()->route('admin.parts.edit', $part)->with('status', 'Part updated.');
    }

    public function destroy(Part $part)
    {
        $part->delete();

        return redirect()->route('admin.parts.index')->with('status', 'Part deleted.');
    }

    public function sync(MobileSentrixService $mobileSentrix)
    {
        $result = $mobileSentrix->syncParts();

        return redirect()->route('admin.parts.mobilesentrix.index')->with('status', $result['message']);
    }

    private function validatedData(StorePartRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('part_image')) {
            $data['image_path'] = $request->file('part_image')->store('parts', 'public');
            $data['local_image_path'] = $data['image_path'];
        }

        $partBrand = PartBrand::query()->find($data['part_brand_id']);
        $partCategory = PartCategory::query()->find($data['part_category_id']);
        $partModel = ! empty($data['part_model_id']) ? PartModel::query()->find($data['part_model_id']) : null;

        $data['brand'] = $partBrand?->name ?? $data['brand'] ?? null;
        $data['part_category'] = $partCategory?->name ?? $data['part_category'] ?? null;
        $data['model_compatibility'] = $partModel?->name ?? $data['model_compatibility'] ?? null;
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name'].' '.($data['sku'] ?? $data['internal_sku'] ?? uniqid()));
        $data['selling_price'] = $data['selling_price'] ?? $data['price'];
        $data['markup_type'] = $data['markup_type'] ?? 'none';
        $data['markup_value'] = $data['markup_value'] ?? 0;
        $data['cost_price'] = $data['cost_price'] ?? $data['api_price'] ?? $data['price'];
        $data['selling_price'] = $this->sellingPrice(
            (float) $data['cost_price'],
            $data['markup_type'],
            (float) $data['markup_value'],
            $data['selling_price'] ?? null,
        );
        $data['final_price'] = $data['final_price'] ?? $data['selling_price'];
        $data['availability_status'] = $data['availability_status'] ?? $data['stock_status'];
        $data['external_api_source'] = $data['external_api_source'] ?? $data['supplier'];
        $data['is_api_item'] = $request->boolean('is_api_item');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_in_stock'] = $request->boolean('is_in_stock', ($data['quantity'] ?? 0) > 0);
        $data['in_stock_qty'] = $data['in_stock_qty'] ?? $data['quantity'];
        $data['status'] = $data['is_active'] ? 'active' : 'inactive';

        foreach (['compatibility', 'specifications'] as $jsonField) {
            if (! empty($data[$jsonField]) && is_string($data[$jsonField])) {
                $decoded = json_decode($data[$jsonField], true);
                $data[$jsonField] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }
        }

        unset($data['part_image']);

        return $data;
    }

    private function sellingPrice(float $costPrice, string $markupType, float $markupValue, mixed $manualPrice): float
    {
        return match ($markupType) {
            'percentage' => round($costPrice + ($costPrice * ($markupValue / 100)), 2),
            'fixed' => round($costPrice + $markupValue, 2),
            default => round((float) ($manualPrice ?? $costPrice), 2),
        };
    }

    private function syncPrimaryCategory(Part $part): void
    {
        if ($part->part_category_id) {
            $part->partCategories()->syncWithoutDetaching([$part->part_category_id]);
        }
    }
}
