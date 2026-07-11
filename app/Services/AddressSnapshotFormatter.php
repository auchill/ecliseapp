<?php

namespace App\Services;

class AddressSnapshotFormatter
{
    public function format(array $data): string
    {
        return collect([
            $data['recipient_name'] ?? $data['full_name'] ?? null,
            $data['address_line1'] ?? null,
            $data['address_line2'] ?? null,
            trim(implode(', ', array_filter([
                $data['city'] ?? null,
                trim(implode(' ', array_filter([
                    $data['province'] ?? null,
                    $data['postal_code'] ?? null,
                ]))),
            ]))),
            $data['country'] ?? null,
            filled($data['recipient_email'] ?? $data['email'] ?? null) ? 'Email: '.($data['recipient_email'] ?? $data['email']) : null,
            filled($data['recipient_phone'] ?? $data['phone'] ?? null) ? 'Phone: '.($data['recipient_phone'] ?? $data['phone']) : null,
        ])
            ->filter(fn ($line): bool => filled($line))
            ->implode("\n");
    }
}
