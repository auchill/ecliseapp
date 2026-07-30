<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\InvoiceService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()
            ->with('customer', 'invoiceable')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($query) use ($search): void {
                        $query->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.invoices.index', ['invoices' => $invoices]);
    }

    public function show(Invoice $invoice)
    {
        return view('admin.invoices.show', [
            'invoice' => $invoice->load('items', 'payments.transactions', 'payments.refunds', 'customer', 'invoiceable'),
        ]);
    }

    public function print(Invoice $invoice)
    {
        return view('invoices.print', [
            'invoice' => $invoice->load('items', 'payments.transactions', 'payments.refunds', 'customer', 'invoiceable'),
        ]);
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $invoices)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $invoices->cancel($invoice, $data['reason'] ?? '');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return redirect()->route('admin.invoices.show', $invoice)->with('status', 'Invoice cancelled.');
    }
}
