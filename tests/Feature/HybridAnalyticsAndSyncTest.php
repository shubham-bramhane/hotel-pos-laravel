<?php

namespace Tests\Feature;

use App\Models\AiInsightReport;
use App\Models\CloudSyncLog;
use App\Models\DailyAnalyticsSnapshot;
use App\Models\DiningTable;
use App\Models\HotelSetting;
use App\Models\InvestorShareLink;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\StaffUser;
use App\Services\PosService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HybridAnalyticsAndSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createSettledSampleOrders(): void
    {
        $pos = app(PosService::class);
        $waiter = StaffUser::where('pin_code', '4444')->firstOrFail();
        $cashier = StaffUser::where('pin_code', '2222')->firstOrFail();
        $t1 = DiningTable::where('code', 'T1')->firstOrFail();
        $pbm = MenuItem::where('short_code', 'PBM')->firstOrFail();
        $bn = MenuItem::where('short_code', 'BN')->firstOrFail();
        $cbr = MenuItem::where('short_code', 'CBR')->firstOrFail();

        // Order 1: Dine-in settled via Split Cash + UPI
        $dineOrder = $pos->openOrGetTableOrder($t1, $waiter, 4);
        $pos->addItemsAndCreateKot($dineOrder, [
            ['menu_item_id' => $pbm->id, 'quantity' => 2],
            ['menu_item_id' => $bn->id, 'quantity' => 4],
        ], $waiter);
        $pos->settleOrder($dineOrder->fresh(), 'split', 300, 393, $cashier);

        // Order 2: Takeaway Parcel settled via UPI
        $parcel = $pos->createTakeawayOrder($cashier, 'Ramesh Kulkarni', '9999999999');
        $pos->addItemsAndCreateKot($parcel, [
            ['menu_item_id' => $cbr->id, 'quantity' => 2],
        ], $cashier);
        $pos->settleOrder($parcel->fresh(), 'upi', 0, 546, $cashier);
    }

    public function test_owner_analytics_dashboard_builds_offline_snapshots_and_enforces_rbac(): void
    {
        $this->createSettledSampleOrders();

        // Waiter cannot open AI Analytics
        $this->post('/login', ['pin' => '4444'])->assertRedirect('/waiter');
        $this->get('/owner/analytics')->assertRedirect('/waiter');
        $this->post('/logout');

        // Owner accesses AI Analytics dashboard
        $this->post('/login', ['pin' => '1111'])->assertRedirect('/owner');
        $response = $this->get('/owner/analytics?range=30days');
        $response->assertOk()
            ->assertSee('AI Performance Analytics')
            ->assertSee('Payment Mode Split (Pie Chart)')
            ->assertSee('Menu Category Mix (Pie Chart)')
            ->assertSee('Peak Dining Hours Distribution')
            ->assertSee('₹1,239'); // 693 + 546

        $snapshot = DailyAnalyticsSnapshot::firstOrFail();
        $this->assertEquals(2, $snapshot->total_orders);
        $this->assertEquals(1, $snapshot->dine_in_orders);
        $this->assertEquals(1, $snapshot->takeaway_orders);
        $this->assertEquals(1239.0, $snapshot->net_revenue);
        $this->assertEquals('pending', $snapshot->sync_status);
        $this->assertNotEmpty($snapshot->category_sales);
        $this->assertNotEmpty($snapshot->top_dishes);
    }

    public function test_ai_insights_support_gemini_interactions_api_and_offline_fallback(): void
    {
        $this->createSettledSampleOrders();
        $this->post('/login', ['pin' => '1111']);

        // 1. No API key configured -> automatic Offline Statistical Fallback
        $this->post('/owner/analytics/generate-ai', ['range' => '30days'])
            ->assertRedirect();

        $fallbackReport = AiInsightReport::latest()->firstOrFail();
        $this->assertEquals('local_fallback', $fallbackReport->provider);
        $this->assertGreaterThanOrEqual(50, $fallbackReport->health_score);
        $this->assertNotEmpty($fallbackReport->highlights);
        $this->assertNotEmpty($fallbackReport->investor_narrative);

        // 2. Gemini API key configured -> calls Google Gemini Interactions API (v1), then falls back on 503
        HotelSetting::setValue('gemini_api_key', 'test-gemini-secret-key');
        HotelSetting::setValue('gemini_model', 'gemini-4-hydrogen');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::sequence()
                ->push([
                    'id' => 'v1_test_interaction',
                    'status' => 'completed',
                    'model' => 'gemini-4-hydrogen',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode([
                                        'executive_summary' => 'Strong unit economics with 75.8% digital UPI adoption across settled orders.',
                                        'health_score' => 89,
                                        'highlights' => ['UPI digital share reached 75.8%', 'Healthy ₹620 average ticket size'],
                                        'risks' => ['Monitor peak dinner prep time'],
                                        'recommendations' => ['Launch paneer family combo for weekend uplift'],
                                        'investor_narrative' => 'Verifiable digital cash flows and zero-downtime LAN POS make this chain expansion-ready.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push(['error' => 'Unavailable'], 503),
        ]);

        $this->post('/owner/analytics/generate-ai', ['range' => '30days'])
            ->assertRedirect();

        Http::assertSent(function (HttpClientRequest $req): bool {
            return $req->url() === 'https://generativelanguage.googleapis.com/v1/interactions'
                && $req->hasHeader('x-goog-api-key', 'test-gemini-secret-key')
                && $req['model'] === 'gemini-4-hydrogen';
        });

        $geminiReport = AiInsightReport::query()->orderByDesc('id')->firstOrFail();
        $this->assertEquals('gemini', $geminiReport->provider);
        $this->assertEquals('gemini-4-hydrogen', $geminiReport->model_name);
        $this->assertEquals(89, $geminiReport->health_score);
        $this->assertStringContainsString('75.8% digital UPI adoption', $geminiReport->executive_summary);

        // 3. Gemini API offline/503 failure -> gracefully falls back without crashing
        $this->post('/owner/analytics/generate-ai', ['range' => '30days'])
            ->assertRedirect();

        $resilientReport = AiInsightReport::query()->orderByDesc('id')->firstOrFail();
        $this->assertEquals('local_fallback', $resilientReport->provider);
    }

    public function test_nightly_cloud_sync_outbound_and_inbound_multi_outlet_hub(): void
    {
        $this->createSettledSampleOrders();

        HotelSetting::setValue('cloud_sync_enabled', '1');
        HotelSetting::setValue('cloud_sync_url', 'https://hub.royalhotelpos.test/api/cloud/sync');
        HotelSetting::setValue('cloud_sync_token', 'super-secret-sync-token-2026');

        // 1. Outbound sync failure (503 offline) queues safely, then succeeds on retry (200)
        Http::fake([
            'https://hub.royalhotelpos.test/api/cloud/sync' => Http::sequence()
                ->push(['message' => 'Offline'], 503)
                ->push([
                    'ok' => true,
                    'outlet_code' => 'ROYAL-YTL-01',
                    'upserted' => 1,
                ], 200),
        ]);

        $this->artisan('pos:sync-cloud', ['--days' => 7, '--manual' => true])
            ->assertExitCode(1);

        $failedSnap = DailyAnalyticsSnapshot::firstOrFail();
        $this->assertEquals('failed', $failedSnap->sync_status);
        $this->assertEquals(1, $failedSnap->sync_attempts);

        // 2. Outbound sync succeeds when internet returns
        $this->artisan('pos:sync-cloud', ['--days' => 7, '--ai' => true, '--manual' => true])
            ->assertExitCode(0);

        Http::assertSent(function (HttpClientRequest $req): bool {
            return $req->url() === 'https://hub.royalhotelpos.test/api/cloud/sync'
                && $req->hasHeader('Authorization', 'Bearer super-secret-sync-token-2026')
                && $req->hasHeader('X-POS-Outlet-Code', 'ROYAL-YTL-01')
                && $req->hasHeader('X-POS-Signature');
        });

        $this->assertEquals('synced', $failedSnap->fresh()->sync_status);
        $this->assertNotNull($failedSnap->fresh()->synced_at);
        $this->assertTrue(CloudSyncLog::where('direction', 'outbound')->where('status', 'success')->exists());

        // 3. Cloud Hub Inbound API rejects bad token
        $this->postJson('/api/cloud/sync', ['outlet' => ['outlet_code' => 'ROYAL-PUN-02'], 'snapshots' => []], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertStatus(401);

        // 4. Cloud Hub Inbound API ingests signed sister-branch data for multi-outlet comparison
        $branchPayload = [
            'outlet' => [
                'outlet_code' => 'ROYAL-PUN-02',
                'name' => 'Royal Hotel Pune Highway',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'seating_capacity' => 84,
            ],
            'snapshots' => [
                [
                    'business_date' => today()->toDateString(),
                    'total_orders' => 18,
                    'dine_in_orders' => 14,
                    'takeaway_orders' => 4,
                    'gross_sales' => 14200,
                    'discount_total' => 200,
                    'gst_total' => 700,
                    'net_revenue' => 14700,
                    'avg_bill_value' => 816.67,
                    'cash_total' => 4700,
                    'upi_total' => 8500,
                    'card_total' => 1500,
                    'cancelled_orders' => 0,
                    'cancelled_items' => 1,
                    'guest_count' => 54,
                    'category_sales' => [['name' => 'Main Course', 'qty' => 28, 'revenue' => 9400]],
                    'top_dishes' => [['name' => 'Paneer Butter Masala', 'qty' => 15, 'revenue' => 3600, 'is_veg' => true]],
                    'hourly_sales' => [['hour' => 20, 'orders' => 9, 'revenue' => 7800]],
                    'waiter_performance' => [['name' => 'Waiter Vikram', 'orders' => 18, 'revenue' => 14700]],
                    'veg_nonveg_split' => ['veg_qty' => 25, 'veg_revenue' => 9200, 'nonveg_qty' => 12, 'nonveg_revenue' => 5000],
                ],
            ],
        ];

        $raw = (string) json_encode($branchPayload);
        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $ts.'.'.$raw, 'super-secret-sync-token-2026');

        $this->call('POST', '/api/cloud/sync', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer super-secret-sync-token-2026',
            'HTTP_X_POS_TIMESTAMP' => $ts,
            'HTTP_X_POS_SIGNATURE' => $sig,
        ], $raw)->assertOk()->assertJsonPath('outlet_code', 'ROYAL-PUN-02')->assertJsonPath('upserted', 1);

        $this->assertEquals(2, Outlet::count());
        $this->getJson('/api/cloud/health')->assertOk()->assertJsonPath('outlets_connected', 2);

        // Owner sees BOTH outlets aggregated in portfolio view
        $this->post('/login', ['pin' => '1111']);
        $this->get('/owner/analytics?range=30days')
            ->assertOk()
            ->assertSee('Royal Hotel Pune Highway')
            ->assertSee('ROYAL-PUN-02')
            ->assertSee('₹15,939'); // 1239 + 14700
    }

    public function test_investor_share_link_creation_public_portal_and_revocation(): void
    {
        $this->createSettledSampleOrders();
        $this->post('/login', ['pin' => '1111']);

        $this->post('/owner/analytics/share-links', [
            'title' => 'Seed Funding Expansion Deck Q4',
            'investor_name' => 'Maharashtra Angel Syndicate',
            'period_days' => 30,
            'expires_in_days' => 14,
            'include_ai_insights' => '1',
            'include_branch_comparison' => '1',
        ])->assertRedirect();

        $link = InvestorShareLink::firstOrFail();

        // Log out so we simulate an external unauthenticated investor opening the share link
        $this->post('/logout');

        $this->get("/investor/{$link->token}")
            ->assertOk()
            ->assertSee('Seed Funding Expansion Deck Q4')
            ->assertSee('Maharashtra Angel Syndicate')
            ->assertSee('₹1,239')
            ->assertSee('AI EXECUTIVE')
            ->assertDontSee('9999999999'); // Customer phone PII is never exposed

        $this->assertEquals(1, $link->fresh()->views_count);

        // Owner revokes link -> investor immediately sees 404 unavailable page
        $this->post('/login', ['pin' => '1111']);
        $this->patch("/owner/analytics/share-links/{$link->id}/toggle")->assertRedirect();
        $this->post('/logout');

        $this->get("/investor/{$link->token}")
            ->assertStatus(404)
            ->assertSee('Investor Report Unavailable');
    }
}
