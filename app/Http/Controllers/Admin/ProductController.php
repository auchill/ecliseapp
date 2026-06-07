<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category', 'productBrand', 'productCategory')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product,
            'categories' => Category::query()->where('type', 'product')->orderBy('name')->get(),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCategories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'conditions' => Product::CONDITIONS,
            'statuses' => Product::STATUSES,
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Product::query()->create($data);

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->where('type', 'product')->orderBy('name')->get(),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCategories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'conditions' => Product::CONDITIONS,
            'statuses' => Product::STATUSES,
        ]);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $product);

        $product->update($data);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    private function validatedData(StoreProductRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('product_image')) {
            $data['image_path'] = $request->file('product_image')->store('products', 'public');
        }

        if (! empty($data['product_brand_id'])) {
            $data['brand'] = ProductBrand::query()->find($data['product_brand_id'])?->name;
        }

        if (! empty($data['product_category_id'])) {
            $productCategory = ProductCategory::query()->find($data['product_category_id']);
            $legacyCategory = $productCategory
                ? Category::query()->firstOrCreate(
                    ['slug' => $productCategory->slug],
                    ['name' => $productCategory->name, 'type' => 'product'],
                )
                : null;

            $data['category_id'] = $legacyCategory?->id;
        }

        unset($data['product_image']);

        return $data;
    }

    private function uniqueSlug(string $name, ?Product $product = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (Product::query()
            ->where('slug', $slug)
            ->when($product, fn ($query) => $query->whereKeyNot($product->id))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
