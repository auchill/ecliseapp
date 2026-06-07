<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductBrandController extends Controller
{
    public function index()
    {
        return view('admin.taxonomies.index', [
            'title' => 'Product Brands',
            'items' => ProductBrand::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
            'routePrefix' => 'admin.product-brands',
        ]);
    }

    public function create()
    {
        return view('admin.taxonomies.form', [
            'title' => 'Add Product Brand',
            'item' => new ProductBrand(['is_active' => true, 'sort_order' => 0]),
            'routePrefix' => 'admin.product-brands',
        ]);
    }

    public function store(Request $request)
    {
        ProductBrand::query()->create($this->validatedData($request));

        return redirect()->route('admin.product-brands.index')->with('status', 'Product brand created.');
    }

    public function edit(ProductBrand $productBrand)
    {
        return view('admin.taxonomies.form', [
            'title' => 'Edit Product Brand',
            'item' => $productBrand,
            'routePrefix' => 'admin.product-brands',
        ]);
    }

    public function update(Request $request, ProductBrand $productBrand)
    {
        $productBrand->update($this->validatedData($request, $productBrand->id));

        return redirect()->route('admin.product-brands.edit', $productBrand)->with('status', 'Product brand updated.');
    }

    public function destroy(ProductBrand $productBrand)
    {
        $productBrand->delete();

        return redirect()->route('admin.product-brands.index')->with('status', 'Product brand deleted.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('product_brands', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
