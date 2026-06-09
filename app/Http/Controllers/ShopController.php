<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category', 'productBrand', 'productCategory', 'productModel')
            ->active()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), function ($query) use ($request): void {
                $category = $request->string('category');
                $query->where(function ($query) use ($category): void {
                    $query->whereHas('productCategory', fn ($query) => $query->where('slug', $category))
                        ->orWhereHas('category', fn ($query) => $query->where('slug', $category));
                });
            })
            ->when($request->filled('brand'), function ($query) use ($request): void {
                $brand = $request->string('brand');
                $query->where(function ($query) use ($brand): void {
                    $query->whereHas('productBrand', fn ($query) => $query->where('slug', $brand))
                        ->orWhere('brand', $brand);
                });
            })
            ->when($request->filled('condition'), fn ($query) => $query->where('condition', $request->string('condition')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('price', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', $request->input('max_price')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'categories' => ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'conditions' => Product::CONDITIONS,
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->status === 'Active', 404);

        return view('shop.show', [
            'product' => $product->load('category', 'productBrand', 'productCategory', 'productModel'),
            'relatedProducts' => Product::query()
                ->with('category', 'productBrand', 'productCategory', 'productModel')
                ->active()
                ->where('id', '!=', $product->id)
                ->when(
                    $product->product_category_id,
                    fn ($query) => $query->where('product_category_id', $product->product_category_id),
                    fn ($query) => $query->where('category_id', $product->category_id),
                )
                ->take(3)
                ->get(),
        ]);
    }
}
