<?php

namespace App\Services;

use App\Models\CloudSyncLog;
use App\Models\DailyAnalyticsSnapshot;
use App\Models\HotelSetting;
use App\Models\Outlet;
use App\Models\StaffUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CloudSyncService
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly GeminiAnalyticsService $ai,
        private readonly PosService $pos
    ) {}

    public function syncOutbound(
        int $lookbackDays = 7,
        string $trigger = 'scheduled',
        bool $generateAiAfter = false,
        ?StaffUser $staff = null
    ): CloudSyncLog {
        $startedAt = microtime(true);
        $settings = HotelSetting::allAsMap();
        $outlet = $this->analytics->ensureLocalOutlet();

        $from = Carbon::today()->subDays(max(0, $lookbackDays - 1))->startOfDay();
        $to = Carbon::today()->endOfDay();
        $this->analytics->syncLocalSnapshotsForRange($from, $to);

        $syncEnabled = ($settings['cloud_sync_enabled'] ?? '0') === '1';
        $syncUrl = trim($settings['cloud_sync_url'] ?? '');
        $syncToken = trim($settings['cloud_sync_token'] ?? '');

        if (! $syncEnabled || $syncUrl === '' || $syncToken === '') {
            $log = CloudSyncLog::create([
                'outlet_id' => $outlet->id,
                'direction' => 'outbound',
                'trigger_type' => $trigger,
                'status' => 'skipped',
                'snapshots_count' => 0,
                'message' => 'Cloud sync is not enabled or Cloud Sync URL/Token is missing in Owner Settings. Local daily snapshots were still refreshed offline.',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            if ($generateAiAfter) {
                $this->ai->generateReport(Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(), $outlet->id, $staff);
            }

            return $log;
        }

        $pending = DailyAnalyticsSnapshot::query()
            ->where('outlet_id', $outlet->id)
            ->where(function ($q): void {
                $q->whereIn('sync_status', ['pending', 'failed'])
                    ->orWhereDate('business_date', today()->toDateString());
            })
            ->orderBy('business_date')
            ->get();

        if ($pending->isEmpty()) {
            $log = CloudSyncLog::create([
                'outlet_id' => $outlet->id,
                'direction' => 'outbound',
                'trigger_type' => $trigger,
                'status' => 'success',
                'snapshots_count' => 0,
                'http_status' => 200,
                'message' => 'All daily snapshots are already synced with the live cloud server.',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            if ($generateAiAfter) {
                $this->ai->generateReport(Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(), $outlet->id, $staff);
            }

            return $log;
        }

        $payload = [
            'outlet' => [
                'outlet_code' => $outlet->outlet_code,
                'name' => $outlet->name,
                'city' => $outlet->city,
                'state' => $outlet->state,
                'seating_capacity' => $outlet->seating_capacity,
            ],
            'snapshots' => $pending->map(fn (DailyAnalyticsSnapshot $s): array => $s->toSyncPayload())->values()->all(),
            'sent_at' => now()->toIso8601String(),
        ];

        $timestamp = (string) now()->timestamp;
        $rawJson = (string) json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawJson, $syncToken);
        $timeout = (int) config('pos.cloud_sync.timeout_seconds', 15);

        try {
            $response = Http::timeout($timeout)
                ->withToken($syncToken)
                ->withHeaders([
                    'X-POS-Outlet-Code' => $outlet->outlet_code,
                    'X-POS-Timestamp' => $timestamp,
                    'X-POS-Signature' => $signature,
                    'Accept' => 'application/json',
                ])
                ->withBody($rawJson, 'application/json')
                ->post($syncUrl);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                $now = now();
                DailyAnalyticsSnapshot::query()
                    ->whereIn('id', $pending->pluck('id'))
                    ->update([
                        'sync_status' => 'synced',
                        'synced_at' => $now,
                        'last_sync_error' => null,
                    ]);

                $outlet->update(['last_synced_at' => $now]);

                $log = CloudSyncLog::create([
                    'outlet_id' => $outlet->id,
                    'direction' => 'outbound',
                    'trigger_type' => $trigger,
                    'status' => 'success',
                    'snapshots_count' => $pending->count(),
                    'http_status' => $response->status(),
                    'message' => "Synced {$pending->count()} daily snapshot(s) to live cloud server.",
                    'duration_ms' => $durationMs,
                ]);

                if ($staff) {
                    $this->pos->logAudit($staff, 'cloud_sync_completed', 'outlet', $outlet->id, [
                        'snapshots' => $pending->count(),
                        'http_status' => $response->status(),
                    ]);
                }

                if ($generateAiAfter) {
                    $this->ai->generateReport(Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(), $outlet->id, $staff);
                }

                return $log;
            }

            $errorMsg = 'Cloud server returned HTTP '.$response->status().': '.substr($response->body(), 0, 240);
            $this->markSnapshotsFailed($pending->pluck('id')->all(), $errorMsg);

            return CloudSyncLog::create([
                'outlet_id' => $outlet->id,
                'direction' => 'outbound',
                'trigger_type' => $trigger,
                'status' => 'failed',
                'snapshots_count' => $pending->count(),
                'http_status' => $response->status(),
                'message' => $errorMsg,
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $errorMsg = 'Offline / network error (will auto-retry next sync): '.$e->getMessage();
            $this->markSnapshotsFailed($pending->pluck('id')->all(), $errorMsg);

            return CloudSyncLog::create([
                'outlet_id' => $outlet->id,
                'direction' => 'outbound',
                'trigger_type' => $trigger,
                'status' => 'failed',
                'snapshots_count' => $pending->count(),
                'message' => $errorMsg,
                'duration_ms' => $durationMs,
            ]);
        }
    }

    /**
     * Ingest nightly snapshots from a remote Hotel POS outlet on a Live Cloud Hub server.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function ingestInbound(
        array $payload,
        string $rawBody,
        ?string $bearerToken,
        ?string $signature,
        ?string $timestamp
    ): array {
        $startedAt = microtime(true);
        $settings = HotelSetting::allAsMap();
        $expectedToken = trim($settings['cloud_sync_token'] ?: (string) config('pos.cloud_sync.token', ''));

        if ($expectedToken === '') {
            throw new RuntimeException('Cloud Hub sync token is not configured on this server.');
        }

        if (! is_string($bearerToken) || ! hash_equals($expectedToken, trim($bearerToken))) {
            throw new RuntimeException('Invalid cloud sync bearer token.');
        }

        if ($signature !== null && $signature !== '' && $timestamp !== null && $timestamp !== '') {
            $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $expectedToken);
            if (! hash_equals($expectedSignature, $signature)) {
                throw new RuntimeException('Invalid payload HMAC signature.');
            }
        }

        $outletData = $payload['outlet'] ?? [];
        $outletCode = strtoupper(trim((string) ($outletData['outlet_code'] ?? '')));
        if ($outletCode === '') {
            throw new RuntimeException('Missing outlet_code in sync payload.');
        }

        $snapshotsData = $payload['snapshots'] ?? [];
        if (! is_array($snapshotsData)) {
            throw new RuntimeException('Invalid snapshots payload.');
        }

        return DB::transaction(function () use ($outletCode, $outletData, $snapshotsData, $startedAt): array {
            $outlet = Outlet::query()->firstOrNew(['outlet_code' => $outletCode]);
            $outlet->name = trim((string) ($outletData['name'] ?? $outlet->name ?: $outletCode));
            $outlet->city = isset($outletData['city']) ? trim((string) $outletData['city']) : $outlet->city;
            $outlet->state = isset($outletData['state']) ? trim((string) $outletData['state']) : $outlet->state;
            $outlet->seating_capacity = (int) ($outletData['seating_capacity'] ?? $outlet->seating_capacity ?? 0);
            if (! $outlet->exists) {
                $outlet->is_local = false;
            }
            $outlet->is_active = true;
            $outlet->last_synced_at = now();
            $outlet->save();

            $upserted = 0;
            foreach ($snapshotsData as $row) {
                if (empty($row['business_date'])) {
                    continue;
                }

                $dateStr = Carbon::parse((string) $row['business_date'])->toDateString();
                $snapshot = DailyAnalyticsSnapshot::query()
                    ->where('outlet_id', $outlet->id)
                    ->whereDate('business_date', $dateStr)
                    ->first();

                $attributes = [
                    'outlet_id' => $outlet->id,
                    'business_date' => $dateStr,
                    'total_orders' => (int) ($row['total_orders'] ?? 0),
                    'dine_in_orders' => (int) ($row['dine_in_orders'] ?? 0),
                    'takeaway_orders' => (int) ($row['takeaway_orders'] ?? 0),
                    'gross_sales' => round((float) ($row['gross_sales'] ?? 0), 2),
                    'discount_total' => round((float) ($row['discount_total'] ?? 0), 2),
                    'gst_total' => round((float) ($row['gst_total'] ?? 0), 2),
                    'net_revenue' => round((float) ($row['net_revenue'] ?? 0), 2),
                    'avg_bill_value' => round((float) ($row['avg_bill_value'] ?? 0), 2),
                    'cash_total' => round((float) ($row['cash_total'] ?? 0), 2),
                    'upi_total' => round((float) ($row['upi_total'] ?? 0), 2),
                    'card_total' => round((float) ($row['card_total'] ?? 0), 2),
                    'cancelled_orders' => (int) ($row['cancelled_orders'] ?? 0),
                    'cancelled_items' => (int) ($row['cancelled_items'] ?? 0),
                    'guest_count' => (int) ($row['guest_count'] ?? 0),
                    'category_sales' => is_array($row['category_sales'] ?? null) ? $row['category_sales'] : [],
                    'top_dishes' => is_array($row['top_dishes'] ?? null) ? $row['top_dishes'] : [],
                    'hourly_sales' => is_array($row['hourly_sales'] ?? null) ? $row['hourly_sales'] : [],
                    'waiter_performance' => is_array($row['waiter_performance'] ?? null) ? $row['waiter_performance'] : [],
                    'veg_nonveg_split' => is_array($row['veg_nonveg_split'] ?? null) ? $row['veg_nonveg_split'] : [],
                    'payload_checksum' => (string) ($row['payload_checksum'] ?? ''),
                    'sync_status' => 'synced',
                    'synced_at' => now(),
                    'last_sync_error' => null,
                ];

                if ($snapshot) {
                    $snapshot->fill($attributes)->save();
                } else {
                    DailyAnalyticsSnapshot::create($attributes);
                }
                $upserted++;
            }

            CloudSyncLog::create([
                'outlet_id' => $outlet->id,
                'direction' => 'inbound',
                'trigger_type' => 'api',
                'status' => 'success',
                'snapshots_count' => $upserted,
                'http_status' => 200,
                'message' => "Ingested {$upserted} daily snapshot(s) from outlet {$outlet->outlet_code}.",
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return [
                'ok' => true,
                'outlet_code' => $outlet->outlet_code,
                'upserted' => $upserted,
                'server_time' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  array<int, int>  $snapshotIds
     */
    private function markSnapshotsFailed(array $snapshotIds, string $errorMessage): void
    {
        if (empty($snapshotIds)) {
            return;
        }

        DailyAnalyticsSnapshot::query()
            ->whereIn('id', $snapshotIds)
            ->update([
                'sync_status' => 'failed',
                'sync_attempts' => DB::raw('sync_attempts + 1'),
                'last_sync_error' => substr($errorMessage, 0, 500),
            ]);
    }
}
