<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ShopProductFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ShopController extends Controller
{
    public function index(Request $request, ShopProductFilters $filters)
    {
        $this->normalizeFilterRequest($request);

        if ($validationResponse = $this->validateFilterRequest($request)) {
            return $validationResponse;
        }

        try {
            $products = $filters->query($request)
                ->paginate(12)
                ->withQueryString();

            $viewData = [
                'products' => $products,
                'filterOptions' => $filters->filterOptions($request),
                'selectedChips' => $filters->selectedChips($request),
                'activeFilterCount' => $filters->activeFilterCount($request),
                'priceBounds' => $filters->priceBounds($request),
                'sortOptions' => ShopProductFilters::SORT_OPTIONS,
            ];

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($this->filterJsonPayload($viewData, $products));
            }

            return view('shop.index', $viewData);
        } catch (Throwable $exception) {
            if ($request->expectsJson() || $request->ajax()) {
                Log::error('Dynamic product filter failed', [
                    'filters' => $this->filterLogContext($request),
                    'exception' => $exception,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'The product filters could not be loaded.',
                ], 500);
            }

            throw $exception;
        }
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

    private function normalizeFilterRequest(Request $request): void
    {
        if (blank($request->query('q')) && filled($request->query('search'))) {
            $request->query->set('q', trim((string) $request->query('search')));
        }

        if ($request->query('sort') === 'sale') {
            $request->query->set('sort', 'on_sale');
        }

        foreach (['min_price', 'max_price'] as $key) {
            if ($request->query($key) === '') {
                $request->query->remove($key);
            }
        }

        foreach (['brands', 'conditions', 'grades', 'colors'] as $key) {
            $value = $request->query($key);

            if (is_string($value)) {
                $request->query->set(
                    $key,
                    str_contains($value, ',')
                        ? collect(explode(',', $value))->map(fn (string $item): string => trim($item))->filter()->values()->all()
                        : [$value],
                );
            }
        }
    }

    private function validateFilterRequest(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['string', 'distinct', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['string', 'distinct', 'max:255'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['string', 'distinct', 'max:255'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['string', 'distinct', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'sort' => ['nullable', Rule::in(array_merge(array_keys(ShopProductFilters::SORT_OPTIONS), ['sale']))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $validator->fails()) {
            return null;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'The selected product filters are invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        abort(404);
    }

    private function filterJsonPayload(array $viewData, $products): array
    {
        $html = [
            'products' => view('shop.products.partials.results', $viewData)->render(),
            'summary' => view('shop.products.partials.summary', $viewData)->render(),
            'active_filters' => view('shop.products.partials.active-filters', $viewData)->render(),
            'desktop_filter_groups' => view('shop.partials.filter-groups', $viewData + ['idPrefix' => 'desktop'])->render(),
            'mobile_filter_groups' => view('shop.partials.filter-groups', $viewData + ['idPrefix' => 'mobile'])->render(),
            'desktop_filters' => view('shop.partials.filter-panel', $viewData + ['idPrefix' => 'desktop'])->render(),
            'mobile_filters' => view('shop.partials.filter-panel', $viewData + ['idPrefix' => 'mobile'])->render(),
        ];

        return [
            'success' => true,
            'html' => $html,
            'meta' => [
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'active_filter_count' => $viewData['activeFilterCount'],
            ],
            'results' => $html['products'],
            'summary' => $html['summary'],
            'activeFilters' => $html['active_filters'],
            'desktopFilterGroups' => $html['desktop_filter_groups'],
            'mobileFilterGroups' => $html['mobile_filter_groups'],
            'desktopFilters' => $html['desktop_filters'],
            'mobileFilters' => $html['mobile_filters'],
            'activeFilterCount' => $viewData['activeFilterCount'],
            'total' => $products->total(),
        ];
    }

    private function filterLogContext(Request $request): array
    {
        return $request->only([
            'q',
            'search',
            'category',
            'brands',
            'conditions',
            'grades',
            'colors',
            'min_price',
            'max_price',
            'sort',
            'page',
        ]);
    }
}
