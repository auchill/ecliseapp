<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RepairBooking;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'metrics' => [
                'Total quotes' => ['value' => Quote::query()->count(), 'route' => route('admin.quotes.index'), 'icon' => 'bi-chat-square-text'],
                'Pending quotes' => ['value' => Quote::query()->where('status', 'pending')->count(), 'route' => route('admin.quotes.index', ['status' => 'pending']), 'icon' => 'bi-hourglass-split'],
                'Approved quotes' => ['value' => Quote::query()->where('status', 'approved')->count(), 'route' => route('admin.quotes.index', ['status' => 'approved']), 'icon' => 'bi-check2-circle'],
                'Repair bookings' => ['value' => RepairBooking::query()->count(), 'route' => route('admin.repairs.index'), 'icon' => 'bi-tools'],
                'Awaiting payment repairs' => ['value' => RepairBooking::query()->whereIn('payment_status', ['unpaid', 'pending', 'partially_paid'])->count(), 'route' => route('admin.repairs.index'), 'icon' => 'bi-wallet2'],
                'Shop orders' => ['value' => Order::query()->where('source', 'shop')->count(), 'route' => route('admin.orders.index'), 'icon' => 'bi-receipt'],
                'Shop payments' => ['value' => Payment::query()->where('source', 'shop')->count(), 'route' => route('admin.shop-payments.index'), 'icon' => 'bi-credit-card'],
                'Repair payments' => ['value' => Payment::query()->where('source', 'repair')->count(), 'route' => route('admin.repair-payments.index'), 'icon' => 'bi-cash-stack'],
                'Customers' => ['value' => User::query()->customers()->count(), 'route' => route('admin.customers.index'), 'icon' => 'bi-people'],
                'Messages' => ['value' => ContactMessage::query()->whereNull('read_at')->count(), 'route' => route('admin.contact-messages.index'), 'icon' => 'bi-envelope'],
                'Products' => ['value' => Product::query()->count(), 'route' => route('admin.products.index'), 'icon' => 'bi-phone'],
                'Listed parts' => ['value' => Part::query()->count(), 'route' => route('admin.parts.index'), 'icon' => 'bi-cpu'],
            ],
            'repairs' => RepairBooking::query()->latest()->take(5)->get(),
            'orders' => Order::query()->where('source', 'shop')->latest()->take(5)->get(),
        ]);
    }
}
