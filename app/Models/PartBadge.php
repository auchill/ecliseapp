<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class PartBadge extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_part_badge')
            ->withTimestamps();
    }

    public function getDisplayIconUrlAttribute(): ?string
    {
        if ($this->icon_url ?: $this->photo_url ?: $this->image_url) {
            return $this->icon_url ?: $this->photo_url ?: $this->image_url;
        }

        return $this->localDefaultIconUrl();
    }

    private function localDefaultIconUrl(): ?string
    {
        $name = Str::lower((string) $this->name);
        $filename = match (true) {
            str_contains($name, 'basic') => 'badge-basic.svg',
            str_contains($name, 'pro') => 'badge-pro.svg',
            str_contains($name, 'core') => 'badge-core.svg',
            str_contains($name, 'genuine') => 'badge-genuine.svg',
            str_contains($name, 'amp') => 'badge-ampsentrix.svg',
            default => 'badge-default.svg',
        };

        $path = 'images/parts/badges/'.$filename;

        return file_exists(public_path($path)) ? asset($path) : null;
    }
}
