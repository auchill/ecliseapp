<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartTag extends Model
{
    protected $guarded = [];

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_part_tag')
            ->withTimestamps();
    }
}
