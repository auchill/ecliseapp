<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Services\MobileSentrix\MobileSentrixProductEnrichmentService;
use App\Services\Parts\PartSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PartController extends Controller
{
    public function index(Request $request, PartSearchService $partSearch): View
    {
        return view('parts.index', [
            'parts' => $partSearch->publicResults($request),
            'brands' => Part::query()
                ->customerFacing()
                ->where('is_active', true)
                ->where('status', 'active')
                ->whereNotNull('brand')
                ->whereRaw("TRIM(brand) != ''")
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand'),
            'deviceTypes' => Part::query()->customerFacing()->where('is_active', true)->where('status', 'active')->distinct()->orderBy('device_type')->pluck('device_type'),
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

    public function show(Part $part, MobileSentrixProductEnrichmentService $enrichmentService): View
    {
        abort_unless($part->is_active && $part->status === 'active', 404);

        try {
            $part = $enrichmentService->enrichPart($part);
        } catch (\Throwable $exception) {
            Log::warning('Unable to enrich part before rendering product page.', [
                'part_id' => $part->id,
                'sku' => $part->sku,
                'message' => $exception->getMessage(),
            ]);
        }

        abort_unless($part->is_active && $part->status === 'active', 404);

        $part->load('categories');

        return view('parts.show', ['part' => $part]);
    }
}
