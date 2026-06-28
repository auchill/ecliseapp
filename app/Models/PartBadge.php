<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
