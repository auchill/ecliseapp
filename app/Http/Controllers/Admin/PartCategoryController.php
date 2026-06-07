<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartCategoryController extends Controller
{
    public function index()
    {
        return view('admin.taxonomies.index', [
            'title' => 'Parts Categories',
            'items' => PartCategory::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
            'routePrefix' => 'admin.part-categories',
        ]);
    }

    public function create()
    {
        return view('admin.taxonomies.form', [
            'title' => 'Add Parts Category',
            'item' => new PartCategory(['is_active' => true, 'sort_order' => 0]),
            'routePrefix' => 'admin.part-categories',
        ]);
    }

    public function store(Request $request)
    {
        PartCategory::query()->create($this->validatedData($request));

        return redirect()->route('admin.part-categories.index')->with('status', 'Parts category created.');
    }

    public function edit(PartCategory $partCategory)
    {
        return view('admin.taxonomies.form', [
            'title' => 'Edit Parts Category',
            'item' => $partCategory,
            'routePrefix' => 'admin.part-categories',
        ]);
    }

    public function update(Request $request, PartCategory $partCategory)
    {
        $partCategory->update($this->validatedData($request, $partCategory->id));

        return redirect()->route('admin.part-categories.edit', $partCategory)->with('status', 'Parts category updated.');
    }

    public function destroy(PartCategory $partCategory)
    {
        $partCategory->delete();

        return redirect()->route('admin.part-categories.index')->with('status', 'Parts category deleted.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('part_categories', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
