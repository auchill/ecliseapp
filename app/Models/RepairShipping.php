<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairShipping extends Model
{
    use HasFactory;

    protected $table = 'repair_shipping';

    protected $fillable = [
        'repair_id',
        'shipping_address',
    ];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }
}
