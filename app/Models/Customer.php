<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive', 'blocked'];

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'street_address',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country',
        'customer_since',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'customer_since' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    public function repairBookings(): HasMany
    {
        return $this->repairs();
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function activeCart(): HasOne
    {
        return $this->hasOne(Cart::class)->where('status', 'active');
    }

    public static function forUser(User $user): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'email' => $user->email,
                'customer_since' => now(),
                'status' => 'active',
            ],
        );
    }

    public function getOrCreateActiveCart(): Cart
    {
        return $this->carts()->firstOrCreate(['status' => 'active']);
    }
}
