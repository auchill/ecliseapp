<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        return view('customer.dashboard', [
            'repairs' => $user->repairBookings()->latest()->take(5)->get(),
            'orders' => $user->orders()->latest()->take(5)->get(),
            'cart' => $user->carts()->where('status', 'active')->with('items.product')->first(),
        ]);
    }

    public function repairs(Request $request)
    {
        return view('customer.repairs', [
            'repairs' => $request->user()->repairBookings()->with('publicStatusUpdates')->latest()->paginate(12),
        ]);
    }

    public function orders(Request $request)
    {
        return view('customer.orders', [
            'orders' => $request->user()->orders()->with('items')->latest()->paginate(12),
        ]);
    }
}
