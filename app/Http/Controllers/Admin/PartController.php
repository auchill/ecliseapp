<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartRequest;
use App\Models\Part;
use App\Services\MobileSentrixService;
use Illuminate\Http\Request;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $parts = Part::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model_compatibility', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.parts.index', [
            'parts' => $parts,
        ]);
    }

    public function create()
    {
        return view('admin.parts.form', [
            'part' => new Part,
            'categories' => Part::CATEGORIES,
        ]);
    }

    public function store(StorePartRequest $request)
    {
        Part::query()->create($this->validatedData($request));

        return redirect()->route('admin.parts.index')->with('status', 'Part created.');
    }

    public function edit(Part $part)
    {
        return view('admin.parts.form', [
            'part' => $part,
            'categories' => Part::CATEGORIES,
        ]);
    }

    public function update(StorePartRequest $request, Part $part)
    {
        $part->update($this->validatedData($request));

        return redirect()->route('admin.parts.edit', $part)->with('status', 'Part updated.');
    }

    public function destroy(Part $part)
    {
        $part->delete();

        return redirect()->route('admin.parts.index')->with('status', 'Part deleted.');
    }

    public function sync(MobileSentrixService $mobileSentrix)
    {
        $result = $mobileSentrix->syncParts();

        return redirect()->route('admin.parts.index')->with('status', $result['message']);
    }

    private function validatedData(StorePartRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('part_image')) {
            $data['image_path'] = $request->file('part_image')->store('parts', 'public');
        }

        unset($data['part_image']);

        return $data;
    }
}
