<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsightReport extends Model
{
    protected $fillable = [
        'outlet_id',
        'scope',
        'period_from',
        'period_to',
        'provider',
        'model_name',
        'executive_summary',
        'health_score',
        'highlights',
        'risks',
        'recommendations',
        'investor_narrative',
        'metrics_snapshot',
        'generated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'health_score' => 'integer',
            'highlights' => 'array',
            'risks' => 'array',
            'recommendations' => 'array',
            'metrics_snapshot' => 'array',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'generated_by_id');
    }
}
