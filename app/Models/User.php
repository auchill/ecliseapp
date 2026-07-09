<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'permission_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->permission_id || ! class_exists(Permission::class)) {
                return;
            }

            $permissionName = $user->role === 'admin' ? 'admin' : 'customer';
            $user->permission_id = Permission::query()
                ->where('name', $permissionName)
                ->where('status', 'active')
                ->value('id');
        });
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function isAdmin(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->relationLoaded('permission') && $this->permission) {
            return $this->permission->name === 'admin' && $this->permission->status === 'active';
        }

        if ($this->permission_id) {
            return $this->permission()->where('name', 'admin')->where('status', 'active')->exists();
        }

        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->relationLoaded('permission') && $this->permission) {
            return $this->permission->name === 'customer' && $this->permission->status === 'active';
        }

        if ($this->permission_id) {
            return $this->permission()->where('name', 'customer')->where('status', 'active')->exists();
        }

        return $this->role !== 'admin';
    }

    public function hasPermission(string $name): bool
    {
        return match ($name) {
            'admin' => $this->isAdmin(),
            'customer' => $this->isCustomer(),
            default => $this->isActive()
                && $this->permission_id
                && $this->permission()->where('name', $name)->where('status', 'active')->exists(),
        };
    }

    public function scopeAdmins($query)
    {
        return $query->whereHas('permission', function ($query): void {
            $query->where('name', 'admin')->where('status', 'active');
        });
    }

    public function scopeCustomers($query)
    {
        return $query->whereHas('permission', function ($query): void {
            $query->where('name', 'customer')->where('status', 'active');
        });
    }

    public function repairBookings(): HasMany
    {
        return $this->hasMany(RepairBooking::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            Customer::class,
            'user_id',
            'customer_id',
        );
    }
}
