<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\Kot;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StaffUser;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CounterController extends Controller
{
    public function __construct(
        private readonly PosService $pos
    ) {}

    public function index(Request $request, ?Order $order = null): View
    {
        $tables = DiningTable::query()
            ->where('is_active', true)
            ->with(['activeOrder.kots', 'activeOrder.waiter'])
            ->orderBy('sort_order')
            ->get();

        $activeParcels = Order::query()
            ->where('order_type', 'takeaway')
            ->whereIn('status', ['open', 'billing'])
            ->with(['kots', 'waiter'])
            ->orderByDesc('id')
            ->get();

        $recentBills = Order::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', today())
            ->with(['table', 'settledBy'])
            ->orderByDesc('paid_at')
            ->limit(15)
            ->get();

        if (! $order) {
            $firstActive = $tables->first(fn ($t) => $t->activeOrder !== null)?->activeOrder ?? $activeParcels->first();
            $order = $firstActive;
        }

        if ($order) {
            $order->load(['table', 'waiter', 'settledBy', 'kots.items', 'items']);
        }

        $emptyTables = $tables->filter(fn ($t) => $t->activeOrder === null);
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->with(['items' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        $todaySales = (float) Order::query()->where('status', 'paid')->whereDate('paid_at', today())->sum('grand_total');
        $todayBills = Order::query()->where('status', 'paid')->whereDate('paid_at', today())->count();

        return view('counter.index', [
            'settings' => HotelSetting::allAsMap(),
            'tables' => $tables,
            'emptyTables' => $emptyTables,
            'activeParcels' => $activeParcels,
            'recentBills' => $recentBills,
            'selectedOrder' => $order,
            'categories' => $categories,
            'todaySales' => $todaySales,
            'todayBills' => $todayBills,
        ]);
    }

    public function openTable(Request $request, DiningTable $table): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $order = $this->pos->openOrGetTableOrder($table, $staff, 2);

        return redirect()->route('counter.order', $order);
    }

    public function createParcel(Request $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $order = $this->pos->createTakeawayOrder(
            $staff,
            $validated['customer_name'] ?? null,
            $validated['customer_phone'] ?? null
        );

        return redirect()->route('counter.order', $order)
            ->with('status', "New Parcel Order {$order->order_no} created.");
    }

    public function addItems(Request $request, Order $order): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        try {
            $kot = $this->pos->addItemsAndCreateKot($order, [[
                'menu_item_id' => $validated['menu_item_id'],
                'quantity' => $validated['quantity'],
                'note' => $validated['note'] ?? '',
            ]], $staff);

            return redirect()->route('counter.order', $order)
                ->with('status', "Item added & {$kot->kot_no} sent to Kitchen.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelItem(Request $request, OrderItem $item): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:160'],
        ]);

        try {
            $order = $this->pos->cancelOrderItem($item, $validated['reason'], $staff);

            return redirect()->route('counter.order', $order)
                ->with('status', "Cancelled {$item->item_name} (logged in Audit Trail).");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function applyDiscount(Request $request, Order $order): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'discount_type' => ['required', 'in:none,flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        try {
            $this->pos->applyDiscount(
                $order,
                $validated['discount_type'],
                (float) ($validated['discount_value'] ?? 0),
                (string) ($validated['reason'] ?? ''),
                $staff
            );

            return redirect()->route('counter.order', $order)
                ->with('status', 'Discount updated & bill recalculated.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function transferTable(Request $request, Order $order): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'target_table_id' => ['required', 'integer', 'exists:dining_tables,id'],
        ]);

        try {
            $target = DiningTable::query()->findOrFail((int) $validated['target_table_id']);
            $this->pos->transferTable($order, $target, $staff);

            return redirect()->route('counter.order', $order)
                ->with('status', "Order transferred to {$target->code}!");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function settle(Request $request, Order $order): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,upi,card,split'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'upi_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $settled = $this->pos->settleOrder(
                $order,
                $validated['payment_method'],
                (float) ($validated['cash_amount'] ?? 0),
                (float) ($validated['upi_amount'] ?? 0),
                $staff
            );

            return redirect()->route('counter.index')
                ->with('status', "Bill {$settled->order_no} settled (".PosService::formatINR($settled->grand_total).' via '.strtoupper($settled->payment_method).')!')
                ->with('print_bill_url', route('print.bill', $settled));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelOrder(Request $request, Order $order): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:160'],
        ]);

        try {
            $this->pos->cancelOrder($order, $validated['reason'], $staff);

            return redirect()->route('counter.index')
                ->with('status', "Order {$order->order_no} cancelled & table released.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function printBill(Order $order): View
    {
        $order->load(['table', 'waiter', 'settledBy', 'activeItems']);

        // Consolidate duplicate items cleanly for printed customer receipt
        $grouped = [];
        foreach ($order->activeItems as $item) {
            $key = $item->item_name.'|'.$item->unit_price;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $item->item_name,
                    'unit_price' => $item->unit_price,
                    'qty' => 0,
                    'total' => 0.0,
                ];
            }
            $grouped[$key]['qty'] += $item->quantity;
            $grouped[$key]['total'] += $item->line_total;
        }

        return view('print.bill', [
            'settings' => HotelSetting::allAsMap(),
            'order' => $order,
            'lines' => array_values($grouped),
        ]);
    }

    public function printKot(Kot $kot): View
    {
        $kot->load(['order.table', 'createdBy', 'items']);

        return view('print.kot', [
            'settings' => HotelSetting::allAsMap(),
            'kot' => $kot,
        ]);
    }

    public function liveSummary(): JsonResponse
    {
        $activeOrders = Order::query()->whereIn('status', ['open', 'billing'])->get();
        $billingCount = $activeOrders->where('status', 'billing')->count();
        $todaySales = (float) Order::query()->where('status', 'paid')->whereDate('paid_at', today())->sum('grand_total');
        $todayBills = Order::query()->where('status', 'paid')->whereDate('paid_at', today())->count();
        $latestKotId = Kot::query()->max('id') ?? 0;

        return response()->json([
            'active_count' => $activeOrders->count(),
            'billing_requests' => $billingCount,
            'today_sales_fmt' => PosService::formatINR($todaySales),
            'today_bills' => $todayBills,
            'latest_kot_id' => $latestKotId,
        ]);
    }
}
