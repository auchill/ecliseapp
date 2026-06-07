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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('product_categories', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
