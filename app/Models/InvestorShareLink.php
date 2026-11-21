<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorShareLink extends Model
{
    protected $fillable = [
        'outlet_id',
        'title',
        'investor_name',
        'token',
        'period_days',
        'include_ai_insights',
        'include_branch_comparison',
        'expires_at',
        'is_active',
        'views_count',
        'last_viewed_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'period_days' => 'integer',
            'include_ai_insights' => 'boolean',
            'include_branch_comparison' => 'boolean',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'views_count' => 'integer',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by_id');
    }

    public function isAccessible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
