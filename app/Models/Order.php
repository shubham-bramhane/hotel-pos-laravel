<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_no', 'table_id', 'waiter_id', 'settled_by_id', 'order_type',
        'guest_count', 'customer_name', 'customer_phone', 'status',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount',
        'gst_rate', 'cgst_amount', 'sgst_amount', 'round_off', 'grand_total',
        'payment_method', 'cash_amount', 'upi_amount', 'notes', 'opened_at', 'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_value' => 'float',
        'discount_amount' => 'float',
        'gst_rate' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'round_off' => 'float',
        'grand_total' => 'float',
        'cash_amount' => 'float',
        'upi_amount' => 'float',
        'opened_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'waiter_id');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'settled_by_id');
    }

    public function kots(): HasMany
    {
        return $this->hasMany(Kot::class)->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->where('status', '!=', 'cancelled')->orderBy('id');
    }

    public function label(): string
    {
        if ($this->order_type === 'takeaway') {
            return 'Parcel'.($this->customer_name ? ' • '.$this->customer_name : '');
        }

        $diningTable = $this->getRelationValue('table');

        return $diningTable ? $diningTable->code.' ('.$diningTable->name.')' : 'Table';
    }
}
