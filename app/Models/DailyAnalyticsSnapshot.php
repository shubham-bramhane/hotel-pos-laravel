<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAnalyticsSnapshot extends Model
{
    protected $fillable = [
        'outlet_id',
        'business_date',
        'total_orders',
        'dine_in_orders',
        'takeaway_orders',
        'gross_sales',
        'discount_total',
        'gst_total',
        'net_revenue',
        'avg_bill_value',
        'cash_total',
        'upi_total',
        'card_total',
        'cancelled_orders',
        'cancelled_items',
        'guest_count',
        'category_sales',
        'top_dishes',
        'hourly_sales',
        'waiter_performance',
        'veg_nonveg_split',
        'payload_checksum',
        'sync_status',
        'synced_at',
        'sync_attempts',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'total_orders' => 'integer',
            'dine_in_orders' => 'integer',
            'takeaway_orders' => 'integer',
            'gross_sales' => 'float',
            'discount_total' => 'float',
            'gst_total' => 'float',
            'net_revenue' => 'float',
            'avg_bill_value' => 'float',
            'cash_total' => 'float',
            'upi_total' => 'float',
            'card_total' => 'float',
            'cancelled_orders' => 'integer',
            'cancelled_items' => 'integer',
            'guest_count' => 'integer',
            'category_sales' => 'array',
            'top_dishes' => 'array',
            'hourly_sales' => 'array',
            'waiter_performance' => 'array',
            'veg_nonveg_split' => 'array',
            'synced_at' => 'datetime',
            'sync_attempts' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array
    {
        return [
            'business_date' => $this->business_date->toDateString(),
            'total_orders' => $this->total_orders,
            'dine_in_orders' => $this->dine_in_orders,
            'takeaway_orders' => $this->takeaway_orders,
            'gross_sales' => $this->gross_sales,
            'discount_total' => $this->discount_total,
            'gst_total' => $this->gst_total,
            'net_revenue' => $this->net_revenue,
            'avg_bill_value' => $this->avg_bill_value,
            'cash_total' => $this->cash_total,
            'upi_total' => $this->upi_total,
            'card_total' => $this->card_total,
            'cancelled_orders' => $this->cancelled_orders,
            'cancelled_items' => $this->cancelled_items,
            'guest_count' => $this->guest_count,
            'category_sales' => $this->category_sales ?? [],
            'top_dishes' => $this->top_dishes ?? [],
            'hourly_sales' => $this->hourly_sales ?? [],
            'waiter_performance' => $this->waiter_performance ?? [],
            'veg_nonveg_split' => $this->veg_nonveg_split ?? [],
            'payload_checksum' => $this->payload_checksum,
        ];
    }
}
