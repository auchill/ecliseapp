<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Collection;

class PaymentReconciliationService
{
    public function __construct(private readonly PaymentBalanceService $balances) {}

    public function report(array $filters = []): array
    {
        $query = Payment::query()->with('invoice', 'transactions', 'refunds');

        $query->when($filters['provider'] ?? null, fn ($query, $provider) => $query->where('provider', $provider));
        $query->when($filters['payment'] ?? null, fn ($query, $payment) => $query->where('payment_number', $payment));
        $query->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from));
        $query->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to));

        $payments = $query->orderByDesc('id')->get();
        $issues = $payments->flatMap(fn (Payment $payment): Collection => $this->issuesFor($payment));
        $warnings = $this->providerWarnings();

        return [
            'status' => 'local_checks_complete',
            'checked_at' => now()->toIso8601String(),
            'filters' => $filters,
            'records_checked' => $payments->count(),
            'issue_count' => $issues->count(),
            'local_discrepancy_count' => $issues->count(),
            'issues' => $issues->values()->all(),
            'local_discrepancies' => $issues->values()->all(),
            'provider_checks' => $this->providerChecks(),
            'records_skipped' => 0,
            'errors' => [],
            'warnings' => $warnings,
            'repairs_proposed' => [],
            'repair_actions_available' => false,
        ];
    }

    private function issuesFor(Payment $payment): Collection
    {
        $issues = collect();

        if ($payment->hasSettledFunds() && ! $payment->paid_at) {
            $issues->push($this->issue($payment, 'missing_paid_at', 'Successful payment is missing paid_at.'));
        }

        if ($payment->hasSettledFunds() && $payment->transactions->isEmpty()) {
            $issues->push($this->issue($payment, 'missing_transaction', 'Successful payment has no transaction ledger row.'));
        }

        if ($payment->hasSettledFunds() && blank($payment->receipt_number)) {
            $issues->push($this->issue($payment, 'missing_receipt', 'Successful payment has no receipt number.'));
        }

        if (in_array($payment->provider, ['stripe', 'paypal'], true) && $payment->hasSettledFunds() && blank($payment->gateway_payment_id ?: $payment->gateway_reference_id)) {
            $issues->push($this->issue($payment, 'missing_gateway_reference', 'Gateway payment is missing a provider reference.'));
        }

        if (in_array($payment->provider, ['manual', 'terminal'], true) && $payment->hasSettledFunds() && ! $payment->received_by) {
            $issues->push($this->issue($payment, 'manual_missing_receiver', 'Manual payment is missing the receiving staff member.'));
        }

        if ($payment->invoice && abs($this->balances->invoiceBalanceDue($payment->invoice) - (float) $payment->invoice->balance_due) > 0.01) {
            $issues->push($this->issue($payment, 'invoice_balance_mismatch', 'Invoice balance is out of sync.'));
        }

        return $issues;
    }

    private function issue(Payment $payment, string $code, string $message): array
    {
        return [
            'payment' => $payment->payment_number ?: '#'.$payment->id,
            'invoice' => $payment->invoice?->invoice_number,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'code' => $code,
            'message' => $message,
            'recommended_action' => 'Review and correct through the payment module workflow.',
        ];
    }

    private function providerChecks(): array
    {
        return [
            'stripe' => [
                'available' => filled(config('services.stripe.secret')),
                'status' => filled(config('services.stripe.secret')) ? 'not_run' : 'unavailable',
                'reason' => filled(config('services.stripe.secret')) ? 'Remote Stripe reconciliation is not implemented in this local dry-run.' : 'Stripe secret is not configured.',
            ],
            'paypal' => [
                'available' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')),
                'status' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')) ? 'not_run' : 'unavailable',
                'reason' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')) ? 'Remote PayPal reconciliation is not implemented in this local dry-run.' : 'PayPal client credentials are not configured.',
            ],
        ];
    }

    private function providerWarnings(): array
    {
        return collect($this->providerChecks())
            ->filter(fn (array $check): bool => $check['status'] === 'unavailable')
            ->map(fn (array $check, string $provider): string => ucfirst($provider).' remote verification unavailable: '.$check['reason'])
            ->values()
            ->all();
    }
}
