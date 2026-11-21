@extends('layouts.pos')
@section('title', 'AI Performance Analytics & Investor Portal')

@section('content')
@php
  use App\Services\PosService;
  $k = $analytics['kpis'];
  $isHub = ($settings['pos_mode'] ?? 'outlet') === 'cloud_hub';
@endphp

<!-- Top Action Header -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <div>
    <h2 style="margin:0;font-size:1.35rem;font-weight:900">AI Performance Analytics &amp; Investor Intelligence</h2>
    <p style="margin:2px 0 0;font-size:0.8rem;color:var(--text-muted)">
      {{ $fromDate }} to {{ $toDate }} ({{ $analytics['period']['days'] }} days) •
      <strong>{{ $isHub ? 'Live Cloud Hub Mode' : '75% Offline Outlet + 25% Cloud AI Sync' }}</strong>
    </p>
  </div>

  <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    @foreach (['today' => 'Today', '7days' => '7 Days', '30days' => '30 Days', 'month' => 'This Month', '90days' => '90 Days'] as $key => $lbl)
      <a href="{{ route('owner.analytics', array_filter(['range' => $key, 'outlet_id' => $selectedOutletId])) }}"
         class="btn btn-sm {{ $range === $key ? 'btn-brand' : 'btn-outline' }}">{{ $lbl }}</a>
    @endforeach

    @if ($outlets->count() > 1)
      <form method="GET" action="{{ route('owner.analytics') }}" style="display:inline-flex;gap:4px">
        <input type="hidden" name="range" value="{{ $range }}" />
        <select name="outlet_id" onchange="this.form.submit()" class="select" style="width:auto;padding:5px 10px;font-size:0.78rem">
          <option value="">All Connected Outlets ({{ $outlets->count() }})</option>
          @foreach ($outlets as $o)
            <option value="{{ $o->id }}" @selected($selectedOutletId === $o->id)>{{ $o->name }} ({{ $o->outlet_code }})</option>
          @endforeach
        </select>
      </form>
    @endif
  </div>
</div>

<!-- Hybrid 75/25 Status & Sync Strip -->
<div class="hybrid-pill-bar">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="badge b-green">75% Offline LAN Active</span>
    <span class="badge {{ ($settings['cloud_sync_enabled'] ?? '0') === '1' ? 'b-blue' : 'b-amber' }}">
      25% Nightly Cloud Sync: {{ ($settings['cloud_sync_enabled'] ?? '0') === '1' ? 'Scheduled @ '.($settings['nightly_sync_time'] ?? '01:00') : 'Local Mode (Configure URL in Settings)' }}
    </span>
    <span style="font-size:0.78rem;color:var(--text-sec);font-weight:700">
      Synced Days: <span class="mono">{{ $syncedSnapshotsCount }}</span> •
      Pending Queue: <span class="mono" style="color:{{ $pendingSyncCount > 0 ? 'var(--amber)' : 'var(--emerald)' }}">{{ $pendingSyncCount }}</span> •
      Outlets: <span class="mono">{{ $k['outlets_count'] }}</span>
    </span>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" action="{{ route('owner.analytics.sync') }}">
      @csrf
      <input type="hidden" name="days" value="14" />
      <button type="submit" class="btn btn-sm btn-outline">☁ Sync Nightly Snapshots Now</button>
    </form>
    <form method="POST" action="{{ route('owner.analytics.ai') }}">
      @csrf
      <input type="hidden" name="range" value="{{ $range }}" />
      @if ($selectedOutletId)
        <input type="hidden" name="outlet_id" value="{{ $selectedOutletId }}" />
      @endif
      <button type="submit" class="btn btn-sm btn-brand">✦ Generate AI Analysis</button>
    </form>
    <a href="{{ route('owner.settings') }}" class="btn btn-sm btn-outline">⚙ Cloud &amp; Gemini Setup</a>
  </div>
</div>

