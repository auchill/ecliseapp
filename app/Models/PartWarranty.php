<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PartWarranty extends Model
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

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function getDisplayIconUrlAttribute(): ?string
    {
        if ($this->icon_url ?: $this->photo_url ?: $this->image_url) {
            return $this->icon_url ?: $this->photo_url ?: $this->image_url;
        }

        return $this->localDefaultIconUrl();
    }

    public function getDisplayLabelAttribute(): ?string
    {
        return $this->duration_label
            ?: $this->name
            ?: (self::WARRANTY_LABELS[(string) $this->external_warranty_id] ?? null);
    }

    private function localDefaultIconUrl(): ?string
    {
        $label = Str::lower((string) $this->display_label);
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

        return file_exists(public_path($path)) ? asset($path) : null;
    }
}
