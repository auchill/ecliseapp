<?php

namespace App\Services;

class AddressSnapshotFormatter
{
    public function format(array $data): string
    {
        return collect([
            $data['shipping_full_name'] ?? $data['full_name'] ?? $data['customer_name'] ?? null,
            $data['shipping_address_line1'] ?? $data['address_line1'] ?? null,
            $data['shipping_address_line2'] ?? $data['address_line2'] ?? null,
            trim(implode(', ', array_filter([
                $data['shipping_city'] ?? $data['city'] ?? null,
                trim(implode(' ', array_filter([
                    $data['shipping_province'] ?? $data['province'] ?? null,
                    $data['shipping_postal_code'] ?? $data['postal_code'] ?? null,
                ]))),
            ]))),
            $data['shipping_country'] ?? $data['country'] ?? null,
            filled($data['shipping_email'] ?? $data['email'] ?? null) ? 'Email: '.($data['shipping_email'] ?? $data['email']) : null,
            filled($data['shipping_phone'] ?? $data['phone'] ?? null) ? 'Phone: '.($data['shipping_phone'] ?? $data['phone']) : null,
        ])
            ->filter(fn ($line): bool => filled($line))
            ->implode("\n");
    }
}
