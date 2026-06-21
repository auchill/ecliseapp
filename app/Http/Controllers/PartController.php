<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use Illuminate\Http\Request;

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
                        ->orWhere('front_position_text', 'like', "%{$search}%")
                        ->orWhere('part_category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('brand'), function ($query) use ($request): void {
                $brand = $request->string('brand');
                $query->where(function ($query) use ($brand): void {
                    $query->whereHas('partBrand', fn ($query) => $query->where('slug', $brand))
                        ->orWhere('brand', $brand);
                });
            })
            ->when($request->filled('model'), fn ($query) => $query->where('model_compatibility', 'like', '%'.$request->string('model').'%'))
            ->when($request->filled('device_type'), fn ($query) => $query->where('device_type', $request->string('device_type')))
            ->when($request->filled('part_category'), function ($query) use ($request): void {
                $category = $request->string('part_category');
                $query->where(function ($query) use ($category): void {
                    $query->whereHas('partCategory', fn ($query) => $query->where('slug', $category))
                        ->orWhereHas('partCategories', fn ($query) => $query->where('slug', $category))
                        ->orWhere('part_category', $category);
                });
            })
            ->where('is_active', true)
            ->where('status', 'active')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('parts.index', [
            'parts' => $parts,
            'brands' => PartBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'deviceTypes' => Part::query()->where('is_active', true)->where('status', 'active')->distinct()->orderBy('device_type')->pluck('device_type'),
            'categories' => PartCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
