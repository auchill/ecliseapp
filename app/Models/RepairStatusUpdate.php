<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_booking_id',
        'status',
        'note',
        'is_customer_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
        ];
    }

    public function repairBooking(): BelongsTo
    {
        return $this->belongsTo(RepairBooking::class);
    }
}
