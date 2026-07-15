<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairNegotiationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_conversation_id',
        'actor_type',
        'actor_id',
        'event_type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RepairConversation::class, 'repair_conversation_id');
    }
}
