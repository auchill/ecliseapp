<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Services\Parts\PartSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartController extends Controller
{
    public function index(Request $request, PartSearchService $partSearch): View
    {
        return view('parts.index', [
            'parts' => $partSearch->publicResults($request),
            'brands' => PartBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'deviceTypes' => Part::query()->where('is_active', true)->where('status', 'active')->distinct()->orderBy('device_type')->pluck('device_type'),
            'categories' => PartCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function search(Request $request, PartSearchService $partSearch): JsonResponse
    {
        $parts = $partSearch->publicResults($request);

        return response()->json([
            'html' => view('parts.partials.results', ['parts' => $parts])->render(),
            'count' => $parts->total(),
        ]);
    }

    public function suggestions(Request $request, PartSearchService $partSearch): JsonResponse
    {
        return response()->json([
            'suggestions' => $partSearch->publicSuggestions($request),
        ]);
    }

    public function show(Part $part): View
    {
        abort_unless($part->is_active && $part->status === 'active', 404);

        $part->load('partBrand', 'partCategory', 'partModel', 'partCategories');

        return view('parts.show', ['part' => $part]);
    }
}
