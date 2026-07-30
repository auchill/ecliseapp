<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load('items', 'payments.transactions', 'payments.refunds', 'customer', 'invoiceable'),
        ]);
    }

    public function print(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        return view('invoices.print', [
            'invoice' => $invoice->load('items', 'payments.transactions', 'payments.refunds', 'customer', 'invoiceable'),
        ]);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->isAdmin()
            || ($user?->isCustomer() && $invoice->customer?->user_id === $user->id),
            403,
        );
    }
}
