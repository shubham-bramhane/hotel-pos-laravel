<?php

namespace App\Http\Controllers;

use App\Models\HotelSetting;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\StaffUser;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class KitchenController extends Controller
{
    public function __construct(
        private readonly PosService $pos
    ) {}

    public function index(): View
    {
        $kots = $this->fetchActiveKots();
        $menuItems = MenuItem::query()->with('category')->orderBy('name')->get();

        return view('kitchen.index', [
            'settings' => HotelSetting::allAsMap(),
            'kots' => $kots,
            'menuItems' => $menuItems,
            'latestKotId' => $kots->max('id') ?? 0,
        ]);
    }

    public function live(): JsonResponse
    {
        $kots = $this->fetchActiveKots();

        return response()->json([
            'server_time' => now()->format('h:i:s A'),
            'latest_kot_id' => $kots->max('id') ?? 0,
            'counts' => [
                'pending' => $kots->where('status', 'pending')->count(),
                'preparing' => $kots->where('status', 'preparing')->count(),
                'ready' => $kots->where('status', 'ready')->count(),
            ],
            'kots' => $kots->map(function (Kot $k) {
                $ord = $k->order;

                return [
                    'id' => $k->id,
                    'kot_no' => $k->kot_no,
                    'status' => $k->status,
                    'table_label' => $ord ? $ord->label() : 'Order',
                    'order_type' => $ord?->order_type ?? 'dine_in',
                    'waiter' => $k->createdBy?->name ?? 'Staff',
                    'notes' => $k->notes,
                    'created_time' => $k->created_at?->format('h:i A'),
                    'elapsed_mins' => $k->created_at ? (int) $k->created_at->diffInMinutes(now()) : 0,
                    'print_url' => route('print.kot', $k),
                    'status_url' => route('kitchen.kot.status', $k),
                    'items' => $k->items->where('status', '!=', 'cancelled')->values()->map(fn ($i) => [
                        'name' => $i->item_name,
                        'qty' => $i->quantity,
                        'is_veg' => $i->is_veg,
                        'note' => $i->special_note,
                    ]),
                ];
            })->values(),
        ]);
    }

    public function updateStatus(Request $request, Kot $kot): RedirectResponse|JsonResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $validated = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served'],
        ]);

        try {
            $updated = $this->pos->updateKotStatus($kot, $validated['status'], $staff);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'kot_id' => $updated->id, 'status' => $updated->status]);
            }

            return back()->with('status', "{$kot->kot_no} marked as ".strtoupper($validated['status']));
        } catch (Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function toggleItemStock(Request $request, MenuItem $item): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $item->update(['is_available' => ! $item->is_available]);

        $this->pos->logAudit($staff, 'menu_stock_toggled', 'menu_item', $item->id, [
            'item' => $item->name,
            'is_available' => $item->is_available,
        ]);

        return back()->with('status', $item->name.' marked '.($item->is_available ? 'IN STOCK' : 'OUT OF STOCK').'.');
    }

    private function fetchActiveKots()
    {
        return Kot::query()
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['open', 'billing']))
            ->with(['order.table', 'createdBy', 'items'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'preparing' THEN 2 WHEN 'ready' THEN 3 ELSE 4 END")
            ->orderBy('id')
            ->get();
    }
}
