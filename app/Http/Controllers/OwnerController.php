<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StaffUser;
use App\Services\AnalyticsService;
use App\Services\PosService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerController extends Controller
{
    public function __construct(
        private readonly PosService $pos
    ) {}

    public function dashboard(Request $request): View
    {
        [$from, $to, $range] = $this->resolveDateRange($request);

        $paidOrders = Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->with(['table', 'waiter', 'settledBy'])
            ->orderByDesc('paid_at')
            ->get();

        $totalRevenue = (float) $paidOrders->sum('grand_total');
        $totalDiscount = (float) $paidOrders->sum('discount_amount');
        $totalGst = (float) $paidOrders->sum('cgst_amount') + (float) $paidOrders->sum('sgst_amount');
        $billCount = $paidOrders->count();
        $avgBill = $billCount > 0 ? round($totalRevenue / $billCount) : 0;

        $cashTotal = (float) $paidOrders->sum('cash_amount');
        $upiTotal = (float) $paidOrders->sum('upi_amount');
        $cardTotal = (float) $paidOrders->where('payment_method', 'card')->sum('grand_total');

        $runningOrders = Order::query()->whereIn('status', ['open', 'billing'])->get();
        $runningValue = (float) $runningOrders->sum('grand_total');

        $cancelledItemsCount = OrderItem::query()
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        // Top selling items
        $paidIds = $paidOrders->pluck('id');
        $topItems = $paidIds->isNotEmpty()
            ? OrderItem::query()
                ->whereIn('order_id', $paidIds)
                ->where('status', '!=', 'cancelled')
                ->select('item_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(line_total) as total_sales'))
                ->groupBy('item_name')
                ->orderByDesc('total_sales')
                ->limit(10)
                ->get()
            : collect();

        // Waiter performance
        $waiterStats = $paidOrders
            ->groupBy(fn ($o) => $o->waiter?->name ?? 'Counter')
            ->map(fn ($group, $name) => [
                'name' => $name,
                'orders' => $group->count(),
                'sales' => (float) $group->sum('grand_total'),
            ])
            ->sortByDesc('sales')
            ->values();

        return view('owner.dashboard', [
            'settings' => HotelSetting::allAsMap(),
            'range' => $range,
            'fromDate' => $from->format('Y-m-d'),
            'toDate' => $to->format('Y-m-d'),
            'kpis' => [
                'revenue' => $totalRevenue,
                'bills' => $billCount,
                'avg_bill' => $avgBill,
                'discount' => $totalDiscount,
                'gst' => $totalGst,
                'cash' => $cashTotal,
                'upi' => $upiTotal,
                'card' => $cardTotal,
                'running_count' => $runningOrders->count(),
                'running_value' => $runningValue,
                'cancelled_items' => $cancelledItemsCount,
            ],
            'topItems' => $topItems,
            'waiterStats' => $waiterStats,
            'paidOrders' => $paidOrders->take(25),
        ]);
    }

    public function exportSales(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        $orders = Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->with(['table', 'waiter', 'settledBy'])
            ->orderBy('paid_at')
            ->get();

        $filename = 'Hotel_Sales_'.$from->format('Ymd').'_to_'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Bill No', 'Date Time', 'Type / Table', 'Waiter', 'Subtotal', 'Discount', 'CGST', 'SGST', 'Grand Total', 'Payment Mode', 'Cash', 'UPI', 'Settled By'], ',', '"', '');
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->order_no,
                    $o->paid_at?->format('d-m-Y h:i A'),
                    $o->label(),
                    $o->waiter?->name ?? '—',
                    $o->subtotal,
                    $o->discount_amount,
                    $o->cgst_amount,
                    $o->sgst_amount,
                    $o->grand_total,
                    strtoupper((string) $o->payment_method),
                    $o->cash_amount,
                    $o->upi_amount,
                    $o->settledBy?->name ?? '—',
                ], ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ================= MENU MANAGER ================= */
    public function menu(): View
    {
        $categories = MenuCategory::query()
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('owner.menu', [
            'settings' => HotelSetting::allAsMap(),
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $max = (int) MenuCategory::query()->max('sort_order');
        MenuCategory::create(['name' => trim($v['name']), 'sort_order' => $max + 1]);

        return back()->with('status', "Category \"{$v['name']}\" added.");
    }

    public function storeMenuItem(Request $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $v = $request->validate([
            'category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'short_code' => ['nullable', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_veg' => ['required', 'boolean'],
        ]);

        $item = MenuItem::create([
            'category_id' => $v['category_id'],
            'name' => trim($v['name']),
            'short_code' => $v['short_code'] ? strtoupper(trim($v['short_code'])) : null,
            'price' => round((float) $v['price'], 2),
            'is_veg' => (bool) $v['is_veg'],
            'is_available' => true,
        ]);

        $this->pos->logAudit($staff, 'menu_item_added', 'menu_item', $item->id, ['name' => $item->name, 'price' => $item->price]);

        return back()->with('status', "Dish \"{$item->name}\" added to menu.");
    }

    public function updateMenuItem(Request $request, MenuItem $item): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'short_code' => ['nullable', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_veg' => ['required', 'boolean'],
            'is_available' => ['required', 'boolean'],
        ]);

        $item->update([
            'name' => trim($v['name']),
            'short_code' => $v['short_code'] ? strtoupper(trim($v['short_code'])) : null,
            'price' => round((float) $v['price'], 2),
            'is_veg' => (bool) $v['is_veg'],
            'is_available' => (bool) $v['is_available'],
        ]);

        $this->pos->logAudit($staff, 'menu_item_updated', 'menu_item', $item->id, ['name' => $item->name, 'price' => $item->price]);

        return back()->with('status', "Updated \"{$item->name}\".");
    }

    public function destroyMenuItem(Request $request, MenuItem $item): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $name = $item->name;
        $item->delete();

        $this->pos->logAudit($staff, 'menu_item_deleted', 'menu_item', null, ['name' => $name]);

        return back()->with('status', "Deleted \"{$name}\" from menu.");
    }

    /* ================= TABLE MANAGER ================= */
    public function tables(): View
    {
        $tables = DiningTable::query()->with('activeOrder')->orderBy('sort_order')->get();

        return view('owner.tables', [
            'settings' => HotelSetting::allAsMap(),
            'tables' => $tables,
        ]);
    }

    public function storeTable(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:dining_tables,code'],
            'name' => ['required', 'string', 'max:60'],
            'section' => ['required', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $max = (int) DiningTable::query()->max('sort_order');
        DiningTable::create([
            'code' => strtoupper(trim($v['code'])),
            'name' => trim($v['name']),
            'section' => trim($v['section']),
            'capacity' => (int) $v['capacity'],
            'sort_order' => $max + 1,
        ]);

        return back()->with('status', "Table {$v['code']} added.");
    }

    public function destroyTable(DiningTable $table): RedirectResponse
    {
        if ($table->activeOrder()->exists()) {
            return back()->with('error', "Cannot delete {$table->code} while an active order is open.");
        }
        $code = $table->code;
        $table->delete();

        return back()->with('status', "Table {$code} removed.");
    }

    /* ================= STAFF & PIN MANAGER ================= */
    public function staff(): View
    {
        $staffList = StaffUser::query()->orderBy('id')->get();

        return view('owner.staff', [
            'settings' => HotelSetting::allAsMap(),
            'staffList' => $staffList,
        ]);
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:owner,cashier,kitchen,waiter'],
            'pin_code' => ['required', 'digits_between:4,6', 'unique:staff_users,pin_code'],
        ]);

        StaffUser::create([
            'name' => trim($v['name']),
            'role' => $v['role'],
            'pin_code' => trim($v['pin_code']),
            'is_active' => true,
        ]);

        return back()->with('status', "Staff member \"{$v['name']}\" added with PIN {$v['pin_code']}.");
    }

    public function updateStaff(Request $request, StaffUser $staffUser): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:owner,cashier,kitchen,waiter'],
            'pin_code' => ['required', 'digits_between:4,6', 'unique:staff_users,pin_code,'.$staffUser->id],
            'is_active' => ['required', 'boolean'],
        ]);

        $staffUser->update([
            'name' => trim($v['name']),
            'role' => $v['role'],
            'pin_code' => trim($v['pin_code']),
            'is_active' => (bool) $v['is_active'],
        ]);

        return back()->with('status', "Updated staff \"{$staffUser->name}\".");
    }

    /* ================= ANTI-THEFT AUDIT LOG ================= */
    public function audit(Request $request): View
    {
        $actionFilter = (string) $request->query('action', 'all');
        $query = AuditLog::query()->orderByDesc('id');
        if ($actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        $logs = $query->paginate(40)->withQueryString();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('owner.audit', [
            'settings' => HotelSetting::allAsMap(),
            'logs' => $logs,
            'actions' => $actions,
            'actionFilter' => $actionFilter,
        ]);
    }

    /* ================= HOTEL SETTINGS & DATABASE BACKUP ================= */
    public function settings(): View
    {
        return view('owner.settings', [
            'settings' => HotelSetting::allAsMap(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'hotel_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:250'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'gst_enabled' => ['nullable', 'in:0,1'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:28'],
            'paper_width' => ['required', 'in:58,80'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'bill_footer' => ['nullable', 'string', 'max:200'],
            'pos_mode' => ['nullable', 'in:outlet,cloud_hub'],
            'outlet_code' => ['nullable', 'string', 'max:40'],
            'outlet_city' => ['nullable', 'string', 'max:80'],
            'outlet_state' => ['nullable', 'string', 'max:80'],
            'cloud_sync_enabled' => ['nullable', 'in:0,1'],
            'cloud_sync_url' => ['nullable', 'url', 'max:255'],
            'cloud_sync_token' => ['nullable', 'string', 'max:180'],
            'nightly_sync_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'gemini_api_key' => ['nullable', 'string', 'max:200'],
            'gemini_model' => ['nullable', 'string', 'max:60'],
            'ai_auto_nightly' => ['nullable', 'in:0,1'],
        ]);

        $v['gst_enabled'] = $request->boolean('gst_enabled') ? '1' : '0';
        if ($request->has('pos_mode')) {
            $v['cloud_sync_enabled'] = $request->boolean('cloud_sync_enabled') ? '1' : '0';
            $v['ai_auto_nightly'] = $request->boolean('ai_auto_nightly') ? '1' : '0';
        }

        // Preserve existing secrets if left blank during a general settings update
        if (array_key_exists('cloud_sync_token', $v) && trim((string) $v['cloud_sync_token']) === '' && ! $request->boolean('clear_cloud_sync_token')) {
            unset($v['cloud_sync_token']);
        }
        if (array_key_exists('gemini_api_key', $v) && trim((string) $v['gemini_api_key']) === '' && ! $request->boolean('clear_gemini_api_key')) {
            unset($v['gemini_api_key']);
        }

        if (! empty($v['outlet_code'])) {
            $v['outlet_code'] = strtoupper(trim((string) $v['outlet_code']));
        }

        foreach ($v as $k => $val) {
            HotelSetting::setValue($k, (string) ($val ?? ''));
        }

        app(AnalyticsService::class)->ensureLocalOutlet();

        return back()->with('status', 'Hotel, Thermal Printer & Hybrid Cloud AI settings saved!');
    }

    public function downloadBackup(Request $request): StreamedResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $this->pos->logAudit($staff, 'backup_downloaded', 'database', null, ['driver' => config('database.default')]);

        $filename = 'hotel_pos_backup_'.now()->format('Y-m-d_His').'.json';

        return response()->streamDownload(function () {
            $payload = [
                'exported_at' => now()->toIso8601String(),
                'hotel_settings' => HotelSetting::query()->get()->toArray(),
                'staff_users' => StaffUser::query()->get()->toArray(),
                'dining_tables' => DiningTable::query()->get()->toArray(),
                'menu_categories' => MenuCategory::query()->get()->toArray(),
                'menu_items' => MenuItem::query()->get()->toArray(),
                'orders' => Order::query()->get()->toArray(),
                'kots' => DB::table('kots')->get()->toArray(),
                'order_items' => OrderItem::query()->get()->toArray(),
                'audit_logs' => AuditLog::query()->orderByDesc('id')->limit(1000)->get()->toArray(),
            ];
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function resolveDateRange(Request $request): array
    {
        $range = (string) $request->query('range', 'today');

        return match ($range) {
            'yesterday' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay(), 'yesterday'],
            '7days' => [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay(), '7days'],
            'month' => [Carbon::today()->startOfMonth(), Carbon::today()->endOfDay(), 'month'],
            'custom' => [
                Carbon::parse($request->query('from', today()->toDateString()))->startOfDay(),
                Carbon::parse($request->query('to', today()->toDateString()))->endOfDay(),
                'custom',
            ],
            default => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), 'today'],
        };
    }
}
