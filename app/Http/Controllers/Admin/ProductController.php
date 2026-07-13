<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductImage;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use App\Services\ProductSkuGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category', 'productBrand', 'productModel', 'sizes', 'productGrade', 'productCondition', 'productColor', 'network', 'primaryImage')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('productBrand', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('productModel', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('brand'), fn ($query) => $query->where('product_brand_id', $request->integer('brand')))
            ->when($request->filled('model'), fn ($query) => $query->where('product_model_id', $request->integer('model')))
            ->when($request->filled('condition'), fn ($query) => $query->where('product_condition_id', $request->integer('condition')))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'productBrands' => ProductBrand::query()->active()->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('name')->get(),
            'productConditions' => ProductCondition::query()->active()->orderBy('name')->get(),
            'activeOptions' => ['1' => 'Active', '0' => 'Inactive'],
        ]);
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product,
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCategories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productSizes' => ProductSize::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productGrades' => ProductGrade::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productConditions' => ProductCondition::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productColors' => ProductColor::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productNetworks' => ProductNetwork::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request, ProductSkuGenerator $skuGenerator)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['sku'] = filled($data['sku'] ?? null)
            ? $data['sku']
            : $skuGenerator->next(! empty($data['product_category_id']) ? ProductCategory::query()->find($data['product_category_id']) : null);

        DB::transaction(function () use ($data, $request): void {
            $product = Product::query()->create($data);
            $product->sizes()->sync($request->safe()->input('product_size_ids', []));
            $this->storeImages($request, $product);
            $this->ensurePrimaryImage($product);
        });

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product->load('sizes', 'images'),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productCategories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productSizes' => ProductSize::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productGrades' => ProductGrade::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productConditions' => ProductCondition::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productColors' => ProductColor::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productNetworks' => ProductNetwork::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $product);

        if (blank($data['sku'] ?? null)) {
            unset($data['sku']);
        }

        DB::transaction(function () use ($data, $request, $product): void {
            $product->update($data);
            $product->sizes()->sync($request->safe()->input('product_size_ids', []));
            $this->deleteImages($request, $product);
            $this->setPrimaryImage($request, $product);
            $this->storeImages($request, $product);
            $this->ensurePrimaryImage($product);
        });

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

        $data['source'] = $data['source'] ?? 'manual';
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');

        unset($data['product_images'], $data['product_size_ids'], $data['primary_image_id'], $data['delete_image_ids']);

        return $data;
    }

    private function storeImages(StoreProductRequest $request, Product $product): void
    {
        $files = collect($request->file('product_images', []))
            ->filter()
            ->values();

        if ($files->isEmpty()) {
            return;
        }

        $nextSort = (int) $product->images()->max('sort_order');
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach ($files as $index => $file) {
            $product->images()->create([
                'image_path' => $file->store('products', 'public'),
                'alt_text' => $product->name,
                'sort_order' => $nextSort + $index + 1,
                'is_primary' => ! $hasPrimary && $index === 0,
            ]);
        }
    }

    private function deleteImages(StoreProductRequest $request, Product $product): void
    {
        $imageIds = collect($request->safe()->input('delete_image_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        if ($imageIds->isEmpty()) {
            return;
        }

        $product->images()->whereIn('id', $imageIds)->delete();
    }

    private function setPrimaryImage(StoreProductRequest $request, Product $product): void
    {
        $primaryImageId = (int) $request->safe()->input('primary_image_id', 0);

        if ($primaryImageId <= 0) {
            return;
        }

        $image = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKey($primaryImageId)
            ->first();

        if (! $image) {
            return;
        }

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    private function ensurePrimaryImage(Product $product): void
    {
        if ($product->images()->where('is_primary', true)->exists()) {
            return;
        }

        $image = $product->images()->oldest('sort_order')->oldest('id')->first();

        if ($image) {
            $image->update(['is_primary' => true]);
        }
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
