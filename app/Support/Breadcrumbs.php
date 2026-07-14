<?php

namespace App\Support;

use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Breadcrumbs
{
    public function forCurrentRoute(Request $request): array
    {
        $routeName = (string) $request->route()?->getName();

        if ($routeName === '' || $routeName === 'home' || str_starts_with($routeName, 'admin.')) {
            return [];
        }

        $items = collect([
            $this->item('Home', route('home')),
        ]);

        match (true) {
            $routeName === 'shop.index' => $this->shopIndex($items, $request),
            $routeName === 'products.show' => $this->productShow($items, $request->route('product')),
            $routeName === 'shop.certified-pre-owned-devices.index' => $this->simple($items, 'Shop', route('shop.index'), 'Certified Pre-Owned Devices'),
            $routeName === 'orders.track' => $this->simple($items, 'Shop', route('shop.index'), 'Track Order'),
            str_starts_with($routeName, 'parts.') => $this->parts($items, $request),
            str_starts_with($routeName, 'repairs.') || str_starts_with($routeName, 'quotes.') => $this->repairs($items, $routeName),
            $routeName === 'about' => $items->push($this->item('About')),
            $routeName === 'services' => $items->push($this->item('Services')),
            str_starts_with($routeName, 'contact.') => $items->push($this->item('Contact')),
            $routeName === 'cart.index' => $items->push($this->item('Cart')),
            str_starts_with($routeName, 'checkout.') => $this->simple($items, 'Cart', route('cart.index'), 'Checkout'),
            str_starts_with($routeName, 'customer.') || $routeName === 'dashboard' => $items->push($this->item('Dashboard')),
            default => $items->push($this->item($this->titleFromRoute($routeName))),
        };

        return $this->normalize($items);
    }

    private function shopIndex(Collection $items, Request $request): void
    {
        $items->push($this->item('Shop', route('shop.index')));
        $items->push($this->item('Products', $request->query() ? route('shop.index') : null));

        if ($request->filled('category')) {
            $category = ProductCategory::query()->where('slug', $request->string('category'))->first();
            $items->push($this->item($category?->name ?: 'Category'));
        }

        if ($request->filled('brand')) {
            $brand = ProductBrand::query()->where('slug', $request->string('brand'))->first();
            $items->push($this->item($brand?->name ?: 'Brand'));
        }

        if ($request->filled('model')) {
            $model = ProductModel::query()->find($request->integer('model'));
            $items->push($this->item($model?->name ?: 'Model'));
        }
    }

    private function productShow(Collection $items, mixed $product): void
    {
        $items->push($this->item('Shop', route('shop.index')));
        $items->push($this->item('Products', route('shop.index')));

        if ($product instanceof Product) {
            if ($product->categoryName()) {
                $items->push($this->item($product->categoryName(), route('shop.index', ['category' => $product->category?->slug])));
            }

            $items->push($this->item($product->name));
        }
    }

    private function parts(Collection $items, Request $request): void
    {
        $items->push($this->item('Parts', route('parts.index')));

        $part = $request->route('part');

        if ($part instanceof Part) {
            $part->loadMissing('categories');
            $category = $part->categories->sortByDesc('level')->first();

            if ($category) {
                foreach ($this->categoryPath($category) as $pathCategory) {
                    $items->push($this->item($pathCategory->name, route('parts.index')));
                }
            }

            $items->push($this->item($part->name));
        } elseif ($request->routeIs('parts.search')) {
            $items->push($this->item('Search'));
        }
    }

    private function repairs(Collection $items, string $routeName): void
    {
        $items->push($this->item('Repair', route('quotes.create')));

        $items->push($this->item(match ($routeName) {
            'quotes.create', 'quotes.store' => 'Get a Quote',
            'repairs.create', 'repairs.store' => 'Book Repair',
            'repairs.track', 'repairs.track.submit' => 'Track Repair',
            default => 'Repair Details',
        }));
    }

    private function simple(Collection $items, string $parent, string $parentUrl, string $current): void
    {
        $items->push($this->item($parent, $parentUrl));
        $items->push($this->item($current));
    }

    private function categoryPath(PartCategory $category): Collection
    {
        $path = collect([$category]);
        $parent = $category->parentCategory;

        while ($parent) {
            $path->prepend($parent);
            $parent = $parent->parentCategory;
        }

        return $path;
    }

    private function item(string $label, ?string $url = null): array
    {
        return ['label' => $label, 'url' => $url, 'active' => $url === null];
    }

    private function normalize(Collection $items): array
    {
        return $items
            ->values()
            ->map(function (array $item, int $index) use ($items): array {
                $item['active'] = $index === $items->count() - 1;

                if ($item['active']) {
                    $item['url'] = null;
                }

                return $item;
            })
            ->all();
    }

    private function titleFromRoute(string $routeName): string
    {
        return str($routeName)->afterLast('.')->replace('-', ' ')->headline()->toString();
    }
}
