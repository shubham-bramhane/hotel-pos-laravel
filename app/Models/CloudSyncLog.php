<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudSyncLog extends Model
{
    protected $fillable = [
        'outlet_id',
        'direction',
        'trigger_type',
        'status',
        'snapshots_count',
        'http_status',
        'message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'snapshots_count' => 'integer',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
