<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEcliseMarkupRequest;
use App\Models\EcliseMarkup;
use App\Services\MobileSentrixMarkupService;
use Illuminate\Http\Request;

class EcliseMarkupController extends Controller
{
    public function index(Request $request, MobileSentrixMarkupService $markupService)
    {
        $markups = EcliseMarkup::query()
            ->when($request->filled('item_type'), fn ($query) => $query->where('item_type', $request->string('item_type')))
            ->when($request->filled('scope_type'), fn ($query) => $query->where('scope_type', $request->string('scope_type')))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('item_type')
            ->orderBy('scope_type')
            ->orderByDesc('priority')
            ->orderBy('brand_text')
            ->orderBy('min_price')
            ->paginate(20)
            ->withQueryString();

        return view('admin.eclise-markups.index', [
            'markups' => $markups,
        ]);
    }

    public function create(MobileSentrixMarkupService $markupService)
    {
        return view('admin.eclise-markups.form', [
            'markup' => new EcliseMarkup([
                'item_type' => EcliseMarkup::ITEM_TYPE_PARTS,
                'scope_type' => EcliseMarkup::SCOPE_ALL,
                'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
                'markup_value' => 0,
                'priority' => 0,
                'is_active' => true,
            ]),
            'brandOptions' => $this->brandOptions($markupService),
            'priceBounds' => $this->priceBounds($markupService),
        ]);
    }

    public function store(StoreEcliseMarkupRequest $request)
    {
        EcliseMarkup::query()->create($request->validated());

        return redirect()->route('admin.mobilesentrix-markups.index')->with('status', 'MobileSentrix markup rule created.');
    }

    public function edit(EcliseMarkup $ecliseMarkup, MobileSentrixMarkupService $markupService)
    {
        return view('admin.eclise-markups.form', [
            'markup' => $ecliseMarkup,
            'brandOptions' => $this->brandOptions($markupService),
            'priceBounds' => $this->priceBounds($markupService),
        ]);
    }

    public function update(StoreEcliseMarkupRequest $request, EcliseMarkup $ecliseMarkup)
    {
        $ecliseMarkup->update($request->validated());

        return redirect()->route('admin.mobilesentrix-markups.edit', $ecliseMarkup)->with('status', 'MobileSentrix markup rule updated.');
    }

    public function destroy(EcliseMarkup $ecliseMarkup)
    {
        $ecliseMarkup->delete();

        return redirect()->route('admin.mobilesentrix-markups.index')->with('status', 'MobileSentrix markup rule deleted.');
    }

    public function toggle(EcliseMarkup $ecliseMarkup)
    {
        $ecliseMarkup->update(['is_active' => ! $ecliseMarkup->is_active]);

        return redirect()->route('admin.mobilesentrix-markups.index')->with('status', 'MobileSentrix markup rule '.($ecliseMarkup->is_active ? 'activated.' : 'deactivated.'));
    }

    public function refresh(MobileSentrixMarkupService $markupService)
    {
        $summary = $markupService->refreshSummary();

        return redirect()->route('admin.mobilesentrix-markups.index')->with(
            'status',
            'Markup refresh completed. Active Parts rules: '.$summary['parts_rules']
                .'. Active Pre-Owned Device rules: '.$summary['pre_owned_device_rules']
                .'. Source prices modified: '.$summary['source_prices_modified']
                .'. Errors: '.$summary['errors'].'.',
        );
    }

    private function brandOptions(MobileSentrixMarkupService $markupService): array
    {
        return collect(array_keys(EcliseMarkup::ITEM_TYPES))
            ->mapWithKeys(fn (string $itemType): array => [$itemType => $markupService->brandOptions($itemType)->all()])
            ->all();
    }

    private function priceBounds(MobileSentrixMarkupService $markupService): array
    {
        return collect(array_keys(EcliseMarkup::ITEM_TYPES))
            ->mapWithKeys(fn (string $itemType): array => [$itemType => $markupService->priceBounds($itemType)])
            ->all();
    }
}
