<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartCompatibility extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
