<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCarrier;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category', 'productBrand', 'productCategory', 'productModel', 'productSize', 'productGrade', 'productCondition', 'productColor', 'productCarrier')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('brand'), fn ($query) => $query->where('product_brand_id', $request->integer('brand')))
            ->when($request->filled('model'), fn ($query) => $query->where('product_model_id', $request->integer('model')))
            ->when($request->filled('condition'), fn ($query) => $query->where('product_condition_id', $request->integer('condition')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'productBrands' => ProductBrand::query()->active()->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('name')->get(),
            'productConditions' => ProductCondition::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product,
            'categories' => Category::query()->where('type', 'product')->orderBy('name')->get(),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCategories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productSizes' => ProductSize::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productGrades' => ProductGrade::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productConditions' => ProductCondition::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productColors' => ProductColor::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCarriers' => ProductCarrier::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
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
            'productModels' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productSizes' => ProductSize::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productGrades' => ProductGrade::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productConditions' => ProductCondition::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productColors' => ProductColor::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCarriers' => ProductCarrier::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
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

        if (! empty($data['product_model_id'])) {
            $data['model'] = ProductModel::query()->find($data['product_model_id'])?->name;
        }

        $data['condition'] = ProductCondition::query()->find($data['product_condition_id'])?->name ?? 'New';

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
