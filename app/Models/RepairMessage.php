<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairMessage extends Model
{
    use HasFactory;

    public const SENDER_ADMIN = 'admin';

    public const SENDER_CUSTOMER = 'customer';

    public const SENDER_SYSTEM = 'system';

    protected $fillable = [
        'repair_conversation_id',
        'sender_type',
        'sender_id',
        'message_type',
        'message',
        'is_internal',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RepairConversation::class, 'repair_conversation_id');
    }
}
