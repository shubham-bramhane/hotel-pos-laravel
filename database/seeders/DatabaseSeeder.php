<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\StaffUser;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default Hotel Settings
        $defaults = [
            'hotel_name' => 'Royal Family Restaurant & Hotel',
            'address' => 'Main Road, Yavatmal, Maharashtra',
            'phone' => '9876543210',
            'gstin' => '',
            'gst_enabled' => '1',
            'gst_rate' => '5',
            'paper_width' => '80',
            'upi_id' => '',
            'bill_footer' => 'Thank you! Please Visit Again.',
        ];
        foreach ($defaults as $k => $v) {
            HotelSetting::query()->firstOrCreate(['key' => $k], ['value' => $v]);
        }

        // 2. Staff Users (4-digit fast POS PINs)
        $staff = [
            ['name' => 'Owner (Admin)', 'role' => 'owner', 'pin_code' => '1111'],
            ['name' => 'Cashier Desk', 'role' => 'cashier', 'pin_code' => '2222'],
            ['name' => 'Main Kitchen', 'role' => 'kitchen', 'pin_code' => '3333'],
            ['name' => 'Waiter Raju', 'role' => 'waiter', 'pin_code' => '4444'],
            ['name' => 'Waiter Amit', 'role' => 'waiter', 'pin_code' => '5555'],
        ];
        foreach ($staff as $s) {
            StaffUser::query()->firstOrCreate(['pin_code' => $s['pin_code']], $s);
        }

        // 3. Dining Tables (Main Hall, AC Family, Garden)
        $tables = [
            ['code' => 'T1', 'name' => 'Table 1', 'section' => 'Main Hall', 'capacity' => 4, 'sort_order' => 1],
            ['code' => 'T2', 'name' => 'Table 2', 'section' => 'Main Hall', 'capacity' => 4, 'sort_order' => 2],
            ['code' => 'T3', 'name' => 'Table 3', 'section' => 'Main Hall', 'capacity' => 4, 'sort_order' => 3],
            ['code' => 'T4', 'name' => 'Table 4', 'section' => 'Main Hall', 'capacity' => 4, 'sort_order' => 4],
            ['code' => 'T5', 'name' => 'Table 5', 'section' => 'Main Hall', 'capacity' => 6, 'sort_order' => 5],
            ['code' => 'T6', 'name' => 'Table 6', 'section' => 'Main Hall', 'capacity' => 6, 'sort_order' => 6],
            ['code' => 'T7', 'name' => 'Table 7', 'section' => 'Main Hall', 'capacity' => 2, 'sort_order' => 7],
            ['code' => 'T8', 'name' => 'Table 8', 'section' => 'Main Hall', 'capacity' => 2, 'sort_order' => 8],
            ['code' => 'AC-1', 'name' => 'AC Cabin 1', 'section' => 'AC Family', 'capacity' => 6, 'sort_order' => 9],
            ['code' => 'AC-2', 'name' => 'AC Cabin 2', 'section' => 'AC Family', 'capacity' => 6, 'sort_order' => 10],
            ['code' => 'AC-3', 'name' => 'AC Cabin 3', 'section' => 'AC Family', 'capacity' => 8, 'sort_order' => 11],
            ['code' => 'AC-4', 'name' => 'AC Cabin 4', 'section' => 'AC Family', 'capacity' => 8, 'sort_order' => 12],
            ['code' => 'G1', 'name' => 'Garden 1', 'section' => 'Garden', 'capacity' => 6, 'sort_order' => 13],
            ['code' => 'G2', 'name' => 'Garden 2', 'section' => 'Garden', 'capacity' => 6, 'sort_order' => 14],
        ];
        foreach ($tables as $t) {
            DiningTable::query()->firstOrCreate(['code' => $t['code']], $t);
        }

        // 4. Menu Categories & Indian Hotel Dishes
        if (MenuCategory::query()->count() > 0) {
            return;
        }

        $menu = [
            'Starters' => [
                ['name' => 'Paneer Tikka', 'short_code' => 'PTK', 'price' => 220, 'is_veg' => true],
                ['name' => 'Veg Crispy', 'short_code' => 'VCR', 'price' => 180, 'is_veg' => true],
                ['name' => 'Hara Bhara Kabab', 'short_code' => 'HBK', 'price' => 170, 'is_veg' => true],
                ['name' => 'Masala Papad', 'short_code' => 'MP', 'price' => 45, 'is_veg' => true],
                ['name' => 'Chicken Tandoori (Half)', 'short_code' => 'CTH', 'price' => 280, 'is_veg' => false],
                ['name' => 'Chicken Lollipop (6 pcs)', 'short_code' => 'CLP', 'price' => 240, 'is_veg' => false],
            ],
            'Main Course' => [
                ['name' => 'Paneer Butter Masala', 'short_code' => 'PBM', 'price' => 240, 'is_veg' => true],
                ['name' => 'Kaju Curry', 'short_code' => 'KJC', 'price' => 260, 'is_veg' => true],
                ['name' => 'Dal Tadka', 'short_code' => 'DT', 'price' => 150, 'is_veg' => true],
                ['name' => 'Dal Fry', 'short_code' => 'DF', 'price' => 130, 'is_veg' => true],
                ['name' => 'Mix Veg Kolhapuri', 'short_code' => 'MVK', 'price' => 190, 'is_veg' => true],
                ['name' => 'Butter Chicken (Half)', 'short_code' => 'BC', 'price' => 310, 'is_veg' => false],
                ['name' => 'Mutton Saoji', 'short_code' => 'MSJ', 'price' => 360, 'is_veg' => false],
            ],
            'Roti & Naan' => [
                ['name' => 'Tandoori Roti', 'short_code' => 'TR', 'price' => 20, 'is_veg' => true],
                ['name' => 'Butter Roti', 'short_code' => 'BR', 'price' => 25, 'is_veg' => true],
                ['name' => 'Butter Naan', 'short_code' => 'BN', 'price' => 45, 'is_veg' => true],
                ['name' => 'Garlic Naan', 'short_code' => 'GN', 'price' => 60, 'is_veg' => true],
                ['name' => 'Tawa Chapati', 'short_code' => 'TC', 'price' => 15, 'is_veg' => true],
            ],
            'Rice & Biryani' => [
                ['name' => 'Jeera Rice', 'short_code' => 'JR', 'price' => 120, 'is_veg' => true],
                ['name' => 'Veg Dum Biryani', 'short_code' => 'VBR', 'price' => 210, 'is_veg' => true],
                ['name' => 'Chicken Dum Biryani', 'short_code' => 'CBR', 'price' => 260, 'is_veg' => false],
                ['name' => 'Dal Khichdi', 'short_code' => 'DK', 'price' => 160, 'is_veg' => true],
            ],
            'Chinese' => [
                ['name' => 'Veg Hakka Noodles', 'short_code' => 'VHN', 'price' => 170, 'is_veg' => true],
                ['name' => 'Veg Manchurian Dry', 'short_code' => 'VMD', 'price' => 180, 'is_veg' => true],
                ['name' => 'Paneer Chilli', 'short_code' => 'PC', 'price' => 220, 'is_veg' => true],
            ],
            'Beverages & Desserts' => [
                ['name' => 'Mineral Water (1L)', 'short_code' => 'MW', 'price' => 20, 'is_veg' => true],
                ['name' => 'Masala Chaas', 'short_code' => 'MC', 'price' => 40, 'is_veg' => true],
                ['name' => 'Cold Drink (250ml)', 'short_code' => 'CD', 'price' => 30, 'is_veg' => true],
                ['name' => 'Gulab Jamun (2 pcs)', 'short_code' => 'GJ', 'price' => 60, 'is_veg' => true],
            ],
        ];

        $catOrder = 1;
        foreach ($menu as $catName => $items) {
            $cat = MenuCategory::create(['name' => $catName, 'sort_order' => $catOrder++]);
            $itemOrder = 1;
            foreach ($items as $item) {
                MenuItem::create([
                    'category_id' => $cat->id,
                    'name' => $item['name'],
                    'short_code' => $item['short_code'],
                    'price' => $item['price'],
                    'is_veg' => $item['is_veg'],
                    'is_available' => true,
                    'sort_order' => $itemOrder++,
                ]);
            }
        }
    }
}
