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
        'converted_to_repair' => 'Converted to repair',
    ];

    protected $fillable = [
        'customer_id',
        'quote_number',
        'customer_name',
        'email',
        'phone_number',
        'device_type_id',
        'product_brand_id',
        'product_model_id',
        'device_model',
        'issue_category_id',
        'preferred_date',
        'preferred_time',
        'device_image',
        'issue_description',
        'status',
        'admin_note',
        'converted_to_booking',
        'converted_to_repair',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'converted_to_booking' => 'boolean',
            'converted_to_repair' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function deviceBrand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'product_brand_id');
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_model_id');
    }

    public function issueCategory(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class);
    }

    public function repair(): HasOne
    {
        return $this->hasOne(Repair::class);
    }

    public function repairBooking(): HasOne
    {
        return $this->repair();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['rejected', 'converted_to_repair']);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getQuoteNumberAttribute(?string $value): string
    {
        return $value ?: 'Quote #'.$this->getKey();
    }

    public function getCustomerNameAttribute(?string $value): ?string
    {
        return $value ?? $this->customer?->full_name;
    }

    public function getEmailAttribute(?string $value): ?string
    {
        return $value ?? $this->customer?->email;
    }

    public function getPhoneNumberAttribute(?string $value): ?string
    {
        return $value ?? $this->customer?->phone;
    }

    public function getConvertedToBookingAttribute(mixed $value): bool
    {
        return (bool) ($this->converted_to_repair ?: $value);
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
