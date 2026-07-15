<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RepairPartGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_conversation_id',
        'title',
        'description',
        'is_required',
        'sort_order',
        'proposal_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'proposal_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RepairConversation::class, 'repair_conversation_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(RepairPartOption::class)
            ->orderByRaw('CASE WHEN is_system_option = 1 OR option_type = ? THEN 0 WHEN is_primary = 1 THEN 1 ELSE 2 END', [RepairPartOption::TYPE_CUSTOMER_SUPPLIED])
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeOptions(): HasMany
    {
        return $this->options()->where('is_active', true);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(RepairPartSelection::class);
    }

    public function selection(): HasOne
    {
        return $this->hasOne(RepairPartSelection::class);
    }
}
