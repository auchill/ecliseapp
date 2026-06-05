<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $parts = Part::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model_compatibility', 'like', "%{$search}%")
                        ->orWhere('part_category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('brand'), fn ($query) => $query->where('brand', $request->string('brand')))
            ->when($request->filled('model'), fn ($query) => $query->where('model_compatibility', 'like', '%'.$request->string('model').'%'))
            ->when($request->filled('device_type'), fn ($query) => $query->where('device_type', $request->string('device_type')))
            ->when($request->filled('part_category'), fn ($query) => $query->where('part_category', $request->string('part_category')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('parts.index', [
            'parts' => $parts,
            'brands' => Part::query()->distinct()->orderBy('brand')->pluck('brand'),
            'deviceTypes' => Part::query()->distinct()->orderBy('device_type')->pluck('device_type'),
            'categories' => Part::CATEGORIES,
        ]);
    }
}
