<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileSentrixBuffer;
use App\Services\MobileSentrix\MobileSentrixItemResolver;
use App\Services\MobileSentrix\MobileSentrixProcurementService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The MobileSentrix Procurement Cart: paid customer items that Eclise still has to buy
 * from MobileSentrix. This is not a customer-facing cart.
 */
class MobileSentrixProcurementController extends Controller
{
    public function index(Request $request, MobileSentrixItemResolver $resolver)
    {
        $query = MobileSentrixBuffer::query()
            ->with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when(! $request->filled('status'), fn ($q) => $q->where('status', MobileSentrixBuffer::STATUS_PENDING))
            ->when($request->input('type') === 'device', fn ($q) => $q->where('is_device', true))
            ->when($request->input('type') === 'part', fn ($q) => $q->where('is_part', true))
            ->when($request->filled('sku'), fn ($q) => $q->where('source_sku', 'like', '%'.$request->string('sku').'%'))
            ->when($request->filled('order_number'), fn ($q) => $q->where('order_number', 'like', '%'.$request->string('order_number').'%'))
            ->when($request->filled('repair_number'), fn ($q) => $q->where('repair_number', 'like', '%'.$request->string('repair_number').'%'))
            ->when($request->filled('customer'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('full_name', 'like', '%'.$request->string('customer').'%')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));

        $items = $query->latest('id')->paginate(25)->withQueryString();

        // Resolved live from the catalogue rather than duplicated into the buffer.
        $items->getCollection()->each(function (MobileSentrixBuffer $item) use ($resolver): void {
            $record = $item->sourceRecord();
            $item->setAttribute('resolved_name', $resolver->displayName($record));
            $item->setAttribute('resolved_price', $resolver->procurementPrice($record));
        });

        return view('admin.mobilesentrix.procurement.index', [
            'items' => $items,
            'aggregates' => $this->skuAggregates(),
            'statuses' => MobileSentrixBuffer::STATUSES,
        ]);
    }

    public function store(Request $request, MobileSentrixProcurementService $procurement)
    {
        $data = $request->validate([
            'quantities' => ['required', 'array', 'min:1'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'quantities.required' => 'Select at least one item to procure.',
        ]);

        try {
            $order = $procurement->createProcurementOrder(
                $data['quantities'],
                $request->user(),
                $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['procurement' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.mobilesentrix-orders.show', $order)
            ->with('status', 'MobileSentrix procurement order '.$order->order_number.' created. Place this order with MobileSentrix, then record the supplier reference.');
    }

    /**
     * Aggregate demand per SKU, shown for buying convenience only. Individual requirements are
     * never merged in the database, because that would destroy customer traceability.
     */
    private function skuAggregates(): array
    {
        return MobileSentrixBuffer::query()
            ->pending()
            ->selectRaw('source_sku, SUM(quantity - processed_quantity) as total_required, COUNT(*) as requirement_count')
            ->groupBy('source_sku')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total_required')
            ->limit(10)
            ->get()
            ->all();
    }
}
