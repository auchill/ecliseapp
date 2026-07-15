<?php

namespace App\Models;

use App\Support\CatalogImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairPartOption extends Model
{
    use HasFactory;

    public const TYPE_PART = 'part';

    public const TYPE_CUSTOMER_SUPPLIED = 'customer_supplied';

    public const SYSTEM_KEY_CUSTOMER_SUPPLIED = 'customer_supplied';

    public const CUSTOMER_SUPPLIED_LABEL = 'I Have the Parts';

    protected $fillable = [
        'repair_part_group_id',
        'option_type',
        'is_system_option',
        'system_option_key',
        'source_type',
        'source_id',
        'sku_snapshot',
        'name_snapshot',
        'model_snapshot',
        'image_url_snapshot',
        'description_snapshot',
        'quality_label',
        'price_snapshot',
        'is_primary',
        'sort_order',
        'proposal_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'is_system_option' => 'boolean',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'proposal_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(RepairPartGroup::class, 'repair_part_group_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(RepairPartSelection::class);
    }

    public function label(): string
    {
        return (string) $this->name_snapshot;
    }

    public function isSystemOption(): bool
    {
        return (bool) $this->is_system_option;
    }

    public function isCustomerSuppliedOption(): bool
    {
        return $this->isSystemOption()
            && $this->option_type === self::TYPE_CUSTOMER_SUPPLIED
            && $this->system_option_key === self::SYSTEM_KEY_CUSTOMER_SUPPLIED;
    }

    public function isPartOption(): bool
    {
        return $this->option_type === self::TYPE_PART && ! $this->isSystemOption();
    }

    public function imageUrl(): string
    {
        return CatalogImage::displayUrl($this->image_url_snapshot);
    }

    public function modelLabel(): ?string
    {
        return $this->model_snapshot;
    }
}
