<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairConversation extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_AWAITING_CUSTOMER = 'awaiting_customer';

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_AWAITING_CUSTOMER => 'Awaiting customer',
        self::STATUS_PAYMENT_PENDING => 'Payment pending',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CLOSED => 'Closed',
    ];

    protected $fillable = [
        'repair_id',
        'customer_id',
        'assigned_admin_id',
        'status',
        'proposal_version',
        'accepted_proposal_version',
        'labour_amount',
        'diagnostic_fee',
        'service_fee',
        'discount_amount',
        'tax_amount',
        'selected_parts_subtotal',
        'final_total',
        'last_message_at',
        'agreed_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposal_version' => 'integer',
            'accepted_proposal_version' => 'integer',
            'labour_amount' => 'decimal:2',
            'diagnostic_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'selected_parts_subtotal' => 'decimal:2',
            'final_total' => 'decimal:2',
            'last_message_at' => 'datetime',
            'agreed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RepairMessage::class);
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function partGroups(): HasMany
    {
        return $this->hasMany(RepairPartGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activePartGroups(): HasMany
    {
        return $this->partGroups()->where('is_active', true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RepairNegotiationEvent::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function isPayable(): bool
    {
        return $this->status === self::STATUS_PAYMENT_PENDING && (float) $this->final_total > 0;
    }
}
