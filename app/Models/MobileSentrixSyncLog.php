<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileSentrixSyncLog extends Model
{
    use HasFactory;

    protected $table = 'mobilesentrix_sync_logs';

    public const TYPES = [
        'authentication',
        'categories',
        'parts',
        'single_part',
        'search',
    ];

    public const STATUSES = [
        'started',
        'success',
        'failed',
        'partial',
    ];

    protected $fillable = [
        'sync_type',
        'status',
        'started_at',
        'finished_at',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'message',
        'error_details',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'error_details' => 'array',
        ];
    }
}
