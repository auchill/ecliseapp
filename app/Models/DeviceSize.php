<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceSize extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'code', 'source', 'status', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function mobileSentrixDevices(): HasMany
    {
        return $this->hasMany(MobileSentrixDevice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
