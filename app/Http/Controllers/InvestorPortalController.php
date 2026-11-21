<?php

namespace App\Http\Controllers;

use App\Models\AiInsightReport;
use App\Models\HotelSetting;
use App\Models\InvestorShareLink;
use App\Services\AnalyticsService;
use App\Services\GeminiAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvestorPortalController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly GeminiAnalyticsService $ai
    ) {}

    public function show(string $token): View|Response
    {
        $link = InvestorShareLink::query()
            ->with('outlet')
            ->where('token', $token)
            ->first();

        if (! $link || ! $link->isAccessible()) {
            return response()->view('investor.unavailable', [
                'settings' => HotelSetting::allAsMap(),
            ], 404);
        }

        $link->increment('views_count');
        $link->forceFill(['last_viewed_at' => now()])->save();

        $days = max(1, (int) $link->period_days);
        $to = Carbon::today()->endOfDay();
        $from = Carbon::today()->subDays($days - 1)->startOfDay();

        $payload = $this->analytics->getAnalyticsPayload($from, $to, $link->outlet_id);

        $aiReport = null;
        if ($link->include_ai_insights) {
            $aiReport = AiInsightReport::query()
                ->when($link->outlet_id !== null, fn ($q) => $q->where('outlet_id', $link->outlet_id))
                ->latest()
                ->first();

            if (! $aiReport) {
                $aiReport = $this->ai->generateReport($from, $to, $link->outlet_id, null);
            }
        }

        return view('investor.report', [
            'settings' => HotelSetting::allAsMap(),
            'link' => $link,
            'analytics' => $payload,
            'aiReport' => $aiReport,
        ]);
    }
}
