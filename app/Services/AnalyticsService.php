<?php

namespace App\Services;

use App\Models\DailyAnalyticsSnapshot;
use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function ensureLocalOutlet(): Outlet
    {
        $settings = HotelSetting::allAsMap();
        $code = strtoupper(trim($settings['outlet_code'] ?: (string) config('pos.outlet_code', 'ROYAL-YTL-01')));
        $capacity = (int) DiningTable::query()->where('is_active', true)->sum('capacity');

        $outlet = Outlet::query()->where('is_local', true)->first()
            ?? Outlet::query()->where('outlet_code', $code)->first();

        if (! $outlet) {
            return Outlet::create([
                'outlet_code' => $code,
                'name' => $settings['hotel_name'] ?: 'Royal Family Restaurant & Hotel',
                'city' => $settings['outlet_city'] ?: null,
                'state' => $settings['outlet_state'] ?: null,
                'seating_capacity' => $capacity,
                'is_local' => true,
                'is_active' => true,
            ]);
        }

        $outlet->update([
            'outlet_code' => $code,
            'name' => $settings['hotel_name'] ?: $outlet->name,
            'city' => $settings['outlet_city'] ?: $outlet->city,
            'state' => $settings['outlet_state'] ?: $outlet->state,
            'seating_capacity' => $capacity > 0 ? $capacity : $outlet->seating_capacity,
            'is_local' => true,
        ]);

        return $outlet->fresh();
    }

    public function buildDailySnapshot(Carbon $date, ?Outlet $outlet = null): DailyAnalyticsSnapshot
    {
        $outlet ??= $this->ensureLocalOutlet();
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $paidOrders = Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$dayStart, $dayEnd])
            ->with(['waiter'])
            ->get();

        $cancelledOrdersCount = Order::query()
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$dayStart, $dayEnd])
            ->count();

        $cancelledItemsCount = OrderItem::query()
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$dayStart, $dayEnd])
            ->count();

        $paidOrderIds = $paidOrders->pluck('id');
        $activeItems = $paidOrderIds->isNotEmpty()
            ? OrderItem::query()
                ->whereIn('order_id', $paidOrderIds)
                ->where('status', '!=', 'cancelled')
                ->with(['menuItem.category'])
                ->get()
            : collect();

        $totalOrders = $paidOrders->count();
        $dineInOrders = $paidOrders->where('order_type', 'dine_in')->count();
        $takeawayOrders = $paidOrders->where('order_type', 'takeaway')->count();
        $grossSales = round((float) $paidOrders->sum('subtotal'), 2);
        $discountTotal = round((float) $paidOrders->sum('discount_amount'), 2);
        $gstTotal = round((float) $paidOrders->sum('cgst_amount') + (float) $paidOrders->sum('sgst_amount'), 2);
        $netRevenue = round((float) $paidOrders->sum('grand_total'), 2);
        $avgBill = $totalOrders > 0 ? round($netRevenue / $totalOrders, 2) : 0.0;
        $cashTotal = round((float) $paidOrders->sum('cash_amount'), 2);
        $upiTotal = round((float) $paidOrders->sum('upi_amount'), 2);
        $cardTotal = round((float) $paidOrders->where('payment_method', 'card')->sum('grand_total'), 2);
        $guestCount = (int) $paidOrders->sum('guest_count');

        // Category sales breakdown
        $categoryMap = [];
        $dishMap = [];
        $vegQty = 0;
        $vegRevenue = 0.0;
        $nonVegQty = 0;
        $nonVegRevenue = 0.0;

        foreach ($activeItems as $item) {
            $categoryName = $item->menuItem?->category?->name ?? 'Other';
            if (! isset($categoryMap[$categoryName])) {
                $categoryMap[$categoryName] = ['name' => $categoryName, 'qty' => 0, 'revenue' => 0.0];
            }
            $categoryMap[$categoryName]['qty'] += (int) $item->quantity;
            $categoryMap[$categoryName]['revenue'] = round($categoryMap[$categoryName]['revenue'] + (float) $item->line_total, 2);

            $dishKey = $item->item_name;
            if (! isset($dishMap[$dishKey])) {
                $dishMap[$dishKey] = [
                    'name' => $item->item_name,
                    'qty' => 0,
                    'revenue' => 0.0,
                    'is_veg' => (bool) $item->is_veg,
                ];
            }
            $dishMap[$dishKey]['qty'] += (int) $item->quantity;
            $dishMap[$dishKey]['revenue'] = round($dishMap[$dishKey]['revenue'] + (float) $item->line_total, 2);

            if ($item->is_veg) {
                $vegQty += (int) $item->quantity;
                $vegRevenue = round($vegRevenue + (float) $item->line_total, 2);
            } else {
                $nonVegQty += (int) $item->quantity;
                $nonVegRevenue = round($nonVegRevenue + (float) $item->line_total, 2);
            }
        }

        usort($categoryMap, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        usort($dishMap, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $topDishes = array_slice($dishMap, 0, 12);

        // Hourly revenue & order counts
        $hourlyMap = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyMap[$h] = ['hour' => $h, 'orders' => 0, 'revenue' => 0.0];
        }
        foreach ($paidOrders as $order) {
            $hour = (int) ($order->paid_at?->format('G') ?? 0);
            $hourlyMap[$hour]['orders']++;
            $hourlyMap[$hour]['revenue'] = round($hourlyMap[$hour]['revenue'] + (float) $order->grand_total, 2);
        }

        // Waiter leaderboard
        $waiterPerformance = $paidOrders
            ->groupBy(fn (Order $o): string => $o->waiter?->name ?? 'Counter')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'orders' => $group->count(),
                'revenue' => round((float) $group->sum('grand_total'), 2),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();

        $vegNonVegSplit = [
            'veg_qty' => $vegQty,
            'veg_revenue' => $vegRevenue,
            'nonveg_qty' => $nonVegQty,
            'nonveg_revenue' => $nonVegRevenue,
        ];

        $dataFields = [
            'total_orders' => $totalOrders,
            'dine_in_orders' => $dineInOrders,
            'takeaway_orders' => $takeawayOrders,
            'gross_sales' => $grossSales,
            'discount_total' => $discountTotal,
            'gst_total' => $gstTotal,
            'net_revenue' => $netRevenue,
            'avg_bill_value' => $avgBill,
            'cash_total' => $cashTotal,
            'upi_total' => $upiTotal,
            'card_total' => $cardTotal,
            'cancelled_orders' => $cancelledOrdersCount,
            'cancelled_items' => $cancelledItemsCount,
            'guest_count' => $guestCount,
            'category_sales' => array_values($categoryMap),
            'top_dishes' => array_values($topDishes),
            'hourly_sales' => array_values($hourlyMap),
            'waiter_performance' => $waiterPerformance,
            'veg_nonveg_split' => $vegNonVegSplit,
        ];

        $checksum = hash('sha256', (string) json_encode($dataFields));
        $businessDate = $date->toDateString();

        $existing = DailyAnalyticsSnapshot::query()
            ->where('outlet_id', $outlet->id)
            ->whereDate('business_date', $businessDate)
            ->first();

        if ($existing) {
            $changed = $existing->payload_checksum !== $checksum;
            $existing->fill($dataFields);
            $existing->payload_checksum = $checksum;
            if ($changed) {
                $existing->sync_status = 'pending';
            }
            $existing->save();

            return $existing->fresh();
        }

        return DailyAnalyticsSnapshot::create(array_merge($dataFields, [
            'outlet_id' => $outlet->id,
            'business_date' => $businessDate,
            'payload_checksum' => $checksum,
            'sync_status' => 'pending',
        ]));
    }

    public function syncLocalSnapshotsForRange(Carbon $from, Carbon $to): int
    {
        $outlet = $this->ensureLocalOutlet();
        $datesWithActivity = Order::query()
            ->where(function ($q) use ($from, $to): void {
                $q->where(function ($paid) use ($from, $to): void {
                    $paid->where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
                })->orWhere(function ($cancelled) use ($from, $to): void {
                    $cancelled->where('status', 'cancelled')->whereBetween('updated_at', [$from, $to]);
                });
            })
            ->get(['paid_at', 'updated_at', 'status'])
            ->map(fn (Order $o): string => ($o->status === 'paid' && $o->paid_at ? $o->paid_at : $o->updated_at)->toDateString())
            ->unique()
            ->values();

        if (today()->between($from, $to) && ! $datesWithActivity->contains(today()->toDateString())) {
            $datesWithActivity->push(today()->toDateString());
        }

        $built = 0;
        foreach ($datesWithActivity as $dateString) {
            $this->buildDailySnapshot(Carbon::parse($dateString), $outlet);
            $built++;
        }

        return $built;
    }

    /**
     * Build complete chart, KPI, multi-outlet, and AI-ready analytics payload.
     *
     * @return array<string, mixed>
     */
    public function getAnalyticsPayload(Carbon $from, Carbon $to, ?int $outletId = null): array
    {
        $this->syncLocalSnapshotsForRange($from, $to);

        $periodDays = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($periodDays - 1)->startOfDay();

        $snapshotsQuery = DailyAnalyticsSnapshot::query()
            ->with('outlet')
            ->whereDate('business_date', '>=', $from->toDateString())
            ->whereDate('business_date', '<=', $to->toDateString())
            ->orderBy('business_date');

        $prevQuery = DailyAnalyticsSnapshot::query()
            ->whereDate('business_date', '>=', $prevFrom->toDateString())
            ->whereDate('business_date', '<=', $prevTo->toDateString());

        if ($outletId !== null) {
            $snapshotsQuery->where('outlet_id', $outletId);
            $prevQuery->where('outlet_id', $outletId);
        }

        $snapshots = $snapshotsQuery->get();
        $prevSnapshots = $prevQuery->get();

        $netRevenue = round((float) $snapshots->sum('net_revenue'), 2);
        $grossSales = round((float) $snapshots->sum('gross_sales'), 2);
        $discountTotal = round((float) $snapshots->sum('discount_total'), 2);
        $gstTotal = round((float) $snapshots->sum('gst_total'), 2);
        $totalOrders = (int) $snapshots->sum('total_orders');
        $dineInOrders = (int) $snapshots->sum('dine_in_orders');
        $takeawayOrders = (int) $snapshots->sum('takeaway_orders');
        $cancelledOrders = (int) $snapshots->sum('cancelled_orders');
        $cancelledItems = (int) $snapshots->sum('cancelled_items');
        $guestCount = (int) $snapshots->sum('guest_count');
        $cashTotal = round((float) $snapshots->sum('cash_total'), 2);
        $upiTotal = round((float) $snapshots->sum('upi_total'), 2);
        $cardTotal = round((float) $snapshots->sum('card_total'), 2);
        $avgBill = $totalOrders > 0 ? round($netRevenue / $totalOrders, 2) : 0.0;

        $prevRevenue = round((float) $prevSnapshots->sum('net_revenue'), 2);
        $prevOrders = (int) $prevSnapshots->sum('total_orders');

        $revenueGrowthPct = $prevRevenue > 0
            ? round((($netRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : ($netRevenue > 0 ? 100.0 : 0.0);

        $ordersGrowthPct = $prevOrders > 0
            ? round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1)
            : ($totalOrders > 0 ? 100.0 : 0.0);

        $discountRatePct = $grossSales > 0 ? round(($discountTotal / $grossSales) * 100, 2) : 0.0;
        $cancelRatePct = ($totalOrders + $cancelledOrders) > 0
            ? round(($cancelledOrders / ($totalOrders + $cancelledOrders)) * 100, 2)
            : 0.0;

        $activeSellingDays = max(1, $snapshots->where('total_orders', '>', 0)->pluck('business_date')->map->toDateString()->unique()->count());
        $dailyRunRate = round($netRevenue / $activeSellingDays, 2);
        $annualizedRunRate = round($dailyRunRate * 365, 2);

        // Daily trend across full date range (zero-filled for continuous graph)
        $byDate = $snapshots->groupBy(fn (DailyAnalyticsSnapshot $s): string => $s->business_date->toDateString());
        $dailyTrend = [];
        $cursor = $from->copy()->startOfDay();
        $endCursor = $to->copy()->startOfDay();
        while ($cursor->lte($endCursor)) {
            $key = $cursor->toDateString();
            /** @var Collection<int, DailyAnalyticsSnapshot> $dayRows */
            $dayRows = $byDate->get($key, collect());
            $dayRev = round((float) $dayRows->sum('net_revenue'), 2);
            $dayOrd = (int) $dayRows->sum('total_orders');
            $dailyTrend[] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'revenue' => $dayRev,
                'orders' => $dayOrd,
                'avg_bill' => $dayOrd > 0 ? round($dayRev / $dayOrd, 2) : 0.0,
            ];
            $cursor->addDay();
        }

        // Payment Split Pie Chart
        $paymentBase = max(0.01, $cashTotal + $upiTotal + $cardTotal);
        $paymentSplit = [
            ['label' => 'Cash', 'amount' => $cashTotal, 'pct' => $netRevenue > 0 ? round(($cashTotal / $paymentBase) * 100, 1) : 0.0, 'color' => '#059669'],
            ['label' => 'UPI / Digital', 'amount' => $upiTotal, 'pct' => $netRevenue > 0 ? round(($upiTotal / $paymentBase) * 100, 1) : 0.0, 'color' => '#2563eb'],
            ['label' => 'Card', 'amount' => $cardTotal, 'pct' => $netRevenue > 0 ? round(($cardTotal / $paymentBase) * 100, 1) : 0.0, 'color' => '#7c3aed'],
        ];

        // Aggregate Category Mix, Top Dishes, Hourly Sales, Waiters, Veg/Non-Veg
        $catTotals = [];
        $dishTotals = [];
        $hourlyTotals = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyTotals[$h] = [
                'hour' => $h,
                'label' => Carbon::createFromTime($h)->format('ga'),
                'orders' => 0,
                'revenue' => 0.0,
            ];
        }
        $waiterTotals = [];
        $vegRevenue = 0.0;
        $nonVegRevenue = 0.0;
        $vegQty = 0;
        $nonVegQty = 0;

        foreach ($snapshots as $snap) {
            foreach ($snap->category_sales ?? [] as $cat) {
                $name = (string) ($cat['name'] ?? 'Other');
                if (! isset($catTotals[$name])) {
                    $catTotals[$name] = ['name' => $name, 'qty' => 0, 'revenue' => 0.0];
                }
                $catTotals[$name]['qty'] += (int) ($cat['qty'] ?? 0);
                $catTotals[$name]['revenue'] = round($catTotals[$name]['revenue'] + (float) ($cat['revenue'] ?? 0), 2);
            }

            foreach ($snap->top_dishes ?? [] as $dish) {
                $name = (string) ($dish['name'] ?? 'Item');
                if (! isset($dishTotals[$name])) {
                    $dishTotals[$name] = [
                        'name' => $name,
                        'qty' => 0,
                        'revenue' => 0.0,
                        'is_veg' => (bool) ($dish['is_veg'] ?? true),
                    ];
                }
                $dishTotals[$name]['qty'] += (int) ($dish['qty'] ?? 0);
                $dishTotals[$name]['revenue'] = round($dishTotals[$name]['revenue'] + (float) ($dish['revenue'] ?? 0), 2);
            }

            foreach ($snap->hourly_sales ?? [] as $hr) {
                $h = (int) ($hr['hour'] ?? 0);
                if (isset($hourlyTotals[$h])) {
                    $hourlyTotals[$h]['orders'] += (int) ($hr['orders'] ?? 0);
                    $hourlyTotals[$h]['revenue'] = round($hourlyTotals[$h]['revenue'] + (float) ($hr['revenue'] ?? 0), 2);
                }
            }

            foreach ($snap->waiter_performance ?? [] as $w) {
                $name = (string) ($w['name'] ?? 'Staff');
                if (! isset($waiterTotals[$name])) {
                    $waiterTotals[$name] = ['name' => $name, 'orders' => 0, 'revenue' => 0.0];
                }
                $waiterTotals[$name]['orders'] += (int) ($w['orders'] ?? 0);
                $waiterTotals[$name]['revenue'] = round($waiterTotals[$name]['revenue'] + (float) ($w['revenue'] ?? 0), 2);
            }

            $vnv = $snap->veg_nonveg_split ?? [];
            $vegRevenue = round($vegRevenue + (float) ($vnv['veg_revenue'] ?? 0), 2);
            $nonVegRevenue = round($nonVegRevenue + (float) ($vnv['nonveg_revenue'] ?? 0), 2);
            $vegQty += (int) ($vnv['veg_qty'] ?? 0);
            $nonVegQty += (int) ($vnv['nonveg_qty'] ?? 0);
        }

        $palette = ['#ea580c', '#2563eb', '#059669', '#7c3aed', '#d97706', '#dc2626', '#0891b2', '#4f46e5'];
        usort($catTotals, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $catGrand = max(0.01, array_sum(array_column($catTotals, 'revenue')));
        $categoryMix = [];
        foreach (array_values($catTotals) as $idx => $cat) {
            $categoryMix[] = [
                'name' => $cat['name'],
                'qty' => $cat['qty'],
                'revenue' => $cat['revenue'],
                'pct' => round(($cat['revenue'] / $catGrand) * 100, 1),
                'color' => $palette[$idx % count($palette)],
            ];
        }

        usort($dishTotals, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $topDishes = array_slice(array_values($dishTotals), 0, 10);

        usort($waiterTotals, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $waiterLeaderboard = array_values($waiterTotals);

        // Peak dining hours (show 9 AM to 11 PM or all hours with activity)
        $activeHours = array_values(array_filter(
            $hourlyTotals,
            fn (array $h): bool => ($h['hour'] >= 10 && $h['hour'] <= 23) || $h['orders'] > 0
        ));

        $peakHour = collect($hourlyTotals)->sortByDesc('revenue')->first();
        $vnvTotal = max(0.01, $vegRevenue + $nonVegRevenue);
        $vegNonVeg = [
            'veg_qty' => $vegQty,
            'veg_revenue' => $vegRevenue,
            'veg_pct' => ($vegRevenue + $nonVegRevenue) > 0 ? round(($vegRevenue / $vnvTotal) * 100, 1) : 0.0,
            'nonveg_qty' => $nonVegQty,
            'nonveg_revenue' => $nonVegRevenue,
            'nonveg_pct' => ($vegRevenue + $nonVegRevenue) > 0 ? round(($nonVegRevenue / $vnvTotal) * 100, 1) : 0.0,
        ];

        // Multi-Outlet Comparison (Cloud Hub / Portfolio benchmarking)
        $allRangeSnapshots = DailyAnalyticsSnapshot::query()
            ->with('outlet')
            ->whereDate('business_date', '>=', $from->toDateString())
            ->whereDate('business_date', '<=', $to->toDateString())
            ->get();

        $outlets = Outlet::query()->where('is_active', true)->orderByDesc('is_local')->orderBy('name')->get();
        $outletComparison = $outlets->map(function (Outlet $o) use ($allRangeSnapshots): array {
            $rows = $allRangeSnapshots->where('outlet_id', $o->id);
            $rev = round((float) $rows->sum('net_revenue'), 2);
            $ord = (int) $rows->sum('total_orders');
            $disc = round((float) $rows->sum('discount_total'), 2);
            $gross = round((float) $rows->sum('gross_sales'), 2);
            $upi = round((float) $rows->sum('upi_total'), 2);

            return [
                'id' => $o->id,
                'outlet_code' => $o->outlet_code,
                'name' => $o->name,
                'location' => trim(implode(', ', array_filter([$o->city, $o->state]))),
                'is_local' => $o->is_local,
                'revenue' => $rev,
                'orders' => $ord,
                'avg_bill' => $ord > 0 ? round($rev / $ord, 2) : 0.0,
                'discount_pct' => $gross > 0 ? round(($disc / $gross) * 100, 1) : 0.0,
                'upi_share_pct' => $rev > 0 ? round(($upi / $rev) * 100, 1) : 0.0,
                'last_synced_at' => $o->last_synced_at?->format('d M Y, h:i A') ?? ($o->is_local ? 'Live Local' : 'Not Synced'),
            ];
        })->sortByDesc('revenue')->values()->all();

        $digitalSharePct = $netRevenue > 0 ? round((($upiTotal + $cardTotal) / $paymentBase) * 100, 1) : 0.0;
        $dineInSharePct = $totalOrders > 0 ? round(($dineInOrders / $totalOrders) * 100, 1) : 0.0;
        $takeawaySharePct = $totalOrders > 0 ? round(($takeawayOrders / $totalOrders) * 100, 1) : 0.0;

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $periodDays,
                'active_selling_days' => $activeSellingDays,
            ],
            'kpis' => [
                'revenue' => $netRevenue,
                'gross_sales' => $grossSales,
                'discount_total' => $discountTotal,
                'discount_rate_pct' => $discountRatePct,
                'gst_total' => $gstTotal,
                'orders' => $totalOrders,
                'dine_in_orders' => $dineInOrders,
                'takeaway_orders' => $takeawayOrders,
                'dine_in_share_pct' => $dineInSharePct,
                'takeaway_share_pct' => $takeawaySharePct,
                'avg_bill' => $avgBill,
                'guest_count' => $guestCount,
                'cancelled_orders' => $cancelledOrders,
                'cancelled_items' => $cancelledItems,
                'cancel_rate_pct' => $cancelRatePct,
                'cash_total' => $cashTotal,
                'upi_total' => $upiTotal,
                'card_total' => $cardTotal,
                'digital_share_pct' => $digitalSharePct,
                'revenue_growth_pct' => $revenueGrowthPct,
                'orders_growth_pct' => $ordersGrowthPct,
                'daily_run_rate' => $dailyRunRate,
                'annualized_run_rate' => $annualizedRunRate,
                'peak_hour_label' => ($peakHour && $peakHour['orders'] > 0) ? $peakHour['label'] : 'N/A',
                'outlets_count' => count($outletComparison),
            ],
            'daily_trend' => $dailyTrend,
            'payment_split' => $paymentSplit,
            'category_mix' => $categoryMix,
            'hourly_sales' => $activeHours,
            'top_dishes' => $topDishes,
            'waiter_leaderboard' => $waiterLeaderboard,
            'veg_nonveg' => $vegNonVeg,
            'outlet_comparison' => $outletComparison,
        ];
    }
}
