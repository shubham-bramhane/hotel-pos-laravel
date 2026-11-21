<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiningTable extends Model
{
    protected $fillable = ['code', 'name', 'section', 'capacity', 'status', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereIn('status', ['open', 'billing'])
            ->latestOfMany();
    }
}
