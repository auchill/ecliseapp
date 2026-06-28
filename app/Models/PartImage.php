<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartImage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
