<?php

namespace App\Models;

use Illuminate\Support\Str;

final class PartWarranty
{
    public const WARRANTY_LABELS = [
        '7627' => 'No Warranty',
        '7630' => '30 Days',
        '7633' => '60 Days',
        '7636' => '90 Days',
        '7642' => '6 Months',
        '7648' => '1 Year',
        '7645' => 'Lifetime Warranty',
        '5941' => '1 Year',
    ];

    public static function displayLabel(mixed $externalId, mixed $label = null): ?string
    {
        if (filled($label)) {
            return trim((string) $label);
        }

        return self::WARRANTY_LABELS[(string) $externalId] ?? (filled($externalId) ? trim((string) $externalId) : null);
    }

    public static function displayIconUrl(mixed $externalId, mixed $label = null, ?string $apiUrl = null): ?string
    {
        $label = Str::lower((string) self::displayLabel($externalId, $label));
        $filename = match (true) {
            str_contains($label, 'lifetime') => 'warranty-lifetime.svg',
            str_contains($label, '1 year') => 'warranty-1-year.svg',
            str_contains($label, '6 month') => 'warranty-6-month.svg',
            str_contains($label, '90') => 'warranty-90-day.svg',
            str_contains($label, '60') => 'warranty-60-day.svg',
            str_contains($label, '30') => 'warranty-30-day.svg',
            str_contains($label, 'no warranty') => 'warranty-none.svg',
            default => 'warranty-default.svg',
        };

        $path = 'images/parts/warranties/'.$filename;

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return filled($apiUrl) ? $apiUrl : null;
    }
}
