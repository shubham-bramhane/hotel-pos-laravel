<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelPosFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_full_restaurant_lifecycle_waiter_to_kitchen_to_cashier_to_owner(): void
    {
        // 1. Unauthenticated redirects to PIN login
        $this->get('/waiter')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Royal Family Restaurant');

        // 2. Waiter logs in with PIN 4444 (blocked from Owner & Counter!)
        $this->post('/login', ['pin' => '4444'])->assertRedirect('/waiter');
        $this->get('/waiter')->assertOk()->assertSee('T1');
        $this->get('/owner')->assertRedirect('/waiter');
        $this->get('/counter')->assertRedirect('/waiter');

        // 3. Waiter opens Table T1 and sends KOT with 2 dishes
        $t1 = DiningTable::where('code', 'T1')->firstOrFail();
        $t2 = DiningTable::where('code', 'T2')->firstOrFail();
        $pbm = MenuItem::where('short_code', 'PBM')->firstOrFail();
        $bn = MenuItem::where('short_code', 'BN')->firstOrFail();

        $this->get("/waiter/table/{$t1->id}")->assertOk()->assertSee('Paneer Butter Masala');
        $resp = $this->post("/waiter/table/{$t1->id}/kot", [
            'guest_count' => 4,
            'kot_notes' => 'Serve hot',
            'cart_json' => json_encode([
                ['menu_item_id' => $pbm->id, 'quantity' => 2, 'note' => 'Less spicy'],
                ['menu_item_id' => $bn->id, 'quantity' => 4, 'note' => 'Extra butter'],
            ]),
        ]);
        $resp->assertRedirect("/waiter/table/{$t1->id}");
        $this->assertNull(session('error'), 'Unexpected error: '.(string) session('error'));

        $order = Order::firstOrFail();
        $kot = Kot::firstOrFail();
        $this->assertEquals('occupied', $t1->fresh()->status);
        $this->assertEquals(660.0, (float) $order->subtotal); // 240*2 + 45*4 = 660
        $this->assertEquals(693.0, (float) $order->grand_total); // +5% GST = 693

        // Waiter requests bill
        $this->post("/waiter/order/{$order->id}/request-bill")->assertRedirect();
        $this->assertEquals('billing', $order->fresh()->status);

        // 4. Kitchen logs in with PIN 3333 -> polls live API -> marks Preparing -> Ready
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['pin' => '3333'])->assertRedirect('/kitchen');
        $this->get('/kitchen')->assertOk()->assertSee('Paneer Butter Masala');
        $this->getJson('/api/kitchen/live')
            ->assertOk()
            ->assertJsonPath('counts.pending', 1);

        $this->patchJson("/kitchen/kot/{$kot->id}/status", ['status' => 'preparing'])->assertOk();
        $this->patchJson("/kitchen/kot/{$kot->id}/status", ['status' => 'ready'])->assertOk();
        $this->assertEquals('ready', $kot->fresh()->status);

        // 5. Cashier logs in with PIN 2222 -> Transfers T1->T2 -> Applies Discount -> Settles Split Bill
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['pin' => '2222'])->assertRedirect('/counter');
        $this->get("/counter/order/{$order->id}")->assertOk()->assertSee($order->order_no);

        // Transfer table T1 -> T2
        $this->post("/counter/order/{$order->id}/transfer", ['target_table_id' => $t2->id])->assertRedirect();
        $this->assertEquals('available', $t1->fresh()->status);
        $this->assertEquals($t2->id, $order->fresh()->table_id);

        // Cancel one item & apply flat ₹60 discount
        $bnItem = OrderItem::where('menu_item_id', $bn->id)->firstOrFail();
        $this->post("/counter/item/{$bnItem->id}/cancel", ['reason' => 'Guest cancelled naan'])->assertRedirect();
        $this->post("/counter/order/{$order->id}/discount", [
            'discount_type' => 'flat',
            'discount_value' => 80,
            'reason' => 'Regular guest',
        ])->assertRedirect();

        // Subtotal now 480 - 80 discount = 400 + 5% GST (20) = 420
        $this->assertEquals(420.0, (float) $order->fresh()->grand_total);

        // Settle via Split Cash (200) + UPI (220)
        $this->post("/counter/order/{$order->id}/settle", [
            'payment_method' => 'split',
            'cash_amount' => 200,
            'upi_amount' => 220,
        ])->assertRedirect('/counter');

        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals('available', $t2->fresh()->status);

        // Thermal Print Views
        $this->get("/print/bill/{$order->id}")->assertOk()->assertSee('NET PAYABLE')->assertSee('420.00');
        $this->get("/print/kot/{$kot->id}")->assertOk()->assertSee('KITCHEN KOT');

        // 6. Owner logs in with PIN 1111 -> Checks Reports, Audit Trail, Export CSV & Backup
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['pin' => '1111'])->assertRedirect('/owner');
        $this->get('/owner')->assertOk()->assertSee('₹420')->assertSee('Paneer Butter Masala');
        $this->get('/owner/menu')->assertOk();
        $this->get('/owner/tables')->assertOk();
        $this->get('/owner/staff')->assertOk();
        $this->get('/owner/audit')->assertOk()->assertSee('item_cancelled')->assertSee('discount_applied');
        $this->get('/owner/settings')->assertOk();

        // Verify CSV export & Backup download
        $csv = $this->get('/owner/export-sales')->assertOk()->streamedContent();
        $this->assertStringContainsString($order->order_no, $csv);

        $backup = $this->post('/owner/backup')->assertOk()->streamedContent();
        $this->assertStringContainsString('Royal Family Restaurant', $backup);

        $this->assertTrue(AuditLog::where('action', 'item_cancelled')->exists());
        $this->assertTrue(AuditLog::where('action', 'bill_settled')->exists());
    }
}
