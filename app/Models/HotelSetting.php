<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, string $default = ''): string
    {
        $row = static::query()->find($key);

        return $row !== null && $row->value !== null ? (string) $row->value : $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) ($value ?? '')]);
    }

    public static function allAsMap(): array
    {
        return array_merge([
            'hotel_name' => 'Royal Family Restaurant & Hotel',
            'address' => 'Main Road, Yavatmal, Maharashtra',
            'phone' => '9876543210',
            'gstin' => '',
            'gst_enabled' => '1',
            'gst_rate' => '5',
            'paper_width' => '80',
            'upi_id' => '',
            'bill_footer' => 'Thank you! Please Visit Again.',
        ], static::query()->pluck('value', 'key')->toArray());
    }
}
