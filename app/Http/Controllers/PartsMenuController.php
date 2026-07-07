<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartCategory;
use App\Services\Parts\PartSearchService;
use App\Services\Parts\PartsMenuService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartsMenuController extends Controller
{
    public function index(Request $request, PartSearchService $partSearch, PartsMenuService $partsMenu): View
    {
        if ($this->hasListingQuery($request)) {
            return view('parts.listing', [
                'parts' => $partSearch->publicResults($request),
                'brands' => $this->brandOptions(),
                'deviceTypes' => $this->deviceTypeOptions(),
            ]);
        }

        return view('parts.index', [
            'mainMenu' => $partsMenu->mainMenu(),
        ]);
    }

    public function menu(PartsMenuService $partsMenu): JsonResponse
    {
        return response()->json([
            'menu' => $partsMenu->mainMenu(),
        ]);
    }

    public function children(PartCategory $category, PartsMenuService $partsMenu): JsonResponse
    {
        abort_unless($partsMenu->isVisible($category), 404);

        $children = $partsMenu->childrenFor($category);

        return response()->json([
            'category' => $partsMenu->categoryPayload($category),
            'children' => $children,
            'has_children' => $children->isNotEmpty(),
        ]);
    }

    public function parts(Request $request, PartCategory $category, PartsMenuService $partsMenu): JsonResponse
    {
        abort_unless($partsMenu->isVisible($category), 404);

        $perPage = max(1, min((int) $request->integer('per_page', 24), 60));
        $parts = $category->parts()
            ->with('categories')
            ->select('parts.*')
            ->where('parts.is_active', true)
            ->where('parts.status', 'active')
            ->orderBy('parts.name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'category' => $partsMenu->categoryPayload($category),
            'html' => view('parts.partials.menu-parts', ['parts' => $parts])->render(),
            'current_page' => $parts->currentPage(),
            'last_page' => $parts->lastPage(),
            'has_more' => $parts->hasMorePages(),
            'next_page' => $parts->hasMorePages() ? $parts->currentPage() + 1 : null,
            'next_page_url' => $parts->nextPageUrl(),
            'total' => $parts->total(),
        ]);
    }

    public function search(Request $request, PartSearchService $partSearch, PartsMenuService $partsMenu): JsonResponse
    {
        $parts = $partSearch->publicResults($request);
        $keyword = trim($request->string('q')->toString());

        return response()->json([
            'html' => view('parts.partials.results', ['parts' => $parts])->render(),
            'count' => $parts->total(),
            'categories' => $partsMenu->searchCategories($keyword),
            'parts' => $this->searchParts($keyword),
        ]);
    }

    private function hasListingQuery(Request $request): bool
    {
        return collect(['q', 'brand', 'model', 'device_type', 'stock', 'min_price', 'max_price', 'page'])
            ->contains(fn (string $key): bool => $request->query->has($key));
    }

    private function searchParts(string $keyword)
    {
        if (mb_strlen($keyword) < 2) {
            return collect();
        }

        return Part::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($keyword): void {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhere('new_sku', 'like', "%{$keyword}%")
                    ->orWhere('model', 'like', "%{$keyword}%")
                    ->orWhere('model_compatibility', 'like', "%{$keyword}%")
                    ->orWhere('device_model_text', 'like', "%{$keyword}%")
                    ->orWhere('model_text', 'like', "%{$keyword}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Part $part): array => [
                'id' => (int) $part->id,
                'name' => $part->name,
                'sku' => $part->sku ?: $part->new_sku,
                'model' => $part->modelName(),
                'brand' => $part->brandName(),
                'price' => number_format($part->displayPrice(), 2),
                'image_url' => $this->partMenuImageUrl($part),
                'fallback_image_url' => asset('images/brand/logo_main.png'),
                'url' => route('parts.show', $part),
            ])
            ->values();
    }

    private function brandOptions()
    {
        return Part::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNotNull('brand')
            ->whereRaw("TRIM(brand) != ''")
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
    }

    private function deviceTypeOptions()
    {
        return Part::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNotNull('device_type')
            ->whereRaw("TRIM(device_type) != ''")
            ->distinct()
            ->orderBy('device_type')
            ->pluck('device_type');
    }

    private function partMenuImageUrl(Part $part): string
    {
        if ($part->local_image_path ?: $part->image_path) {
            return asset('storage/'.($part->local_image_path ?: $part->image_path));
        }

        if ($part->default_image ?: $part->image_url) {
            return $part->default_image ?: $part->image_url;
        }

        return asset('images/brand/logo_main.png');
    }
}
