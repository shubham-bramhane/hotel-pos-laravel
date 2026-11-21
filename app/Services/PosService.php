<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosService
{
    public function openOrGetTableOrder(DiningTable $table, StaffUser $staff, int $guestCount = 2): Order
    {
        $existing = $table->activeOrder()->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($table, $staff, $guestCount) {
            $settings = HotelSetting::allAsMap();
            $gstRate = ($settings['gst_enabled'] ?? '1') === '1' ? (float) ($settings['gst_rate'] ?? 5) : 0.0;

            $order = Order::create([
                'order_no' => $this->nextOrderNo(),
                'table_id' => $table->id,
                'waiter_id' => $staff->id,
                'order_type' => 'dine_in',
                'guest_count' => max(1, $guestCount),
                'status' => 'open',
                'gst_rate' => $gstRate,
                'opened_at' => now(),
            ]);

            $table->update(['status' => 'occupied']);

            $this->logAudit($staff, 'order_opened', 'order', $order->id, [
                'order_no' => $order->order_no,
                'table' => $table->code,
                'guests' => $guestCount,
            ]);

            return $order;
        });
    }

    public function createTakeawayOrder(StaffUser $staff, ?string $customerName = null, ?string $customerPhone = null): Order
    {
        return DB::transaction(function () use ($staff, $customerName, $customerPhone) {
            $settings = HotelSetting::allAsMap();
            $gstRate = ($settings['gst_enabled'] ?? '1') === '1' ? (float) ($settings['gst_rate'] ?? 5) : 0.0;

            $order = Order::create([
                'order_no' => $this->nextOrderNo(),
                'table_id' => null,
                'waiter_id' => $staff->id,
                'order_type' => 'takeaway',
                'guest_count' => 1,
                'customer_name' => $customerName ? trim($customerName) : 'Walk-in Parcel',
                'customer_phone' => $customerPhone ? trim($customerPhone) : null,
                'status' => 'open',
                'gst_rate' => $gstRate,
                'opened_at' => now(),
            ]);

            $this->logAudit($staff, 'parcel_opened', 'order', $order->id, [
                'order_no' => $order->order_no,
                'customer' => $order->customer_name,
            ]);

            return $order;
        });
    }

    /**
     * Add items from waiter/counter cart and generate a Kitchen Order Ticket (KOT).
     *
     * @param  array<int, array{menu_item_id:int, quantity:int, note?:string}>  $cartItems
     */
    public function addItemsAndCreateKot(Order $order, array $cartItems, StaffUser $staff, ?string $kotNotes = null): Kot
    {
        if (in_array($order->status, ['paid', 'cancelled'], true)) {
            throw new RuntimeException('Cannot add items to a settled or cancelled order.');
        }

        $cleanItems = array_values(array_filter($cartItems, fn ($i) => (int) ($i['quantity'] ?? 0) > 0));
        if (empty($cleanItems)) {
            throw new RuntimeException('Please add at least one menu item before sending KOT.');
        }

        return DB::transaction(function () use ($order, $cleanItems, $staff, $kotNotes) {
            $kot = Kot::create([
                'kot_no' => $this->nextKotNo(),
                'order_id' => $order->id,
                'created_by_id' => $staff->id,
                'status' => 'pending',
                'notes' => $kotNotes ? trim($kotNotes) : null,
            ]);

            $itemSummary = [];
            foreach ($cleanItems as $entry) {
                $menuItem = MenuItem::query()->findOrFail((int) $entry['menu_item_id']);
                $qty = max(1, (int) $entry['quantity']);
                $lineTotal = round($menuItem->price * $qty, 2);

                OrderItem::create([
                    'order_id' => $order->id,
                    'kot_id' => $kot->id,
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'is_veg' => $menuItem->is_veg,
                    'unit_price' => $menuItem->price,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                    'special_note' => isset($entry['note']) && trim((string) $entry['note']) !== '' ? trim((string) $entry['note']) : null,
                    'status' => 'pending',
                ]);

                $itemSummary[] = "{$menuItem->name} x{$qty}";
            }

            if ($order->status === 'billing') {
                $order->status = 'open';
                $order->save();
            }
            if ($order->table) {
                $order->table->update(['status' => 'occupied']);
            }

            $this->recalculateOrder($order);

            $this->logAudit($staff, 'kot_sent', 'kot', $kot->id, [
                'kot_no' => $kot->kot_no,
                'order_no' => $order->order_no,
                'table' => $order->label(),
                'items' => $itemSummary,
            ]);

            return $kot->load('items');
        });
    }

    public function updateKotStatus(Kot $kot, string $newStatus, StaffUser $staff): Kot
    {
        if (! in_array($newStatus, ['pending', 'preparing', 'ready', 'served'], true)) {
            throw new RuntimeException('Invalid KOT status.');
        }

        return DB::transaction(function () use ($kot, $newStatus, $staff) {
            $updates = ['status' => $newStatus];
            if ($newStatus === 'ready' && $kot->ready_at === null) {
                $updates['ready_at'] = now();
            }
            if ($newStatus === 'served' && $kot->served_at === null) {
                $updates['served_at'] = now();
            }

            $kot->update($updates);
            $kot->items()->where('status', '!=', 'cancelled')->update(['status' => $newStatus]);

            $this->logAudit($staff, 'kot_status_updated', 'kot', $kot->id, [
                'kot_no' => $kot->kot_no,
                'status' => $newStatus,
            ]);

            return $kot->fresh('items');
        });
    }

    public function requestBill(Order $order, StaffUser $staff): Order
    {
        return DB::transaction(function () use ($order, $staff) {
            $order->update(['status' => 'billing']);
            if ($order->table) {
                $order->table->update(['status' => 'billing']);
            }

            $this->logAudit($staff, 'bill_requested', 'order', $order->id, [
                'order_no' => $order->order_no,
                'table' => $order->label(),
                'grand_total' => $order->grand_total,
            ]);

            return $order->fresh();
        });
    }

    public function cancelOrderItem(OrderItem $item, string $reason, StaffUser $staff): Order
    {
        return DB::transaction(function () use ($item, $reason, $staff) {
            $order = $item->order;
            if (in_array($order->status, ['paid', 'cancelled'], true)) {
                throw new RuntimeException('Cannot cancel items on a closed order.');
            }

            $item->update([
                'status' => 'cancelled',
                'cancel_reason' => trim($reason) ?: 'Cancelled at counter',
            ]);

            // If all items of a KOT are cancelled, mark the KOT cancelled too
            if ($item->kot && $item->kot->items()->where('status', '!=', 'cancelled')->count() === 0) {
                $item->kot->update(['status' => 'cancelled']);
            }

            $this->recalculateOrder($order);

            $this->logAudit($staff, 'item_cancelled', 'order_item', $item->id, [
                'order_no' => $order->order_no,
                'item' => $item->item_name,
                'qty' => $item->quantity,
                'amount' => $item->line_total,
                'reason' => $item->cancel_reason,
            ]);

            return $order->fresh();
        });
    }

    public function applyDiscount(Order $order, string $type, float $value, string $reason, StaffUser $staff): Order
    {
        if (! in_array($type, ['none', 'flat', 'percent'], true)) {
            throw new RuntimeException('Invalid discount type.');
        }

        return DB::transaction(function () use ($order, $type, $value, $reason, $staff) {
            $cleanVal = $type === 'none' ? 0.0 : max(0.0, $value);
            if ($type === 'percent') {
                $cleanVal = min(100.0, $cleanVal);
            }

            $order->update([
                'discount_type' => $type,
                'discount_value' => $cleanVal,
            ]);

            $this->recalculateOrder($order);

            $this->logAudit($staff, 'discount_applied', 'order', $order->id, [
                'order_no' => $order->order_no,
                'type' => $type,
                'value' => $cleanVal,
                'discount_amount' => $order->discount_amount,
                'reason' => trim($reason) ?: 'Counter discount',
            ]);

            return $order->fresh();
        });
    }

    public function transferTable(Order $order, DiningTable $targetTable, StaffUser $staff): Order
    {
        if ($order->order_type !== 'dine_in') {
            throw new RuntimeException('Only Dine-In orders can be transferred.');
        }
        if ($targetTable->activeOrder()->exists()) {
            throw new RuntimeException("Table {$targetTable->code} already has an active order.");
        }

        return DB::transaction(function () use ($order, $targetTable, $staff) {
            $oldTable = $order->table;
            $oldCode = $oldTable ? $oldTable->code : 'None';

            $order->update(['table_id' => $targetTable->id]);
            if ($oldTable) {
                $oldTable->update(['status' => 'available']);
            }
            $targetTable->update(['status' => $order->status === 'billing' ? 'billing' : 'occupied']);

            $this->logAudit($staff, 'table_transferred', 'order', $order->id, [
                'order_no' => $order->order_no,
                'from' => $oldCode,
                'to' => $targetTable->code,
            ]);

            return $order->fresh('table');
        });
    }

    public function settleOrder(Order $order, string $method, float $cashAmt, float $upiAmt, StaffUser $staff): Order
    {
        if (in_array($order->status, ['paid', 'cancelled'], true)) {
            throw new RuntimeException('Order is already closed.');
        }

        return DB::transaction(function () use ($order, $method, $cashAmt, $upiAmt, $staff) {
            $this->recalculateOrder($order);
            $grand = (float) $order->grand_total;

            $finalCash = match ($method) {
                'cash' => $grand,
                'split' => max(0, $cashAmt),
                default => 0.0,
            };
            $finalUpi = match ($method) {
                'upi' => $grand,
                'split' => max(0, $upiAmt > 0 ? $upiAmt : max(0, $grand - $finalCash)),
                default => 0.0,
            };

            $order->update([
                'status' => 'paid',
                'payment_method' => $method,
                'cash_amount' => round($finalCash, 2),
                'upi_amount' => round($finalUpi, 2),
                'settled_by_id' => $staff->id,
                'paid_at' => now(),
            ]);

            $order->kots()->whereIn('status', ['pending', 'preparing', 'ready'])->update([
                'status' => 'served',
                'served_at' => now(),
            ]);
            $order->items()->where('status', '!=', 'cancelled')->update(['status' => 'served']);

            if ($order->table) {
                $order->table->update(['status' => 'available']);
            }

            $this->logAudit($staff, 'bill_settled', 'order', $order->id, [
                'order_no' => $order->order_no,
                'table' => $order->label(),
                'payment_method' => $method,
                'grand_total' => $grand,
            ]);

            return $order->fresh();
        });
    }

    public function cancelOrder(Order $order, string $reason, StaffUser $staff): Order
    {
        if ($order->status === 'paid') {
            throw new RuntimeException('Cannot cancel an already paid bill.');
        }

        return DB::transaction(function () use ($order, $reason, $staff) {
            $order->update([
                'status' => 'cancelled',
                'notes' => trim(($order->notes ? $order->notes.' | ' : '').'Cancelled: '.($reason ?: 'No reason')),
            ]);
            $order->kots()->update(['status' => 'cancelled']);
            $order->items()->update([
                'status' => 'cancelled',
                'cancel_reason' => trim($reason) ?: 'Order cancelled',
            ]);

            if ($order->table) {
                $order->table->update(['status' => 'available']);
            }

            $this->logAudit($staff, 'order_cancelled', 'order', $order->id, [
                'order_no' => $order->order_no,
                'table' => $order->label(),
                'reason' => trim($reason) ?: 'Order cancelled',
            ]);

            return $order->fresh();
        });
    }

    public function recalculateOrder(Order $order): Order
    {
        $subtotal = (float) $order->items()->where('status', '!=', 'cancelled')->sum('line_total');

        $discountAmt = 0.0;
        if ($order->discount_type === 'flat') {
            $discountAmt = min($subtotal, max(0.0, (float) $order->discount_value));
        } elseif ($order->discount_type === 'percent') {
            $discountAmt = round($subtotal * (min(100.0, max(0.0, (float) $order->discount_value)) / 100), 2);
        }

        $taxable = max(0.0, $subtotal - $discountAmt);
        $gstRate = max(0.0, (float) $order->gst_rate);
        $halfRate = $gstRate / 2;
        $cgst = round($taxable * ($halfRate / 100), 2);
        $sgst = round($taxable * ($halfRate / 100), 2);

        $rawTotal = $taxable + $cgst + $sgst;
        $rounded = round($rawTotal);
        $roundOff = round($rounded - $rawTotal, 2);

        $order->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmt, 2),
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'round_off' => $roundOff,
            'grand_total' => $rounded,
        ]);

        return $order;
    }

    public function logAudit(StaffUser $staff, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        AuditLog::create([
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'staff_role' => $staff->role,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => request()?->ip(),
        ]);
    }

    private function nextOrderNo(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';
        $countToday = Order::query()->where('order_no', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($countToday + 1), 3, '0', STR_PAD_LEFT);
    }

    private function nextKotNo(): string
    {
        $countToday = Kot::query()->whereDate('created_at', today())->count();

        return 'KOT-'.str_pad((string) ($countToday + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function formatINR(float|int $amount): string
    {
        $negative = $amount < 0;
        $abs = abs((float) $amount);
        $hasDecimals = fmod($abs, 1.0) !== 0.0;
        $str = number_format($abs, $hasDecimals ? 2 : 0, '.', '');
        [$intPart, $decPart] = array_pad(explode('.', $str), 2, '');

        if (strlen($intPart) > 3) {
            $lastThree = substr($intPart, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($intPart, 0, -3));
            $intPart = $rest.','.$lastThree;
        }

        return ($negative ? '-₹' : '₹').$intPart.($decPart !== '' ? '.'.$decPart : '');
    }
}
