<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payments\RefundService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RefundController extends Controller
{
    public function index()
    {
        return view('admin.refunds.index', [
            'refunds' => PaymentRefund::query()->with('payment.customer', 'requester', 'approver', 'processor')->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, Payment $payment, RefundService $refunds)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason_code' => ['nullable', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:1000'],
            'requested_method' => ['nullable', 'string', 'max:80'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $refund = $refunds->request($payment, $request->user(), $data, $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return redirect()->route('admin.refunds.index')->with('status', 'Refund requested: '.$refund->refund_number);
    }

    public function approve(Request $request, PaymentRefund $refund, RefundService $refunds)
    {
        try {
            $refunds->approve($refund, $request->user(), $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Refund approved.');
    }

    public function process(Request $request, PaymentRefund $refund, RefundService $refunds)
    {
        $data = $request->validate([
            'processed_method' => ['nullable', 'string', 'max:80'],
            'manual_reference' => ['nullable', 'string', 'max:255'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $refunds->process($refund, $request->user(), $data, $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Refund processed.');
    }
}
