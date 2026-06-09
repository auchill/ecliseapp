<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pending',
        'replied' => 'Replied',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'converted_to_booking' => 'Converted to booking',
    ];

    protected $fillable = [
        'quote_number',
        'customer_name',
        'email',
        'phone_number',
        'device_type_id',
        'device_brand_id',
        'device_model_id',
        'device_model',
        'issue_category_id',
        'preferred_date',
        'preferred_time',
        'device_image',
        'issue_description',
        'status',
        'admin_note',
        'converted_to_booking',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'converted_to_booking' => 'boolean',
        ];
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function deviceBrand(): BelongsTo
    {
        return $this->belongsTo(DeviceBrand::class);
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    public function issueCategory(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class);
    }

    public function repairBooking(): HasOne
    {
        return $this->hasOne(RepairBooking::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['rejected', 'converted_to_booking']);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function deviceModelName(): ?string
    {
        return $this->deviceModel?->name ?? $this->device_model;
    }

    public function deviceLabel(): string
    {
        return trim(implode(' ', array_filter([
            $this->deviceBrand?->name,
            $this->deviceModelName(),
        ]))) ?: ($this->deviceType?->name ?? 'Device');
    }
}
