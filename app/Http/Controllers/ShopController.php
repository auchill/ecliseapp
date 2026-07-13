<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $networkId = $request->integer('network') ?: $request->integer('carrier');

        $products = Product::query()
            ->with('category', 'productBrand', 'productModel', 'sizes', 'productGrade', 'productCondition', 'productColor', 'network', 'primaryImage')
            ->active()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('productBrand', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('productModel', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), function ($query) use ($request): void {
                $category = $request->string('category');
                $query->whereHas('category', fn ($query) => $query->where('slug', $category));
            })
            ->when($request->filled('brand'), function ($query) use ($request): void {
                $brand = $request->string('brand');
                $query->whereHas('productBrand', fn ($query) => $query->where('slug', $brand));
            })
            ->when($request->filled('model'), fn ($query) => $query->where('product_model_id', $request->integer('model')))
            ->when($request->filled('size'), fn ($query) => $query->whereHas('sizes', fn ($query) => $query->whereKey($request->integer('size'))))
            ->when($request->filled('grade'), fn ($query) => $query->where('product_grade_id', $request->integer('grade')))
            ->when($request->filled('condition'), fn ($query) => $query->where('product_condition_id', $request->integer('condition')))
            ->when($request->filled('color'), fn ($query) => $query->where('product_color_id', $request->integer('color')))
            ->when($networkId, fn ($query) => $query->where('product_network_id', $networkId))
            ->when($request->filled('min_price'), fn ($query) => $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [(float) $request->input('min_price')]))
            ->when($request->filled('max_price'), fn ($query) => $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [(float) $request->input('max_price')]))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'categories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'models' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'sizes' => ProductSize::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'grades' => ProductGrade::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'conditions' => ProductCondition::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'colors' => ProductColor::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'networks' => ProductNetwork::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->isAvailable(), 404);

        return view('shop.show', [
            'product' => $product->load('category', 'productBrand', 'productModel', 'sizes', 'productGrade', 'productCondition', 'productColor', 'network', 'images', 'primaryImage'),
            'relatedProducts' => Product::query()
                ->with('category', 'productBrand', 'productModel', 'sizes', 'productGrade', 'productCondition', 'productColor', 'network', 'primaryImage')
                ->active()
                ->where('id', '!=', $product->id)
                ->where('product_category_id', $product->product_category_id)
                ->take(3)
                ->get(),
        ]);
    }
}
