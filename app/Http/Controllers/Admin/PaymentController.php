<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function dashboard()
    {
        return view('admin.payments.dashboard', [
            'cards' => [
                'Received today' => [
                    'value' => Payment::query()->whereIn('status', ['paid', 'succeeded'])->whereDate('paid_at', today())->sum('amount'),
                    'money' => true,
                    'route' => route('admin.payments.index'),
                ],
                'Received this month' => [
                    'value' => Payment::query()->whereIn('status', ['paid', 'succeeded'])->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
                    'money' => true,
                    'route' => route('admin.payments.index'),
                ],
                'Pending verification' => [
                    'value' => Payment::query()->where('status', 'pending_verification')->count(),
                    'route' => route('admin.payments.pending-verification'),
                ],
                'Failed payments' => [
                    'value' => Payment::query()->where('status', 'failed')->count(),
                    'route' => route('admin.payments.index', ['status' => 'failed']),
                ],
                'Outstanding invoices' => [
                    'value' => Invoice::query()->where('balance_due', '>', 0)->count(),
                    'route' => route('admin.invoices.index'),
                ],
                'Refund requests' => [
                    'value' => PaymentRefund::query()->whereIn('status', ['pending', 'approved', 'processing'])->count(),
                    'route' => route('admin.refunds.index'),
                ],
                'Webhook failures' => [
                    'value' => PaymentWebhookEvent::query()->where('status', 'failed')->count(),
                    'route' => route('admin.payment-webhooks.index'),
                ],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $routeSource = $request->route('source');
        $source = $routeSource ?: ($request->filled('source') ? $request->string('source')->toString() : null);

        abort_if($source && ! array_key_exists($source, Payment::SOURCES), 404);

        $payments = Payment::query()
            ->with(['customer', 'invoice', 'payable.customer', 'receiver', 'verifier'])
            ->when($source, fn ($query) => $query->where('source', $source))
            ->when($request->filled('gateway'), fn ($query) => $query->where('gateway', $request->string('gateway')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->string('method')))
            ->when($request->filled('provider'), fn ($query) => $query->where('provider', $request->string('provider')))
            ->when($request->filled('purpose'), fn ($query) => $query->where('purpose', $request->string('purpose')))
            ->when($request->filled('payment_number'), fn ($query) => $query->where('payment_number', 'like', '%'.$request->string('payment_number').'%'))
            ->when($request->filled('invoice_number'), function ($query) use ($request): void {
                $query->whereHas('invoice', fn ($query) => $query->where('invoice_number', 'like', '%'.$request->string('invoice_number').'%'));
            })
            ->when($request->filled('customer'), function ($query) use ($request): void {
                $customer = $request->string('customer');
                $query->whereHas('customer', fn ($query) => $query->where('full_name', 'like', "%{$customer}%")->orWhere('email', 'like', "%{$customer}%"));
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('min_amount'), fn ($query) => $query->where('amount', '>=', $request->input('min_amount')))
            ->when($request->filled('max_amount'), fn ($query) => $query->where('amount', '<=', $request->input('max_amount')))
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
            'payment' => $payment->load(['customer', 'invoice', 'payable.customer', 'transactions']),
        ]);
    }

    public function proof(Payment $payment)
    {
        abort_unless(filled($payment->proof_path) && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download(
            $payment->proof_path,
            $payment->proof_original_name ?: basename($payment->proof_path),
        );
    }
}
