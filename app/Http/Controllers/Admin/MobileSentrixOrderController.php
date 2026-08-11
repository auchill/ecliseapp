<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileSentrixOrder;
use App\Models\ShippingMethod;
use App\Services\MobileSentrix\MobileSentrixItemResolver;
use App\Services\MobileSentrix\MobileSentrixProcurementService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MobileSentrixOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = MobileSentrixOrder::query()
            ->withCount('items')
            ->when($request->filled('order_status'), fn ($q) => $q->where('order_status', $request->string('order_status')))
            ->when($request->filled('order_number'), fn ($q) => $q->where('order_number', 'like', '%'.$request->string('order_number').'%'))
            ->when($request->filled('supplier_order_number'), fn ($q) => $q->where('supplier_order_number', 'like', '%'.$request->string('supplier_order_number').'%'))
            ->when($request->filled('tracking_number'), fn ($q) => $q->where('tracking_number', 'like', '%'.$request->string('tracking_number').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.mobilesentrix.orders.index', [
            'orders' => $orders,
            'statuses' => MobileSentrixOrder::STATUSES,
        ]);
    }

    public function show(MobileSentrixOrder $mobilesentrixOrder, MobileSentrixItemResolver $resolver)
    {
        $mobilesentrixOrder->load('items.customer', 'shippingMethod', 'createdBy');

        $mobilesentrixOrder->items->each(function ($item) use ($resolver): void {
            $item->setAttribute('resolved_name', $resolver->displayName($item->sourceRecord()));
        });

        return view('admin.mobilesentrix.orders.show', [
            'order' => $mobilesentrixOrder,
            'shippingMethods' => ShippingMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, MobileSentrixOrder $mobilesentrixOrder, MobileSentrixProcurementService $procurement)
    {
        $data = $request->validate([
            'supplier_order_number' => ['nullable', 'string', 'max:255'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'delivery_carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'tracking_notes' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        foreach (['tax', 'shipping_cost', 'shipping_discount_amount', 'payment_amount'] as $money) {
            $data[$money] = $data[$money] ?? 0;
        }

        try {
            $procurement->updateOrder($mobilesentrixOrder, $data, $request->user(), $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['procurement' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Procurement order updated.');
    }

    public function receive(Request $request, MobileSentrixOrder $mobilesentrixOrder, MobileSentrixProcurementService $procurement)
    {
        return $this->transition($request, $mobilesentrixOrder, $procurement, MobileSentrixOrder::STATUS_RECEIVED);
    }

    public function return(Request $request, MobileSentrixOrder $mobilesentrixOrder, MobileSentrixProcurementService $procurement)
    {
        return $this->transition($request, $mobilesentrixOrder, $procurement, MobileSentrixOrder::STATUS_RETURNED);
    }

    private function transition(Request $request, MobileSentrixOrder $order, MobileSentrixProcurementService $procurement, string $status)
    {
        try {
            $procurement->transitionStatus($order, $status, $request->user(), $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['procurement' => $exception->getMessage()]);
        }

        return back()->with('status', 'Procurement order marked '.$status.'.');
    }
}
