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
            'pos_mode' => (string) config('pos.mode', 'outlet'),
            'outlet_code' => (string) config('pos.outlet_code', 'ROYAL-YTL-01'),
            'outlet_city' => 'Yavatmal',
            'outlet_state' => 'Maharashtra',
            'cloud_sync_enabled' => config('pos.cloud_sync.enabled') ? '1' : '0',
            'cloud_sync_url' => (string) config('pos.cloud_sync.url', ''),
            'cloud_sync_token' => (string) config('pos.cloud_sync.token', ''),
            'nightly_sync_time' => (string) config('pos.cloud_sync.nightly_time', '01:00'),
            'gemini_api_key' => (string) config('pos.gemini.api_key', ''),
            'gemini_model' => (string) config('pos.gemini.model', 'gemini-4-hydrogen'),
            'ai_auto_nightly' => '1',
        ], static::query()->pluck('value', 'key')->toArray());
    }
}
