<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentFinalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManualPaymentService
{
    public function __construct(
        private readonly PaymentSettingsService $settings,
        private readonly PaymentBalanceService $balances,
        private readonly PaymentFinalizer $finalizer,
        private readonly PaymentAuditLogger $audit,
    ) {}

    public function record(Invoice $invoice, User $actor, array $data, ?UploadedFile $proof = null, ?string $sourceIp = null): Payment
    {
        return DB::transaction(function () use ($invoice, $actor, $data, $proof, $sourceIp): Payment {
            $invoice = Invoice::query()->with('invoiceable')->lockForUpdate()->findOrFail($invoice->id);
            $this->assertPayable($invoice);

            $method = (string) ($data['method'] ?? '');
            $amount = round((float) ($data['amount'] ?? 0), 2);

            $this->validateManualPayment($invoice, $method, $amount, $data);

            $proofData = $proof ? $this->storeProof($proof) : [];
            $isInterac = $method === 'interac';
            $checkoutData = $this->checkoutDataForInvoice($invoice);

            $payment = $invoice->invoiceable->payments()->create(array_merge([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'source' => $this->sourceForInvoice($invoice),
                'purpose' => $data['purpose'] ?? $this->purposeForInvoice($invoice),
                'gateway' => $method,
                'method' => $method,
                'provider' => in_array($method, ['debit_terminal', 'credit_terminal'], true) ? 'terminal' : 'manual',
                'amount' => $amount,
                'currency' => strtolower($invoice->currency),
                'status' => $isInterac ? PaymentStatus::PendingVerification->value : PaymentStatus::Pending->value,
                'manual_reference' => $data['manual_reference'] ?? null,
                'gateway_reference_id' => $data['manual_reference'] ?? null,
                'gateway_reference' => $data['manual_reference'] ?? null,
                'received_by' => $isInterac ? null : $actor->id,
                'created_by' => $actor->id,
                'source_ip' => $sourceIp,
                'submitted_at' => $isInterac ? now() : null,
                'paid_at' => null,
                'customer_note' => $data['customer_note'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'metadata' => [
                    'recorded_by' => $actor->id,
                    'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                ],
                'checkout_data' => $checkoutData,
            ], $proofData));

            if ($isInterac) {
                $payment->transactions()->create([
                    'transaction_type' => PaymentTransactionType::ManualConfirmation->value,
                    'status' => PaymentStatus::PendingVerification->value,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'provider_reference' => $payment->manual_reference,
                    'processed_at' => now(),
                ]);

                $this->audit->log('payment.interac.submitted', $payment, $actor, [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                ], $sourceIp);

                return $payment->fresh('invoice', 'transactions');
            }

            $payment = $this->finalizer->markPaid($payment, [
                'paid_at' => $data['payment_date'] ?? now(),
                'received_by' => $actor->id,
                'gateway_reference_id' => $payment->manual_reference,
                'gateway_reference' => $payment->manual_reference,
                'raw_response' => ['manual_payment' => true, 'method' => $method],
            ]);
            $payment->transactions()->latest('id')->first()?->update([
                'transaction_type' => PaymentTransactionType::ManualConfirmation->value,
            ]);

            $this->balances->synchronizeInvoice($invoice->fresh());
            $this->audit->log('payment.manual.recorded', $payment, $actor, [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $method,
            ], $sourceIp);

            return $payment->fresh('invoice', 'transactions');
        });
    }

    public function verifyInterac(Payment $payment, User $actor, array $data = [], ?string $sourceIp = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $data, $sourceIp): Payment {
            $payment = Payment::query()->with('invoice')->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::PendingVerification->value || $payment->method !== 'interac') {
                throw new InvalidArgumentException('Only pending Interac payments can be verified.');
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            if ((float) $payment->amount > $this->balances->invoiceBalanceDue($invoice) + 0.01) {
                throw new InvalidArgumentException('Payment amount exceeds the current invoice balance.');
            }

            $payment = $this->finalizer->markPaid($payment, [
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'received_by' => $actor->id,
                'paid_at' => now(),
                'gateway_reference_id' => $data['manual_reference'] ?? $payment->manual_reference,
                'gateway_reference' => $data['manual_reference'] ?? $payment->manual_reference,
                'admin_note' => $data['admin_note'] ?? $payment->admin_note,
                'raw_response' => ['interac_verified' => true],
            ]);
            $payment->transactions()->latest('id')->first()?->update([
                'transaction_type' => PaymentTransactionType::ManualConfirmation->value,
            ]);

            $this->balances->synchronizeInvoice($invoice->fresh());
            $this->audit->log('payment.interac.verified', $payment, $actor, [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ], $sourceIp);

            return $payment->fresh('invoice', 'transactions');
        });
    }

    public function rejectInterac(Payment $payment, User $actor, string $reason, ?string $sourceIp = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason, $sourceIp): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::PendingVerification->value || $payment->method !== 'interac') {
                throw new InvalidArgumentException('Only pending Interac payments can be rejected.');
            }

            $payment->forceFill([
                'status' => PaymentStatus::Failed->value,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'failure_message' => $reason,
                'failed_at' => now(),
            ])->save();

            $payment->transactions()->create([
                'transaction_type' => PaymentTransactionType::ManualRejection->value,
                'status' => PaymentStatus::Failed->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider_reference' => $payment->manual_reference,
                'failure_message' => $reason,
                'processed_at' => now(),
            ]);

            $this->audit->log('payment.interac.rejected', $payment, $actor, [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'reason' => $reason,
            ], $sourceIp);

            return $payment->fresh('invoice', 'transactions');
        });
    }

    private function validateManualPayment(Invoice $invoice, string $method, float $amount, array $data): void
    {
        if (! array_key_exists($method, $this->settings->paymentMethodOptions())) {
            throw new InvalidArgumentException('The selected payment method is not enabled.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if (strtolower((string) ($data['currency'] ?? $invoice->currency)) !== strtolower($invoice->currency)) {
            throw new InvalidArgumentException('Payment currency must match the invoice.');
        }

        if ($amount > $this->balances->invoiceBalanceDue($invoice) + 0.01) {
            throw new InvalidArgumentException('Payment amount cannot exceed the invoice balance.');
        }

        if (filled($data['manual_reference'] ?? null)) {
            $duplicate = Payment::query()
                ->where('method', $method)
                ->where('manual_reference', $data['manual_reference'])
                ->whereNotIn('status', [PaymentStatus::Failed->value, PaymentStatus::Cancelled->value])
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('A payment with this reference already exists.');
            }
        }
    }

    private function assertPayable(Invoice $invoice): void
    {
        if (! $invoice->invoiceable || $this->balances->invoiceBalanceDue($invoice) <= 0.01) {
            throw new InvalidArgumentException('This invoice is not payable.');
        }
    }

    private function storeProof(UploadedFile $proof): array
    {
        $name = Str::uuid()->toString().'.'.$proof->guessExtension();
        $path = $proof->storeAs('payment-proofs', $name, ['disk' => 'local']);

        return [
            'proof_path' => $path,
            'proof_original_name' => $proof->getClientOriginalName(),
            'proof_mime_type' => $proof->getMimeType(),
            'proof_size' => $proof->getSize(),
        ];
    }

    private function sourceForInvoice(Invoice $invoice): string
    {
        return $invoice->type === 'shop_order' ? 'shop' : 'repair';
    }

    private function purposeForInvoice(Invoice $invoice): string
    {
        return match ($invoice->type) {
            'shop_order' => 'shop_order',
            'repair_deposit' => 'deposit',
            'repair_additional_charge' => 'additional_charge',
            default => 'balance',
        };
    }

    private function checkoutDataForInvoice(Invoice $invoice): ?array
    {
        if (! $invoice->invoiceable instanceof Cart) {
            return null;
        }

        return $invoice->payments()
            ->whereNotNull('checkout_data')
            ->latest('id')
            ->first()
            ?->checkout_data;
    }
}