<!-- 5 Executive KPI Cards -->
<section class="kpi-grid-5">
  <div class="kpi-card">
    <div class="kpi-lbl">Net Sales Revenue</div>
    <div class="kpi-val mono" style="color:var(--emerald)">{{ PosService::formatINR($k['revenue']) }}</div>
    <div class="kpi-sub">
      <strong style="color:{{ $k['revenue_growth_pct'] >= 0 ? 'var(--emerald)' : 'var(--rose)' }}">
        {{ $k['revenue_growth_pct'] >= 0 ? '▲' : '▼' }} {{ abs($k['revenue_growth_pct']) }}%
      </strong> vs prior period
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Settled Bills &amp; Mix</div>
    <div class="kpi-val mono">{{ number_format($k['orders']) }}</div>
    <div class="kpi-sub">Dine-In {{ $k['dine_in_share_pct'] }}% • Parcel {{ $k['takeaway_share_pct'] }}%</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Avg Order Value (AOV)</div>
    <div class="kpi-val mono" style="color:var(--blue)">{{ PosService::formatINR($k['avg_bill']) }}</div>
    <div class="kpi-sub">Peak Hour: <strong>{{ $k['peak_hour_label'] }}</strong> • {{ $k['guest_count'] }} guests</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Digital Payment Share</div>
    <div class="kpi-val mono" style="color:var(--purple)">{{ $k['digital_share_pct'] }}%</div>
    <div class="kpi-sub">UPI {{ PosService::formatINR($k['upi_total']) }} • Cash {{ PosService::formatINR($k['cash_total']) }}</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Est. Annual Run Rate</div>
    <div class="kpi-val mono" style="color:var(--brand)">{{ PosService::formatINR($k['annualized_run_rate']) }}</div>
    <div class="kpi-sub">Discount {{ $k['discount_rate_pct'] }}% • Cancel {{ $k['cancel_rate_pct'] }}%</div>
  </div>
</section>

