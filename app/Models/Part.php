<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'Screens',
        'Batteries',
        'Charging Ports',
        'Cameras',
        'Back Covers',
        'Speakers',
        'Keyboards',
        'Laptop Screens',
        'Laptop Batteries',
        'Motherboards',
        'Other',
    ];

    protected $fillable = [
        'name',
        'device_type',
        'brand',
        'model_compatibility',
        'part_category',
        'image_path',
        'price',
        'stock_status',
        'supplier',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }

    public function imageUrl(): string
    {
        if ($this->image_path) {
            return asset('storage/'.$this->image_path);
        }

        return asset('images/brand/logo.png');
    }
}
