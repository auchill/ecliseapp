<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return view('admin.taxonomies.index', [
            'title' => 'Product Categories',
            'items' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
            'routePrefix' => 'admin.product-categories',
        ]);
    }

    public function create()
    {
        return view('admin.taxonomies.form', [
            'title' => 'Add Product Category',
            'item' => new ProductCategory(['is_active' => true, 'sort_order' => 0]),
            'routePrefix' => 'admin.product-categories',
            'usesSkuCode' => true,
        ]);
    }

    public function store(Request $request)
    {
        ProductCategory::query()->create($this->validatedData($request));

        return redirect()->route('admin.product-categories.index')->with('status', 'Product category created.');
    }

    public function edit(ProductCategory $productCategory)
    {
        return view('admin.taxonomies.form', [
            'title' => 'Edit Product Category',
            'item' => $productCategory,
            'routePrefix' => 'admin.product-categories',
            'usesSkuCode' => true,
        ]);
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $productCategory->update($this->validatedData($request, $productCategory->id));

        return redirect()->route('admin.product-categories.edit', $productCategory)->with('status', 'Product category updated.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();

        return redirect()->route('admin.product-categories.index')->with('status', 'Product category deleted.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]+$/', Rule::unique('product_categories', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = $this->uniqueSlug(Str::slug($data['name']), $ignoreId);
        $data['code'] = filled($data['code'] ?? null) ? Str::upper($data['code']) : null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base ?: 'item';
        $candidate = $slug;
        $counter = 2;

        while (ProductCategory::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