<!-- Google Gemini AI Executive Analysis Banner -->
<section class="ai-banner">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div style="flex:1;min-width:260px">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
        <span class="badge" style="background:rgba(251,146,60,0.22);color:#fdba74">✦ AI BUSINESS &amp; INVESTOR INTELLIGENCE</span>
        @if ($latestReport)
          <span class="badge" style="background:rgba(255,255,255,0.12);color:#e2e8f0">
            {{ $latestReport->provider === 'gemini' ? 'Google Gemini • '.$latestReport->model_name : 'Offline Statistical Engine' }}
          </span>
          <span style="font-size:0.74rem;color:#94a3b8">Updated {{ $latestReport->created_at->format('d M Y, h:i A') }}</span>
        @endif
      </div>

      @if ($latestReport)
        <p style="margin:0;font-size:0.95rem;line-height:1.55;color:#f1f5f9">{{ $latestReport->executive_summary }}</p>
      @else
        <p style="margin:0;font-size:0.92rem;line-height:1.55;color:#cbd5e1">
          Click <strong>"Generate AI Analysis"</strong> above to run a Google Gemini executive review (or offline statistical breakdown) covering revenue velocity, menu mix, leakage flags, and an investor-ready funding narrative.
        </p>
      @endif
    </div>

    @if ($latestReport)
      <div class="ai-score-ring">
        <strong class="mono">{{ $latestReport->health_score }}</strong>
        <span>Health / 100</span>
      </div>
    @endif
  </div>

  @if ($latestReport)
    <div class="ai-cols">
      <div class="ai-box">
        <h4 style="color:#34d399">Key Performance Highlights</h4>
        <ul>
          @foreach ($latestReport->highlights ?? [] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
      <div class="ai-box">
        <h4 style="color:#fbbf24">Leakage &amp; Risk Watch</h4>
        <ul>
          @foreach ($latestReport->risks ?? [] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
      <div class="ai-box">
        <h4 style="color:#60a5fa">Growth &amp; Margin Actions</h4>
        <ul>
          @foreach ($latestReport->recommendations ?? [] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
    </div>

    @if ($latestReport->investor_narrative)
      <div style="margin-top:14px;padding:12px 15px;border-radius:11px;background:rgba(5,150,105,0.16);border:1px solid rgba(52,211,153,0.35)">
        <div style="font-size:0.72rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#6ee7b7;margin-bottom:4px">Investor Pitch Narrative (Included in Shareable Link)</div>
        <div style="font-size:0.84rem;line-height:1.55;color:#ecfdf5">{{ $latestReport->investor_narrative }}</div>
      </div>
    @endif
  @endif
</section>

<!-- Charts Row 1: Revenue Trend + Payment Split Pie -->
<div class="chart-grid-wide">
  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Daily Revenue &amp; Bill Velocity Trend</h3>
        <p class="panel-sub">Hover any point/bar for exact date, revenue, bills &amp; AOV (100% offline SVG)</p>
      </div>
      <span class="badge b-green">{{ $analytics['period']['active_selling_days'] }} active day(s)</span>
    </div>
    <div class="panel-body">
      <div id="chartRevenueTrend"></div>
    </div>
  </div>

  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Payment Mode Split (Pie Chart)</h3>
        <p class="panel-sub">Cash vs UPI vs Card verifiability</p>
      </div>
      <span class="badge b-purple">{{ $k['digital_share_pct'] }}% Digital</span>
    </div>
    <div class="panel-body">
      <div id="chartPaymentPie"></div>
    </div>
  </div>
</div>

<!-- Charts Row 2: Category Sales Pie + Peak Dining Hours -->
<div class="chart-grid-2">
  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Menu Category Mix (Pie Chart)</h3>
        <p class="panel-sub">Revenue contribution across food &amp; beverage categories</p>
      </div>
      <span class="badge b-amber">Veg {{ $analytics['veg_nonveg']['veg_pct'] }}% / Non-Veg {{ $analytics['veg_nonveg']['nonveg_pct'] }}%</span>
    </div>
    <div class="panel-body">
      <div id="chartCategoryPie"></div>
    </div>
  </div>

  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Peak Dining Hours Distribution</h3>
        <p class="panel-sub">Orange bar marks peak revenue hour for staffing &amp; prep planning</p>
      </div>
      <span class="badge b-blue">Peak: {{ $k['peak_hour_label'] }}</span>
    </div>
    <div class="panel-body">
      <div id="chartHourlyBars"></div>
    </div>
  </div>
</div>

<!-- Multi-Outlet Benchmarking + Top Dishes Chart -->
<div class="chart-grid-2">
  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Multi-Outlet / Branch Benchmarking</h3>
        <p class="panel-sub">Aggregated across local outlet &amp; all nightly-synced sister branches</p>
      </div>
      <span class="badge b-blue">{{ count($analytics['outlet_comparison']) }} Outlet(s)</span>
    </div>
    <div class="panel-body">
      <div id="chartOutletComparison" style="margin-bottom:14px"></div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Outlet</th>
              <th>Orders</th>
              <th>Revenue</th>
              <th>AOV</th>
              <th>UPI %</th>
              <th>Last Sync</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($analytics['outlet_comparison'] as $oc)
              <tr>
                <td>
                  <strong>{{ $oc['name'] }}</strong>
                  <div style="font-size:0.72rem;color:var(--text-muted)">{{ $oc['outlet_code'] }}{{ $oc['location'] ? ' • '.$oc['location'] : '' }}</div>
                </td>
                <td class="mono">{{ $oc['orders'] }}</td>
                <td class="mono" style="font-weight:800;color:var(--emerald)">{{ PosService::formatINR($oc['revenue']) }}</td>
                <td class="mono">{{ PosService::formatINR($oc['avg_bill']) }}</td>
                <td class="mono">{{ $oc['upi_share_pct'] }}%</td>
                <td style="font-size:0.74rem;color:var(--text-sec)">{{ $oc['last_synced_at'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Top Revenue Dishes (Menu Engineering)</h3>
        <p class="panel-sub">Highest-grossing items across the selected analysis window</p>
      </div>
    </div>
    <div class="panel-body">
      <div id="chartTopDishes"></div>
    </div>
  </div>
</div>

<!-- Investor Share Link Manager & Nightly Sync Logs -->
<div class="chart-grid-wide">
  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Investor Share Links (Fundraising &amp; Performance Deck)</h3>
        <p class="panel-sub">Share a secure read-only executive link with investors — zero PINs or private phone numbers exposed</p>
      </div>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('owner.analytics.share.store') }}" class="form-grid" style="margin-bottom:16px">
        @csrf
        <div class="form-group">
          <label class="form-label">Report Title</label>
          <input type="text" name="title" class="input" required placeholder="Q4 Growth &amp; Expansion Performance Deck" />
        </div>
        <div class="form-group">
          <label class="form-label">Investor / Firm Name (Optional)</label>
          <input type="text" name="investor_name" class="input" placeholder="e.g., Angel Partner / Bank Manager" />
        </div>
        <div class="form-group">
          <label class="form-label">Reporting Window</label>
          <select name="period_days" class="select">
            <option value="7">Last 7 Days</option>
            <option value="30" selected>Last 30 Days</option>
            <option value="90">Last 90 Days (Quarter)</option>
            <option value="180">Last 180 Days</option>
            <option value="365">Last 365 Days (Annual)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Link Expiry (Days, optional)</label>
          <input type="number" name="expires_in_days" class="input mono" min="1" max="365" value="14" />
        </div>
        @if ($outlets->count() > 1)
          <div class="form-group full">
            <label class="form-label">Outlet Scope</label>
            <select name="outlet_id" class="select">
              <option value="">All Connected Outlets (Portfolio View)</option>
              @foreach ($outlets as $o)
                <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->outlet_code }})</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="form-group full" style="flex-direction:row;gap:16px;flex-wrap:wrap">
          <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;font-weight:700">
            <input type="checkbox" name="include_ai_insights" value="1" checked /> Include AI Investor Narrative
          </label>
          <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;font-weight:700">
            <input type="checkbox" name="include_branch_comparison" value="1" checked /> Include Branch Comparison
          </label>
        </div>
        <div class="form-group full">
          <button type="submit" class="btn btn-emerald">+ Create Investor Share Link</button>
        </div>
      </form>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Deck Title / Investor</th>
              <th>Window</th>
              <th>Views</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($shareLinks as $sl)
              @php $shareUrl = route('investor.report', $sl->token); @endphp
              <tr>
                <td>
                  <strong>{{ $sl->title }}</strong>
                  <div style="font-size:0.72rem;color:var(--text-muted)">
                    {{ $sl->investor_name ?: 'General Investor Link' }} •
                    {{ $sl->expires_at ? 'Expires '.$sl->expires_at->format('d M Y') : 'No expiry' }}
                  </div>
                </td>
                <td class="mono">{{ $sl->period_days }}d</td>
                <td class="mono">{{ $sl->views_count }}</td>
                <td>
                  @if ($sl->isAccessible())
                    <span class="badge b-green">Active</span>
                  @else
                    <span class="badge b-red">Revoked / Expired</span>
                  @endif
                </td>
                <td>
                  <div style="display:flex;gap:5px;flex-wrap:wrap">
                    <a href="{{ $shareUrl }}" target="_blank" class="btn btn-sm btn-blue">Open</a>
                    <button type="button" class="btn btn-sm btn-outline" onclick="copyShareUrl('{{ $shareUrl }}', this)">Copy URL</button>
                    <form method="POST" action="{{ route('owner.analytics.share.toggle', $sl) }}">
                      @csrf @method('PATCH')
                      <button type="submit" class="btn btn-sm btn-outline">{{ $sl->is_active ? 'Revoke' : 'Enable' }}</button>
                    </form>
                    <form method="POST" action="{{ route('owner.analytics.share.destroy', $sl) }}" onsubmit="return confirm('Delete this investor link?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-danger">✕</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No investor share links yet. Create one above to share live verified metrics!</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Nightly Cloud Sync History -->
  <div class="panel" style="margin:0">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Nightly Cloud Sync Logs</h3>
        <p class="panel-sub">Outbound uploads &amp; inbound hub ingestions</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Dir / Type</th>
            <th>Status</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recentSyncLogs as $log)
            <tr>
              <td class="mono" style="font-size:0.74rem">{{ $log->created_at->format('d M h:i A') }}</td>
              <td>
                <span class="badge b-slate">{{ strtoupper($log->direction) }}</span>
                <div style="font-size:0.7rem;color:var(--text-muted)">{{ $log->trigger_type }} • {{ $log->snapshots_count }}d</div>
              </td>
              <td>
                <span class="badge {{ $log->status === 'success' ? 'b-green' : ($log->status === 'failed' ? 'b-red' : 'b-amber') }}">
                  {{ strtoupper($log->status) }}
                </span>
              </td>
              <td style="font-size:0.75rem;color:var(--text-sec)">{{ $log->message }}</td>
            </tr>
          @empty
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:22px">No cloud sync runs logged yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@push('scripts')
<script src="{{ asset('js/pos-charts.js') }}"></script>
<script>
  (function () {
    const analytics = @json($analytics);

    PosCharts.renderRevenueTrend('chartRevenueTrend', analytics.daily_trend || []);

    PosCharts.renderDonut(
      'chartPaymentPie',
      (analytics.payment_split || []).map(p => ({ label: p.label, value: p.amount, color: p.color })),
      'COLLECTIONS'
    );

    PosCharts.renderDonut(
      'chartCategoryPie',
      (analytics.category_mix || []).map(c => ({ label: c.name, value: c.revenue, color: c.color })),
      'CATEGORIES'
    );

    PosCharts.renderHourlyBars('chartHourlyBars', analytics.hourly_sales || []);

    PosCharts.renderHorizontalBars(
      'chartOutletComparison',
      (analytics.outlet_comparison || []).map(o => ({
        label: o.name,
        badge: o.outlet_code,
        value: o.revenue,
        sub: `(${o.orders} bills)`,
        color: o.is_local ? '#ea580c' : '#2563eb'
      }))
    );

    PosCharts.renderHorizontalBars(
      'chartTopDishes',
      (analytics.top_dishes || []).slice(0, 7).map(d => ({
        label: d.name,
        badge: d.is_veg ? 'VEG' : 'NON-VEG',
        value: d.revenue,
        sub: `(${d.qty} sold)`,
        color: d.is_veg ? '#059669' : '#dc2626'
      }))
    );
  })();

  function copyShareUrl(url, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1600);
      });
    } else {
      window.prompt('Copy Investor Share Link:', url);
    }
  }
</script>
@endpush
@endsection
