<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function form()
    {
        return view('orders.track');
    }

    public function result(Request $request)
    {
        // Tracking requires authentication and results are scoped to the signed-in customer's
        // own orders, so no contact detail is collected or needed.
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:40'],
        ]);

        $order = $this->scopeToRequester(
            Order::query()->where('order_number', $data['order_number']),
            $request,
        )
            ->with(['items', 'customer', 'shipping', 'publicStatusUpdates', 'latestPayment'])
            ->first();

        if (! $order) {
            return back()
                ->withErrors(['order_number' => 'No order was found for that order number.'])
                ->withInput();
        }

        return view('orders.track', [
            'order' => $order,
        ]);
    }

    /**
     * Restricts a lookup to records the requester is entitled to see.
     *
     * Tracking requires authentication, so a customer can only ever reach their own orders,
     * whatever order number they type. Admins are unrestricted because they need to look up any
     * customer operationally.
     */
    private function scopeToRequester(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $query;
        }

        // A guest reaching this point means the auth middleware was removed; match nothing
        // rather than falling through to an unscoped query. A missing customer profile is
        // matched to 0 for the same reason: never NULL, which would match orphaned records.
        return $query->where('customer_id', $user?->customer?->id ?? 0);
    }
}
