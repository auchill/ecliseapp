<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Collection;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentBalanceService $balances,
        private readonly StripeApiClient $stripe,
    ) {}

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
            'provider_checks' => $this->providerChecks($payments, $filters),
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

        if ($payment->invoice && $this->balances->invoiceNetPaidAmount($payment->invoice) > (float) $payment->invoice->total + 0.01) {
            $issues->push($this->issue($payment, 'invoice_overpaid', 'Settled payments exceed the invoice total; a refund may be required.'));
        }

        if (abs($this->balances->paymentRefundedAmount($payment) - (float) $payment->refunded_amount) > 0.01) {
            $issues->push($this->issue($payment, 'refund_total_mismatch', 'Payment refunded_amount does not match the refund ledger.'));
        }

        if ($payment->provider === 'stripe' && $payment->hasSettledFunds() && filled($payment->stripe_payment_intent_id)) {
            $duplicates = Payment::query()
                ->where('stripe_payment_intent_id', $payment->stripe_payment_intent_id)
                ->whereKeyNot($payment->id)
                ->count();

            if ($duplicates > 0) {
                $issues->push($this->issue($payment, 'duplicate_payment_intent', 'More than one local payment references this Stripe PaymentIntent.'));
            }
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

    private function providerChecks(?Collection $payments = null, array $filters = []): array
    {
        $stripeSecretConfigured = filled(config('services.stripe.secret'));
        $stripeCheck = [
            'available' => $stripeSecretConfigured,
            'status' => $stripeSecretConfigured ? 'not_run' : 'unavailable',
            'reason' => $stripeSecretConfigured ? 'Run with --provider=stripe to compare local Stripe payments with Stripe PaymentIntents.' : 'Stripe secret is not configured.',
        ];

        if (($filters['provider'] ?? null) === 'stripe' && $stripeSecretConfigured) {
            $stripeCheck = $this->stripeRemoteChecks($payments ?? collect());
        }

        return [
            'stripe' => $stripeCheck,
            'paypal' => [
                'available' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')),
                'status' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')) ? 'not_run' : 'unavailable',
                'reason' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')) ? 'PayPal reconciliation is deferred outside Stripe Stage 3.' : 'PayPal client credentials are not configured.',
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

    /**
     * Read-only comparison against Stripe. This never writes: reconciliation reports, it does not correct.
     */
    private function stripeRemoteChecks(Collection $payments): array
    {
        $discrepancies = collect();
        $errors = collect();
        $checked = 0;

        $stripePayments = $payments->where('provider', 'stripe');

        $stripePayments
            ->filter(fn (Payment $payment): bool => blank($payment->stripe_payment_intent_id ?: $payment->gateway_payment_id))
            ->filter(fn (Payment $payment): bool => $payment->hasSettledFunds())
            ->each(function (Payment $payment) use ($discrepancies): void {
                $discrepancies->push([
                    'payment' => $payment->payment_number ?: '#'.$payment->id,
                    'provider_reference' => null,
                    'code' => 'missing_provider_reference',
                    'message' => 'Local payment is settled but carries no Stripe PaymentIntent to verify against.',
                ]);
            });

        $stripePayments
            ->filter(fn (Payment $payment): bool => filled($payment->stripe_payment_intent_id ?: $payment->gateway_payment_id))
            ->each(function (Payment $payment) use (&$checked, $discrepancies, $errors): void {
                $checked++;
                $paymentIntentId = $payment->stripe_payment_intent_id ?: $payment->gateway_payment_id;
                $response = $this->stripe->request()
                    ->acceptJson()
                    ->get($this->stripe->url('payment_intents/'.$paymentIntentId));

                if ($response->failed()) {
                    $errors->push([
                        'payment' => $payment->payment_number ?: '#'.$payment->id,
                        'provider_reference' => $paymentIntentId,
                        'code' => $response->status() === 404 ? 'unknown_provider_reference' : 'provider_lookup_failed',
                        'message' => data_get($response->json(), 'error.message') ?: 'Stripe PaymentIntent lookup failed.',
                    ]);

                    return;
                }

                $payload = $response->json();
                $remoteAmount = (int) ($payload['amount_received'] ?? $payload['amount'] ?? 0);
                $localAmount = Money::toMinorUnits($payment->amount);
                $remoteCurrency = strtolower((string) ($payload['currency'] ?? ''));
                $localCurrency = strtolower((string) $payment->currency);
                $remoteStatus = (string) ($payload['status'] ?? '');
                $remoteSettled = $remoteStatus === 'succeeded';
                $localSettled = $payment->hasSettledFunds();

                $base = [
                    'payment' => $payment->payment_number ?: '#'.$payment->id,
                    'provider_reference' => $paymentIntentId,
                    'local_status' => $payment->status,
                    'remote_status' => $remoteStatus,
                    'local_amount_minor' => $localAmount,
                    'remote_amount_minor' => $remoteAmount,
                    'local_currency' => $localCurrency,
                    'remote_currency' => $remoteCurrency,
                ];

                if ($remoteAmount !== $localAmount) {
                    $discrepancies->push($base + [
                        'code' => 'amount_mismatch',
                        'message' => 'Stripe PaymentIntent amount differs from the local payment.',
                    ]);
                }

                if ($remoteCurrency !== '' && $remoteCurrency !== $localCurrency) {
                    $discrepancies->push($base + [
                        'code' => 'currency_mismatch',
                        'message' => 'Stripe PaymentIntent currency differs from the local payment.',
                    ]);
                }

                if ($remoteSettled && ! $localSettled) {
                    $discrepancies->push($base + [
                        'code' => 'provider_paid_local_unpaid',
                        'message' => 'Stripe reports a succeeded PaymentIntent while the local payment is not settled.',
                    ]);
                }

                if ($localSettled && ! $remoteSettled) {
                    $discrepancies->push($base + [
                        'code' => 'local_paid_provider_unpaid',
                        'message' => 'The local payment is settled while Stripe does not report a succeeded PaymentIntent.',
                    ]);
                }

                $remoteRefunded = Money::fromMinorUnits((int) ($payload['amount_refunded'] ?? data_get($payload, 'charges.data.0.amount_refunded') ?? 0));
                $localRefunded = $this->balances->paymentRefundedAmount($payment);

                if (abs($remoteRefunded - $localRefunded) > 0.01) {
                    $discrepancies->push($base + [
                        'code' => 'refund_mismatch',
                        'local_refunded' => $localRefunded,
                        'remote_refunded' => $remoteRefunded,
                        'message' => 'Stripe refunded total differs from the local refund ledger.',
                    ]);
                }
            });

        return [
            'available' => true,
            'status' => $errors->isEmpty() ? 'completed' : 'completed_with_errors',
            'records_checked' => $checked,
            'discrepancy_count' => $discrepancies->count(),
            'discrepancies' => $discrepancies->values()->all(),
            'errors' => $errors->values()->all(),
        ];
    }
}
