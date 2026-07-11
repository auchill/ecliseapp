<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function form()
    {
        return view('orders.track');
    }

    public function result(Request $request)
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:40'],
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $contact = trim($data['contact']);

        $order = Order::query()
            ->where('order_number', $data['order_number'])
            ->whereHas('customer', function ($query) use ($contact): void {
                $query->where('email', $contact)
                    ->orWhere('phone', $contact);
            })
            ->with(['items', 'customer', 'shipping', 'publicStatusUpdates', 'latestPayment'])
            ->first();

        if (! $order) {
            return back()
                ->withErrors(['order_number' => 'No order was found for that order number and contact detail.'])
                ->withInput();
        }

        return view('orders.track', [
            'order' => $order,
        ]);
    }
}
