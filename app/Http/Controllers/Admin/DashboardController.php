<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'metrics' => [
                'Total repair bookings' => RepairBooking::query()->count(),
                'Pending repairs' => RepairBooking::query()->whereNotIn('status', ['Completed', 'Cancelled'])->count(),
                'Completed repairs' => RepairBooking::query()->where('status', 'Completed')->count(),
                'Total products' => Product::query()->count(),
                'Total orders' => Order::query()->count(),
                'Total customers' => User::query()->where('role', 'customer')->count(),
                'Total listed parts' => Part::query()->count(),
                'Unread messages' => ContactMessage::query()->whereNull('read_at')->count(),
            ],
            'repairs' => RepairBooking::query()->latest()->take(5)->get(),
            'orders' => Order::query()->latest()->take(5)->get(),
        ]);
    }
}
