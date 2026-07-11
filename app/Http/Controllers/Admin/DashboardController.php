<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\MobileSentrixDevice;
use App\Models\Order;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Repair;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'metrics' => [
                'Total quotes' => ['value' => Quote::query()->count(), 'route' => route('admin.quotes.index'), 'icon' => 'bi-chat-square-text'],
                'Pending quotes' => ['value' => Quote::query()->where('status', 'pending')->count(), 'route' => route('admin.quotes.index', ['status' => 'pending']), 'icon' => 'bi-hourglass-split'],
                'Approved quotes' => ['value' => Quote::query()->where('status', 'approved')->count(), 'route' => route('admin.quotes.index', ['status' => 'approved']), 'icon' => 'bi-check2-circle'],
                'Repairs' => ['value' => Repair::query()->count(), 'route' => route('admin.repairs.index'), 'icon' => 'bi-tools'],
                'Awaiting payment repairs' => ['value' => Repair::query()->whereIn('payment_status', ['unpaid', 'pending', 'partially_paid'])->count(), 'route' => route('admin.repairs.index'), 'icon' => 'bi-wallet2'],
                'Shop orders' => ['value' => Order::query()->count(), 'route' => route('admin.orders.index'), 'icon' => 'bi-receipt'],
                'Shop payments' => ['value' => Payment::query()->where('source', 'shop')->count(), 'route' => route('admin.shop-payments.index'), 'icon' => 'bi-credit-card'],
                'Repair payments' => ['value' => Payment::query()->where('source', 'repair')->count(), 'route' => route('admin.repair-payments.index'), 'icon' => 'bi-cash-stack'],
                'Customers' => ['value' => Customer::query()->count(), 'route' => route('admin.customers.index'), 'icon' => 'bi-people'],
                'Messages' => ['value' => ContactMessage::query()->whereNull('read_at')->count(), 'route' => route('admin.contact-messages.index'), 'icon' => 'bi-envelope'],
                'Products' => ['value' => Product::query()->count(), 'route' => route('admin.products.index'), 'icon' => 'bi-phone'],
                'Pre-owned devices' => ['value' => MobileSentrixDevice::query()->count(), 'route' => route('admin.devices.index'), 'icon' => 'bi-phone-fill'],
                'Listed parts' => ['value' => Part::query()->count(), 'route' => route('admin.parts.index'), 'icon' => 'bi-cpu'],
            ],
            'repairs' => Repair::query()->with('customer')->latest()->take(5)->get(),
            'orders' => Order::query()->with('customer')->latest()->take(5)->get(),
        ]);
    }
}
