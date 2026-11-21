<?php

namespace App\Http\Controllers;

use App\Models\AiInsightReport;
use App\Models\CloudSyncLog;
use App\Models\DailyAnalyticsSnapshot;
use App\Models\HotelSetting;
use App\Models\InvestorShareLink;
use App\Models\Outlet;
use App\Models\StaffUser;
use App\Services\AnalyticsService;
use App\Services\CloudSyncService;
use App\Services\GeminiAnalyticsService;
use App\Services\PosService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly GeminiAnalyticsService $ai,
        private readonly CloudSyncService $cloudSync,
        private readonly PosService $pos
    ) {}

    public function index(Request $request): View
    {
        $this->analytics->ensureLocalOutlet();
        [$from, $to, $range] = $this->resolveRange($request);

        $outlets = Outlet::query()->where('is_active', true)->orderByDesc('is_local')->orderBy('name')->get();
        $selectedOutletId = $request->filled('outlet_id') ? (int) $request->query('outlet_id') : null;
        if ($selectedOutletId !== null && ! $outlets->contains('id', $selectedOutletId)) {
            $selectedOutletId = null;
        }

        $payload = $this->analytics->getAnalyticsPayload($from, $to, $selectedOutletId);

        $latestReport = AiInsightReport::query()
            ->when($selectedOutletId !== null, fn ($q) => $q->where('outlet_id', $selectedOutletId))
            ->latest()
            ->first();

        $shareLinks = InvestorShareLink::query()
            ->with('outlet')
            ->latest()
            ->limit(15)
            ->get();

        $recentSyncLogs = CloudSyncLog::query()
            ->with('outlet')
            ->latest()
            ->limit(10)
            ->get();

        $pendingSyncCount = DailyAnalyticsSnapshot::query()
            ->whereIn('sync_status', ['pending', 'failed'])
            ->count();

        $syncedSnapshotsCount = DailyAnalyticsSnapshot::query()
            ->where('sync_status', 'synced')
            ->count();

        return view('owner.analytics', [
            'settings' => HotelSetting::allAsMap(),
            'range' => $range,
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'analytics' => $payload,
            'latestReport' => $latestReport,
            'shareLinks' => $shareLinks,
            'recentSyncLogs' => $recentSyncLogs,
            'pendingSyncCount' => $pendingSyncCount,
            'syncedSnapshotsCount' => $syncedSnapshotsCount,
        ]);
    }

    public function generateAi(Request $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        [$from, $to] = $this->resolveRange($request);
        $outletId = $request->filled('outlet_id') ? (int) $request->input('outlet_id') : null;

        $report = $this->ai->generateReport($from, $to, $outletId, $staff);

        $providerLabel = $report->provider === 'gemini'
            ? "Google Gemini ({$report->model_name})"
            : 'Offline Statistical Engine (add Gemini API key in Settings for live LLM insights)';

        return back()->with('status', "AI Performance Report generated via {$providerLabel} — Health Score: {$report->health_score}/100.");
    }

    public function syncNow(Request $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $days = max(1, min(90, (int) $request->input('days', 7)));
        $refreshAi = $request->boolean('refresh_ai', false);

        $log = $this->cloudSync->syncOutbound(
            lookbackDays: $days,
            trigger: 'manual',
            generateAiAfter: $refreshAi,
            staff: $staff
        );

        if ($log->status === 'failed') {
            return back()->with('error', $log->message);
        }

        return back()->with('status', $log->message);
    }

    public function createShareLink(Request $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'investor_name' => ['nullable', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
            'period_days' => ['required', 'integer', 'in:7,14,30,60,90,180,365'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'include_ai_insights' => ['nullable', 'boolean'],
            'include_branch_comparison' => ['nullable', 'boolean'],
        ]);

        $link = InvestorShareLink::create([
            'outlet_id' => $validated['outlet_id'] ?? null,
            'title' => trim($validated['title']),
            'investor_name' => isset($validated['investor_name']) ? trim((string) $validated['investor_name']) : null,
            'token' => Str::random(48),
            'period_days' => (int) $validated['period_days'],
            'include_ai_insights' => $request->boolean('include_ai_insights', true),
            'include_branch_comparison' => $request->boolean('include_branch_comparison', true),
            'expires_at' => ! empty($validated['expires_in_days'])
                ? now()->addDays((int) $validated['expires_in_days'])
                : null,
            'is_active' => true,
            'created_by_id' => $staff->id,
        ]);

        $this->pos->logAudit($staff, 'investor_link_created', 'investor_share_link', $link->id, [
            'title' => $link->title,
            'investor' => $link->investor_name,
            'period_days' => $link->period_days,
        ]);

        return back()->with('status', "Investor Share Link created for \"{$link->title}\"!");
    }

    public function toggleShareLink(Request $request, InvestorShareLink $shareLink): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $shareLink->update(['is_active' => ! $shareLink->is_active]);

        $this->pos->logAudit($staff, 'investor_link_toggled', 'investor_share_link', $shareLink->id, [
            'title' => $shareLink->title,
            'is_active' => $shareLink->is_active,
        ]);

        return back()->with('status', 'Investor link '.($shareLink->is_active ? 'activated' : 'revoked').'.');
    }

    public function destroyShareLink(Request $request, InvestorShareLink $shareLink): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->attributes->get('staff');
        $title = $shareLink->title;
        $shareLink->delete();

        $this->pos->logAudit($staff, 'investor_link_deleted', 'investor_share_link', null, [
            'title' => $title,
        ]);

        return back()->with('status', "Removed investor share link \"{$title}\".");
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $range = (string) $request->input('range', '30days');

        return match ($range) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), 'today'],
            '7days' => [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay(), '7days'],
            '90days' => [Carbon::today()->subDays(89)->startOfDay(), Carbon::today()->endOfDay(), '90days'],
            'month' => [Carbon::today()->startOfMonth(), Carbon::today()->endOfDay(), 'month'],
            default => [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(), '30days'],
        };
    }
}
