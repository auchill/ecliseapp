<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MobileSentrixApiSetting extends Model
{
    protected $table = 'mobilesentrix_api_settings';

    protected $hidden = [
        'consumer_key',
        'consumer_secret',
        'access_token',
        'access_token_secret',
    ];

    protected $fillable = [
        'environment',
        'base_url',
        'consumer_name',
        'consumer_key',
        'consumer_secret',
        'access_token',
        'access_token_secret',
        'callback_url',
        'is_active',
        'last_authenticated_at',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_authenticated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
