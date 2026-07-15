<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairPartSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_part_group_id',
        'repair_part_option_id',
        'customer_id',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(RepairPartGroup::class, 'repair_part_group_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(RepairPartOption::class, 'repair_part_option_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
