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

class PartController extends Controller
{
    public function index(Request $request)
    {
        $parts = Part::query()
            ->with('partBrand', 'partCategory', 'partModel')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model_compatibility', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.parts.index', [
            'parts' => $parts,
        ]);
    }

    public function create()
    {
        return view('admin.parts.form', [
            'part' => new Part,
            'partBrands' => PartBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partCategories' => PartCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partModels' => PartModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePartRequest $request)
    {
        Part::query()->create($this->validatedData($request));

        return redirect()->route('admin.parts.index')->with('status', 'Part created.');
    }

    public function edit(Part $part)
    {
        return view('admin.parts.form', [
            'part' => $part,
            'partBrands' => PartBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partCategories' => PartCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'partModels' => PartModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(StorePartRequest $request, Part $part)
    {
        $part->update($this->validatedData($request));

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

        return redirect()->route('admin.parts.index')->with('status', $result['message']);
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
        $data['selling_price'] = $data['selling_price'] ?? $data['price'];
        $data['final_price'] = $data['final_price'] ?? $data['selling_price'];
        $data['availability_status'] = $data['availability_status'] ?? $data['stock_status'];
        $data['external_api_source'] = $data['external_api_source'] ?? $data['supplier'];
        $data['is_api_item'] = $request->boolean('is_api_item');
        $data['is_active'] = $request->boolean('is_active', true);

        foreach (['compatibility', 'specifications'] as $jsonField) {
            if (! empty($data[$jsonField]) && is_string($data[$jsonField])) {
                $decoded = json_decode($data[$jsonField], true);
                $data[$jsonField] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }
        }

        unset($data['part_image']);

        return $data;
    }
}
