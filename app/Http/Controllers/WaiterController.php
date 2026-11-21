<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\StaffUser;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WaiterController extends Controller
{
    public function __construct(
        private readonly PosService $pos
    ) {}

    public function index(): View
    {
        $tables = DiningTable::query()
            ->where('is_active', true)
            ->with(['activeOrder.kots', 'activeOrder.waiter'])
            ->orderBy('sort_order')
            ->get();

        $sections = $tables->groupBy('section');
        $readyKotsCount = $tables->sum(function ($t) {
            return $t->activeOrder
                ? $t->activeOrder->kots->where('status', 'ready')->count()
                : 0;
        });

        return view('waiter.index', [
            'settings' => HotelSetting::allAsMap(),
            'sections' => $sections,
            'tables' => $tables,
            'readyKotsCount' => $readyKotsCount,
        ]);
    }

    public function table(DiningTable $table): View
    {
        $order = $table->activeOrder()
            ->with(['kots.items', 'items', 'waiter'])
            ->first();

        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        return view('waiter.table', [
            'settings' => HotelSetting::allAsMap(),
            'table' => $table,
            'order' => $order,
            'categories' => $categories,
        ]);
    }

    public function sendKot(Request $request, DiningTable $table): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');

        $validated = $request->validate([
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'kot_notes' => ['nullable', 'string', 'max:250'],
            'cart_json' => ['required', 'string'],
        ]);

        $cart = json_decode($validated['cart_json'], true);
        if (! is_array($cart) || empty($cart)) {
            return back()->with('error', 'Please select at least 1 dish before sending KOT.');
        }

        try {
            $order = $this->pos->openOrGetTableOrder($table, $staff, (int) ($validated['guest_count'] ?? 2));
            $kot = $this->pos->addItemsAndCreateKot($order, $cart, $staff, $validated['kot_notes'] ?? null);

            return redirect()->route('waiter.table', $table)
                ->with('status', "{$kot->kot_no} sent to Kitchen for {$table->code}!");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function requestBill(Request $request, Order $order): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');

        try {
            $this->pos->requestBill($order, $staff);

            return back()->with('status', "Bill requested for {$order->label()} — Cashier notified!");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function liveTables(): JsonResponse
    {
        $tables = DiningTable::query()
            ->where('is_active', true)
            ->with(['activeOrder.kots', 'activeOrder.waiter'])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'time' => now()->format('h:i:s A'),
            'tables' => $tables->map(function (DiningTable $t) {
                $ord = $t->activeOrder;

                return [
                    'id' => $t->id,
                    'code' => $t->code,
                    'status' => $ord ? ($ord->status === 'billing' ? 'billing' : 'occupied') : 'available',
                    'grand_total' => $ord ? (float) $ord->grand_total : 0,
                    'grand_total_fmt' => $ord ? PosService::formatINR($ord->grand_total) : '',
                    'waiter' => $ord?->waiter?->name,
                    'ready_kots' => $ord ? $ord->kots->where('status', 'ready')->count() : 0,
                    'pending_kots' => $ord ? $ord->kots->whereIn('status', ['pending', 'preparing'])->count() : 0,
                    'minutes' => $ord && $ord->opened_at ? (int) $ord->opened_at->diffInMinutes(now()) : 0,
                ];
            }),
        ]);
    }
}
