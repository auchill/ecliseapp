<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\ManualPaymentService;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ManualPaymentController extends Controller
{
    public function create(Request $request, PaymentSettingsService $settings)
    {
        $invoice = $request->integer('invoice')
            ? Invoice::query()->with('customer', 'invoiceable')->find($request->integer('invoice'))
            : null;

        return view('admin.payments.manual', [
            'invoice' => $invoice,
            'invoices' => Invoice::query()->with('customer')->where('balance_due', '>', 0)->latest()->limit(100)->get(),
            'methods' => $settings->paymentMethodOptions(),
        ]);
    }

    public function store(Request $request, ManualPaymentService $manualPayments, PaymentSettingsService $settings)
    {
        $methodKeys = array_keys($settings->paymentMethodOptions());
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'purpose' => ['nullable', 'string', 'max:80'],
            'method' => ['required', Rule::in($methodKeys)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_date' => ['nullable', 'date'],
            'manual_reference' => ['nullable', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,heic,webp', 'max:5120'],
        ]);

        try {
            $payment = $manualPayments->record(
                Invoice::query()->findOrFail($data['invoice_id']),
                $request->user(),
                $data,
                $request->file('proof'),
                $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.payments.show', $payment)->with('status', 'Payment recorded.');
    }
}
