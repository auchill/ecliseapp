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
            ->where(function ($query) use ($contact): void {
                $query->where('email', $contact)
                    ->orWhere('phone', $contact)
                    ->orWhere('shipping_email', $contact)
                    ->orWhere('shipping_phone', $contact);
            })
            ->with(['items', 'publicStatusUpdates'])
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
