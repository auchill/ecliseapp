<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $routeSource = $request->route('source');
        $source = $routeSource ?: ($request->filled('source') ? $request->string('source')->toString() : null);

        abort_if($source && ! array_key_exists($source, Payment::SOURCES), 404);

        $payments = Payment::query()
            ->with('payable')
            ->when($source, fn ($query) => $query->where('source', $source))
            ->when($request->filled('gateway'), fn ($query) => $query->where('gateway', $request->string('gateway')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $actionRoute = match ($source) {
            'repair' => route('admin.repair-payments.index'),
            'shop' => route('admin.shop-payments.index'),
            default => route('admin.payments.index'),
        };

        return view('admin.payments.index', [
            'payments' => $payments,
            'gateways' => Payment::GATEWAYS,
            'statuses' => Payment::STATUSES,
            'sources' => Payment::SOURCES,
            'source' => $source,
            'actionRoute' => $actionRoute,
        ]);
    }

    public function show(Payment $payment)
    {
        return view('admin.payments.show', [
            'payment' => $payment->load('payable'),
        ]);
    }
}
