<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category')
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
                $query->whereHas('category', fn ($query) => $query->where('slug', $request->string('category')));
            })
            ->when($request->filled('brand'), fn ($query) => $query->where('brand', $request->string('brand')))
            ->when($request->filled('condition'), fn ($query) => $query->where('condition', $request->string('condition')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('price', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', $request->input('max_price')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'categories' => Category::query()->where('type', 'product')->orderBy('name')->get(),
            'brands' => Product::query()->active()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'conditions' => Product::CONDITIONS,
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->status === 'Active', 404);

        return view('shop.show', [
            'product' => $product->load('category'),
            'relatedProducts' => Product::query()
                ->active()
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->take(3)
                ->get(),
        ]);
    }
}
