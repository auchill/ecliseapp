<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_id',
        'status',
        'note',
        'is_customer_visible',
        'delivery_carrier',
        'tracking_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
        ];
    }

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function repairBooking(): BelongsTo
    {
        return $this->repair();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
