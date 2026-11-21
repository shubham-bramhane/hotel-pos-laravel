<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = [
        'outlet_code',
        'name',
        'city',
        'state',
        'seating_capacity',
        'sync_secret_hash',
        'is_local',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'seating_capacity' => 'integer',
            'is_local' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(DailyAnalyticsSnapshot::class)->orderByDesc('business_date');
    }

    public function aiReports(): HasMany
    {
        return $this->hasMany(AiInsightReport::class)->latest();
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(CloudSyncLog::class)->latest();
    }
}
