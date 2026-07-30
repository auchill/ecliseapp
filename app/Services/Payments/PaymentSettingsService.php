<?php

namespace App\Services\Payments;

use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PaymentSettingsService
{
    public const DEFAULTS = [
        'default_currency' => 'CAD',
        'stripe_enabled' => true,
        'paypal_enabled' => true,
        'interac_enabled' => true,
        'cash_enabled' => true,
        'debit_terminal_enabled' => true,
        'credit_terminal_enabled' => true,
        'pay_in_store_enabled' => true,
        'repair_partial_payments_enabled' => true,
        'shop_partial_payments_enabled' => false,
        'repair_deposit_type' => 'full_payment',
        'repair_deposit_fixed_amount' => 0.00,
        'repair_deposit_percentage' => 40.00,
        'repair_minimum_deposit' => 0.00,
        'require_repair_deposit_before_work' => true,
        'require_full_payment_before_repair_pickup' => true,
        'require_full_payment_before_repair_delivery' => true,
        'require_full_payment_before_shop_shipping' => true,
        'allow_pay_in_store_for_pickup' => true,
        'refund_approval_required' => true,
        'refund_approval_threshold' => 0.00,
        'automatic_receipt_email' => true,
        'automatic_invoice_email' => false,
        'interac_recipient_name' => '',
        'interac_recipient_email' => '',
        'interac_instructions' => 'Use the invoice number as the transfer message.',
        'invoice_due_days' => 14,
        'invoice_terms' => 'Payment is due by the invoice due date.',
    ];

    public function all(): array
    {
        if (! Schema::hasTable('payment_settings')) {
            return self::DEFAULTS;
        }

        $settings = PaymentSetting::query()->get()->keyBy('key');

        return collect(self::DEFAULTS)
            ->mapWithKeys(fn (mixed $default, string $key): array => [
                $key => $this->cast($settings->get($key)?->value ?? $default, $this->typeFor($key)),
            ])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function enabled(string $method): bool
    {
        return (bool) $this->get($method.'_enabled', false);
    }

    public function update(array $data, ?User $actor = null): void
    {
        $data = $this->validated($data);

        foreach ($data as $key => $value) {
            PaymentSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'type' => $this->typeFor($key),
                    'is_secret' => false,
                    'updated_by' => $actor?->id,
                ],
            );
        }
    }

    public function repairDepositAmount(float $total): float
    {
        $type = (string) $this->get('repair_deposit_type', 'full_payment');

        $amount = match ($type) {
            'none' => 0.0,
            'fixed' => (float) $this->get('repair_deposit_fixed_amount', 0),
            'percentage' => round($total * ((float) $this->get('repair_deposit_percentage', 0) / 100), 2),
            'minimum' => max((float) $this->get('repair_minimum_deposit', 0), round($total * ((float) $this->get('repair_deposit_percentage', 0) / 100), 2)),
            'full_payment' => $total,
            default => $total,
        };

        return round(min(max(0, $amount), max(0, $total)), 2);
    }

    public function paymentMethodOptions(bool $customerFacing = false): array
    {
        $methods = [
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'interac' => 'Interac e-Transfer',
            'cash' => 'Cash',
            'debit_terminal' => 'Debit terminal',
            'credit_terminal' => 'Credit terminal',
            'pay_in_store' => 'Pay in store',
        ];

        if ($customerFacing) {
            unset($methods['cash'], $methods['debit_terminal'], $methods['credit_terminal']);
        }

        return collect($methods)
            ->filter(fn (string $label, string $method): bool => $this->enabled($method))
            ->all();
    }

    private function validated(array $data): array
    {
        $allowed = array_keys(self::DEFAULTS);
        $data = array_intersect_key($data, array_flip($allowed));

        if (isset($data['repair_deposit_type']) && ! in_array($data['repair_deposit_type'], ['none', 'fixed', 'percentage', 'minimum', 'full_payment'], true)) {
            throw new InvalidArgumentException('Invalid repair deposit type.');
        }

        foreach ([
            'repair_deposit_fixed_amount',
            'repair_deposit_percentage',
            'repair_minimum_deposit',
            'refund_approval_threshold',
        ] as $moneyKey) {
            if (array_key_exists($moneyKey, $data) && (float) $data[$moneyKey] < 0) {
                throw new InvalidArgumentException('Payment monetary settings cannot be negative.');
            }
        }

        if (array_key_exists('invoice_due_days', $data) && (int) $data['invoice_due_days'] < 0) {
            throw new InvalidArgumentException('Invoice due days cannot be negative.');
        }

        return $data;
    }

    private function typeFor(string $key): string
    {
        $default = self::DEFAULTS[$key] ?? '';

        return match (true) {
            is_bool($default) => 'boolean',
            is_int($default) => 'integer',
            is_float($default) => 'decimal',
            str_contains($key, 'instructions') || str_contains($key, 'terms') => 'text',
            default => 'string',
        };
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'decimal' => round((float) $value, 2),
            default => $value,
        };
    }
}
