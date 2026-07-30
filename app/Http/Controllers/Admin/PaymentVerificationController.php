<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentVerificationController extends Controller
{
    public function index()
    {
        return view('admin.payments.pending-verification', [
            'payments' => Payment::query()
                ->with('invoice', 'customer', 'payable')
                ->where('status', 'pending_verification')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function verify(Request $request, Payment $payment, ManualPaymentService $manualPayments)
    {
        $data = $request->validate([
            'manual_reference' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payment = $manualPayments->verifyInterac($payment, $request->user(), $data, $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->route('admin.payments.show', $payment)->with('status', 'Interac payment verified.');
    }

    public function reject(Request $request, Payment $payment, ManualPaymentService $manualPayments)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $manualPayments->rejectInterac($payment, $request->user(), $data['reason'], $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->route('admin.payments.pending-verification')->with('status', 'Interac payment rejected.');
    }
}
