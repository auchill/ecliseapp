<?php

namespace Database\Seeders;

use App\Models\PaymentSetting;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Database\Seeder;

/**
 * Restores payment settings to the documented defaults.
 *
 * These were originally written by the Stage 2 migration, so they must be reseeded whenever the
 * payment_settings table is cleared. Values are read from PaymentSettingsService::DEFAULTS so
 * this can never drift from the service's own expectations.
 */
class PaymentSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PaymentSettingsService::DEFAULTS as $key => $default) {
            PaymentSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($default) ? ($default ? '1' : '0') : (string) $default,
                    'type' => match (true) {
                        is_bool($default) => 'boolean',
                        is_int($default) => 'integer',
                        is_float($default) => 'decimal',
                        str_contains($key, 'instructions') || str_contains($key, 'terms') => 'text',
                        default => 'string',
                    },
                    'is_secret' => false,
                ],
            );
        }

        // Interac needs real recipient details before customers can be told where to send funds.
        PaymentSetting::query()->where('key', 'interac_recipient_name')->update(['value' => 'Eclise Technology Inc.']);
        PaymentSetting::query()->where('key', 'interac_recipient_email')->update(['value' => 'payments@eclisetech.com']);
    }
}
