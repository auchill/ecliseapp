<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartBrandController extends Controller
{
    public function index()
    {
        return view('admin.taxonomies.index', [
            'title' => 'Parts Brands',
            'items' => PartBrand::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
            'routePrefix' => 'admin.part-brands',
        ]);
    }

    public function create()
    {
        return view('admin.taxonomies.form', [
            'title' => 'Add Parts Brand',
            'item' => new PartBrand(['is_active' => true, 'sort_order' => 0]),
            'routePrefix' => 'admin.part-brands',
        ]);
    }

    public function store(Request $request)
    {
        PartBrand::query()->create($this->validatedData($request));

        return redirect()->route('admin.part-brands.index')->with('status', 'Parts brand created.');
    }

    public function edit(PartBrand $partBrand)
    {
        return view('admin.taxonomies.form', [
            'title' => 'Edit Parts Brand',
            'item' => $partBrand,
            'routePrefix' => 'admin.part-brands',
        ]);
    }

    public function update(Request $request, PartBrand $partBrand)
    {
        $partBrand->update($this->validatedData($request, $partBrand->id));

        return redirect()->route('admin.part-brands.edit', $partBrand)->with('status', 'Parts brand updated.');
    }

    public function destroy(PartBrand $partBrand)
    {
        $partBrand->delete();

        return redirect()->route('admin.part-brands.index')->with('status', 'Parts brand deleted.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('part_brands', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
