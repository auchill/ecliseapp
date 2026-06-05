<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairBooking extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Submitted',
        'Appointment Confirmed',
        'Device Received',
        'Diagnosis in Progress',
        'Waiting for Parts',
        'Repair in Progress',
        'Ready for Pickup',
        'Completed',
        'Cancelled',
    ];

    protected $fillable = [
        'user_id',
        'tracking_number',
        'customer_name',
        'email',
        'phone',
        'device_type',
        'device_brand',
        'device_model',
        'issue_category',
        'issue_description',
        'preferred_appointment_date',
        'preferred_appointment_time',
        'device_image_path',
        'terms_accepted',
        'status',
        'estimated_completion_date',
        'internal_notes',
        'customer_notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_appointment_date' => 'date',
            'estimated_completion_date' => 'date',
            'terms_accepted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(RepairStatusUpdate::class)->latest();
    }

    public function publicStatusUpdates(): HasMany
    {
        return $this->hasMany(RepairStatusUpdate::class)
            ->where('is_customer_visible', true)
            ->oldest();
    }

    public function deviceLabel(): string
    {
        return trim("{$this->device_brand} {$this->device_model}") ?: $this->device_type;
    }
}
