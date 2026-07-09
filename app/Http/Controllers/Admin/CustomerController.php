<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = User::query()
            ->customers()
            ->withCount(['repairBookings', 'orders'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
        ]);
    }

    public function show(User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        return view('admin.customers.show', [
            'customer' => $customer->load(['customer', 'repairBookings.deviceBrand', 'repairBookings.deviceModel', 'repairBookings.issueCategory', 'orders.items']),
        ]);
    }
}
