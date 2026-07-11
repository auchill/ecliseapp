<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $customer = Customer::forUser($user);

        return view('customer.dashboard', [
            'repairs' => $customer->repairs()->with('deviceBrand', 'deviceModel', 'issueCategory')->latest()->take(5)->get(),
            'orders' => $user->orders()->latest()->take(5)->get(),
            'cart' => $customer->activeCart()->with('items')->first(),
        ]);
    }

    public function repairs(Request $request)
    {
        $customer = Customer::forUser($request->user());

        return view('customer.repairs', [
            'repairs' => $customer->repairs()->with('publicStatusUpdates', 'deviceBrand', 'deviceModel', 'issueCategory')->latest()->paginate(12),
        ]);
    }

    public function orders(Request $request)
    {
        return view('customer.orders', [
            'orders' => $request->user()->orders()->with('items')->latest()->paginate(12),
        ]);
    }
}
