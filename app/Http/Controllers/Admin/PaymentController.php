<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::query()
            ->with('payable')
            ->when($request->filled('gateway'), fn ($query) => $query->where('gateway', $request->string('gateway')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'gateways' => Payment::GATEWAYS,
            'statuses' => Payment::STATUSES,
        ]);
    }

    public function show(Payment $payment)
    {
        return view('admin.payments.show', [
            'payment' => $payment->load('payable'),
        ]);
    }
}
