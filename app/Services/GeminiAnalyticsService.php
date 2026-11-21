<?php

namespace App\Services;

use App\Models\AiInsightReport;
use App\Models\HotelSetting;
use App\Models\Outlet;
use App\Models\StaffUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiAnalyticsService
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly PosService $pos
    ) {}

    public function generateReport(Carbon $from, Carbon $to, ?int $outletId = null, ?StaffUser $staff = null): AiInsightReport
    {
        $metrics = $this->analytics->getAnalyticsPayload($from, $to, $outletId);
        $settings = HotelSetting::allAsMap();
        $apiKey = trim($settings['gemini_api_key'] ?: (string) config('pos.gemini.api_key', ''));
        $model = trim($settings['gemini_model'] ?: (string) config('pos.gemini.model', 'gemini-4-hydrogen'));
        $scope = $outletId === null && count($metrics['outlet_comparison']) > 1 ? 'portfolio' : 'outlet';

        $resolvedOutletId = $outletId;
        if ($resolvedOutletId === null && $scope === 'outlet') {
            $resolvedOutletId = Outlet::query()->where('is_local', true)->value('id');
        }

        $parsed = null;
        $provider = 'local_fallback';
        $usedModel = 'statistical-engine-v1';

        if ($apiKey !== '') {
            $parsed = $this->callGeminiInteractionsApi($apiKey, $model, $metrics, $settings['hotel_name'] ?? 'Hotel POS');
            if ($parsed !== null) {
                $provider = 'gemini';
                $usedModel = $model;
            }
        }

        if ($parsed === null) {
            $parsed = $this->buildStatisticalFallback($metrics, $settings['hotel_name'] ?? 'Hotel POS');
        }

        $report = AiInsightReport::create([
            'outlet_id' => $resolvedOutletId,
            'scope' => $scope,
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'provider' => $provider,
            'model_name' => $usedModel,
            'executive_summary' => $parsed['executive_summary'],
            'health_score' => max(0, min(100, (int) $parsed['health_score'])),
            'highlights' => $parsed['highlights'],
            'risks' => $parsed['risks'],
            'recommendations' => $parsed['recommendations'],
            'investor_narrative' => $parsed['investor_narrative'],
            'metrics_snapshot' => [
                'kpis' => $metrics['kpis'],
                'top_category' => $metrics['category_mix'][0]['name'] ?? null,
                'top_dish' => $metrics['top_dishes'][0]['name'] ?? null,
            ],
            'generated_by_id' => $staff?->id,
        ]);

        if ($staff) {
            $this->pos->logAudit($staff, 'ai_insight_generated', 'ai_insight_report', $report->id, [
                'provider' => $provider,
                'model' => $usedModel,
                'period' => "{$from->toDateString()}..{$to->toDateString()}",
                'health_score' => $report->health_score,
            ]);
        }

        return $report;
    }

    /**
     * Call Google Gemini Interactions API (v1) with strict JSON schema output.
     *
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>|null
     */
    private function callGeminiInteractionsApi(string $apiKey, string $model, array $metrics, string $hotelName): ?array
    {
        $endpoint = (string) config('pos.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1/interactions');
        $timeout = (int) config('pos.gemini.timeout_seconds', 25);

        $compactContext = [
            'business_name' => $hotelName,
            'period' => $metrics['period'],
            'kpis' => $metrics['kpis'],
            'payment_split' => $metrics['payment_split'],
            'top_categories' => array_slice($metrics['category_mix'], 0, 6),
            'top_dishes' => array_slice($metrics['top_dishes'], 0, 6),
            'veg_nonveg' => $metrics['veg_nonveg'],
            'outlet_comparison' => $metrics['outlet_comparison'],
        ];

        $prompt = "You are a hospitality CFO and restaurant operations analyst reviewing verified Indian hotel POS telemetry for {$hotelName}. "
            .'Analyze the metrics JSON below and produce concise, investor-grade insights in INR (₹). '
            ."Be concrete, cite actual numbers, highlight unit economics, digital UPI adoption, discount/cancellation leakage, peak-hour staffing, and expansion readiness.\n\n"
            .'METRICS JSON: '.json_encode($compactContext);

        $schema = [
            'type' => 'object',
            'properties' => [
                'executive_summary' => ['type' => 'string'],
                'health_score' => ['type' => 'integer'],
                'highlights' => ['type' => 'array', 'items' => ['type' => 'string']],
                'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
                'investor_narrative' => ['type' => 'string'],
            ],
            'required' => [
                'executive_summary',
                'health_score',
                'highlights',
                'risks',
                'recommendations',
                'investor_narrative',
            ],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'model' => $model,
                    'input' => $prompt,
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => $schema,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini Interactions API non-2xx response', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $json = $response->json();
            $text = $this->extractOutputText(is_array($json) ? $json : []);
            if ($text === null) {
                return null;
            }

            $decoded = json_decode($text, true);
            if (! is_array($decoded) || empty($decoded['executive_summary'])) {
                return null;
            }

            return [
                'executive_summary' => (string) $decoded['executive_summary'],
                'health_score' => (int) ($decoded['health_score'] ?? 75),
                'highlights' => array_values(array_map('strval', (array) ($decoded['highlights'] ?? []))),
                'risks' => array_values(array_map('strval', (array) ($decoded['risks'] ?? []))),
                'recommendations' => array_values(array_map('strval', (array) ($decoded['recommendations'] ?? []))),
                'investor_narrative' => (string) ($decoded['investor_narrative'] ?? ''),
            ];
        } catch (Throwable $e) {
            Log::warning('Gemini API call failed, falling back to statistical engine', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract text from Gemini Interactions API `steps` payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractOutputText(array $payload): ?string
    {
        if (! empty($payload['output_text']) && is_string($payload['output_text'])) {
            return $payload['output_text'];
        }

        $chunks = [];
        foreach ($payload['steps'] ?? [] as $step) {
            if (($step['type'] ?? '') !== 'model_output') {
                continue;
            }
            foreach ($step['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                    $chunks[] = (string) $part['text'];
                }
            }
        }

        $combined = trim(implode("\n", $chunks));

        return $combined !== '' ? $combined : null;
    }

    /**
     * Offline / Zero-key deterministic statistical analytics fallback.
     *
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function buildStatisticalFallback(array $metrics, string $hotelName): array
    {
        $k = $metrics['kpis'];
        $revFmt = PosService::formatINR($k['revenue']);
        $aovFmt = PosService::formatINR($k['avg_bill']);
        $arrFmt = PosService::formatINR($k['annualized_run_rate']);
        $topCategory = $metrics['category_mix'][0] ?? null;
        $topDish = $metrics['top_dishes'][0] ?? null;
        $outletsCount = (int) $k['outlets_count'];

        // Compute deterministic Health Score (0-100)
        $score = 68;
        if ($k['orders'] > 0) {
            $score += 10;
        }
        if ($k['revenue_growth_pct'] > 0) {
            $score += min(12, (int) round($k['revenue_growth_pct'] / 5));
        }
        if ($k['digital_share_pct'] >= 35) {
            $score += 6;
        }
        if ($k['discount_rate_pct'] > 8) {
            $score -= 10;
        } elseif ($k['discount_rate_pct'] <= 4) {
            $score += 4;
        }
        if ($k['cancel_rate_pct'] > 5) {
            $score -= 10;
        }
        $score = max(25, min(98, $score));

        if ($k['orders'] === 0) {
            return [
                'executive_summary' => "{$hotelName} has no settled bills recorded between {$metrics['period']['from']} and {$metrics['period']['to']}. Once counter settlements begin, nightly snapshots and AI executive insights will populate automatically.",
                'health_score' => 50,
                'highlights' => [
                    'Offline-first LAN POS & nightly cloud sync pipeline are configured and ready to capture transactions.',
                    'Zero-leakage audit trail is active across item cancellations, discounts, and table transfers.',
                ],
                'risks' => [
                    'No settled bill telemetry available in the selected window — widen the date filter or settle active floor orders.',
                ],
                'recommendations' => [
                    'Complete daily table settlements through Billing Counter so nightly snapshots sync cleanly at 1:00 AM.',
                    'Add your Google Gemini API key in Owner → Settings to unlock live natural-language CFO insights.',
                ],
                'investor_narrative' => "{$hotelName} operates on a hybrid 75% offline LAN POS / 25% cloud analytics architecture designed for zero-downtime service and verifiable multi-outlet reporting.",
            ];
        }

        $highlights = [
            "Generated {$revFmt} across {$k['orders']} settled bills (Average Order Value: {$aovFmt}; {$k['revenue_growth_pct']}% vs prior period).",
            "Digital payment adoption (UPI + Card) accounts for {$k['digital_share_pct']}% of settled collections, enhancing cash-flow verifiability for lenders and investors.",
            "Peak floor demand concentrates around {$k['peak_hour_label']} with {$k['dine_in_share_pct']}% Dine-In and {$k['takeaway_share_pct']}% Takeaway mix.",
        ];

        if ($topCategory) {
            $highlights[] = "Top revenue category is {$topCategory['name']} contributing {$topCategory['pct']}% (".PosService::formatINR($topCategory['revenue']).') of item sales.';
        }
        if ($topDish) {
            $highlights[] = "Star dish \"{$topDish['name']}\" drove {$topDish['qty']} units (".PosService::formatINR($topDish['revenue']).').';
        }

        $risks = [];
        if ($k['discount_rate_pct'] > 6) {
            $risks[] = "Discount leakage is elevated at {$k['discount_rate_pct']}% of gross sales (".PosService::formatINR($k['discount_total']).') — review counter discount reasons in Anti-Theft Audit Log.';
        } else {
            $risks[] = "Discount discipline is healthy at {$k['discount_rate_pct']}% of gross sales; continue enforcing mandatory audit reasons above ₹100.";
        }

        if ($k['cancelled_items'] > 0 || $k['cancelled_orders'] > 0) {
            $risks[] = "Recorded {$k['cancelled_items']} cancelled item(s) and {$k['cancelled_orders']} cancelled order(s) ({$k['cancel_rate_pct']}% cancel rate) — audit kitchen prep waste vs waiter punch errors.";
        }

        if ($k['takeaway_share_pct'] < 15) {
            $risks[] = "Takeaway/Parcel contributes only {$k['takeaway_share_pct']}% of volume — low off-premise utilization leaves kitchen capacity untapped during non-peak windows.";
        }

        $recommendations = [
            "Pre-prep top-selling dishes before {$k['peak_hour_label']} to shave 3–5 minutes off KDS ticket times and improve peak table turnover.",
            $topDish
                ? "Bundle \"{$topDish['name']}\" with high-margin beverages/desserts to lift Average Order Value from {$aovFmt} by 10–15%."
                : 'Introduce curated family combos to push Average Order Value up by 10–15%.',
            'Keep Nightly Cloud Sync active so daily verified snapshots build an auditable 90-day investor track record.',
        ];

        if ($outletsCount > 1) {
            $leader = $metrics['outlet_comparison'][0];
            $recommendations[] = "Replicate menu mix and staffing practices from top-performing branch \"{$leader['name']}\" (".PosService::formatINR($leader['revenue']).') across sister outlets.';
        }

        $branchPhrase = $outletsCount > 1 ? "across {$outletsCount} connected outlets" : 'from its primary outlet';

        return [
            'executive_summary' => "{$hotelName} delivered {$revFmt} across {$k['orders']} settled bills ({$aovFmt} AOV) over {$metrics['period']['days']} day(s), pacing at an estimated {$arrFmt} annualized gross run rate with {$k['digital_share_pct']}% digital payment transparency and {$k['discount_rate_pct']}% discount control.",
            'health_score' => $score,
            'highlights' => $highlights,
            'risks' => $risks,
            'recommendations' => $recommendations,
            'investor_narrative' => "{$hotelName} demonstrates disciplined unit economics {$branchPhrase}, combining 75% zero-downtime local LAN execution with automated 25% nightly cloud financial consolidation. With {$revFmt} verified in this window ({$arrFmt} annualized run rate), {$k['digital_share_pct']}% traceable UPI/Card inflow, and full item-level anti-theft audit trails, the operation is structured for transparent due diligence and multi-outlet expansion.",
        ];
    }
}
