<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $link->title }} — Investor Performance Portal</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}" />
  <link rel="stylesheet" href="{{ asset('css/pos.css') }}" />
</head>
<body>
@php
  use App\Services\PosService;
  $k = $analytics['kpis'];
@endphp

<header class="pos-nav">
  <div class="pos-brand">
    <div class="pos-brand-icon">
      <img src="{{ asset('icon.svg') }}" alt="Logo" />
    </div>
    <div>
      <h1>{{ $link->outlet?->name ?? ($settings['hotel_name'] ?? 'Royal Family Restaurant & Hotel') }}</h1>
      <span>Verified POS Telemetry • Read-Only Investor Performance Report</span>
    </div>
  </div>

  <div class="pos-nav-right no-print">
    <span class="staff-chip">
      <span class="role-dot"></span>
      {{ $link->investor_name ? 'Prepared for '.$link->investor_name : 'Investor Executive View' }}
    </span>
    <button type="button" onclick="window.print()" class="btn btn-sm btn-brand">🖨 Print / Save Investor PDF</button>
  </div>
</header>

<main class="pos-main">
  <!-- Executive Title Banner -->
  <div class="hybrid-pill-bar">
    <div>
      <h2 style="margin:0;font-size:1.3rem;font-weight:900">{{ $link->title }}</h2>
      <p style="margin:3px 0 0;font-size:0.8rem;color:var(--text-muted)">
        Reporting Window: <strong>{{ $analytics['period']['from'] }} to {{ $analytics['period']['to'] }}</strong> ({{ $link->period_days }} days) •
        Scope: <strong>{{ $link->outlet ? $link->outlet->name.' ('.$link->outlet->outlet_code.')' : 'All Connected Outlets ('.$k['outlets_count'].')' }}</strong> •
        Generated {{ now()->format('d M Y, h:i A') }}
      </p>
    </div>
    <span class="badge b-green">✓ Verified POS Data • 75% Offline / 25% Cloud Sync</span>
  </div>

  <!-- 5 Investor Unit Economics KPIs -->
  <section class="kpi-grid-5">
    <div class="kpi-card">
      <div class="kpi-lbl">Verified Net Revenue</div>
      <div class="kpi-val mono" style="color:var(--emerald)">{{ PosService::formatINR($k['revenue']) }}</div>
      <div class="kpi-sub">
        <strong style="color:{{ $k['revenue_growth_pct'] >= 0 ? 'var(--emerald)' : 'var(--rose)' }}">
          {{ $k['revenue_growth_pct'] >= 0 ? '▲' : '▼' }} {{ abs($k['revenue_growth_pct']) }}%
        </strong> vs previous {{ $link->period_days }}d
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-lbl">Est. Annual Run Rate</div>
      <div class="kpi-val mono" style="color:var(--brand)">{{ PosService::formatINR($k['annualized_run_rate']) }}</div>
      <div class="kpi-sub">Daily avg: {{ PosService::formatINR($k['daily_run_rate']) }}</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-lbl">Orders &amp; Average Ticket</div>
      <div class="kpi-val mono" style="color:var(--blue)">{{ PosService::formatINR($k['avg_bill']) }}</div>
      <div class="kpi-sub">{{ number_format($k['orders']) }} bills • {{ $k['dine_in_share_pct'] }}% Dine-In / {{ $k['takeaway_share_pct'] }}% Parcel</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-lbl">Traceable Digital Flow</div>
      <div class="kpi-val mono" style="color:var(--purple)">{{ $k['digital_share_pct'] }}%</div>
      <div class="kpi-sub">UPI + Card: {{ PosService::formatINR($k['upi_total'] + $k['card_total']) }}</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-lbl">Margin &amp; Leakage Control</div>
      <div class="kpi-val mono">{{ $k['discount_rate_pct'] }}%</div>
      <div class="kpi-sub">Discount rate • {{ $k['cancel_rate_pct'] }}% order cancel rate</div>
    </div>
  </section>

  <!-- AI Investor Narrative & Health Score -->
  @if ($link->include_ai_insights && $aiReport)
    <section class="ai-banner">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <span class="badge" style="background:rgba(52,211,153,0.22);color:#6ee7b7">✦ AI EXECUTIVE &amp; INVESTOR BRIEFING</span>
            <span class="badge" style="background:rgba(255,255,255,0.12);color:#e2e8f0">
              {{ $aiReport->provider === 'gemini' ? 'Google Gemini ('.$aiReport->model_name.')' : 'Automated Financial Engine' }}
            </span>
          </div>
          <p style="margin:0 0 10px;font-size:0.95rem;line-height:1.6;color:#f8fafc">{{ $aiReport->investor_narrative ?: $aiReport->executive_summary }}</p>
        </div>
        <div class="ai-score-ring">
          <strong class="mono">{{ $aiReport->health_score }}</strong>
          <span>Health / 100</span>
        </div>
      </div>

      <div class="ai-cols">
        <div class="ai-box">
          <h4 style="color:#34d399">Growth Highlights</h4>
          <ul>
            @foreach ($aiReport->highlights ?? [] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
        <div class="ai-box">
          <h4 style="color:#fbbf24">Operational Watchpoints</h4>
          <ul>
            @foreach ($aiReport->risks ?? [] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
        <div class="ai-box">
          <h4 style="color:#60a5fa">Expansion Levers</h4>
          <ul>
            @foreach ($aiReport->recommendations ?? [] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    </section>
  @endif

  <!-- Graphs & Pie Charts -->
  <div class="chart-grid-wide">
    <div class="panel" style="margin:0">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Daily Revenue Trajectory</h3>
          <p class="panel-sub">Verified daily settlement totals</p>
        </div>
      </div>
      <div class="panel-body">
        <div id="invRevenueTrend"></div>
      </div>
    </div>

    <div class="panel" style="margin:0">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Payment Mode Breakdown</h3>
          <p class="panel-sub">Cash vs UPI vs Card mix</p>
        </div>
      </div>
      <div class="panel-body">
        <div id="invPaymentPie"></div>
      </div>
    </div>
  </div>

  <div class="chart-grid-2">
    <div class="panel" style="margin:0">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Category Revenue Mix</h3>
          <p class="panel-sub">Veg {{ $analytics['veg_nonveg']['veg_pct'] }}% • Non-Veg {{ $analytics['veg_nonveg']['nonveg_pct'] }}%</p>
        </div>
      </div>
      <div class="panel-body">
        <div id="invCategoryPie"></div>
      </div>
    </div>

    <div class="panel" style="margin:0">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Hourly Demand Profile</h3>
          <p class="panel-sub">Peak throughput hour: {{ $k['peak_hour_label'] }}</p>
        </div>
      </div>
      <div class="panel-body">
        <div id="invHourlyBars"></div>
      </div>
    </div>
  </div>

  @if ($link->include_branch_comparison && count($analytics['outlet_comparison']) > 0)
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Outlet / Branch Performance Comparison</h3>
          <p class="panel-sub">Consolidated across connected properties via nightly cloud sync</p>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Outlet</th>
              <th>Location</th>
              <th>Settled Orders</th>
              <th>Net Revenue</th>
              <th>Avg Ticket</th>
              <th>UPI Share</th>
              <th>Discount %</th>
              <th>Last Sync</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($analytics['outlet_comparison'] as $oc)
              <tr>
                <td><strong>{{ $oc['name'] }}</strong> <span class="badge b-slate">{{ $oc['outlet_code'] }}</span></td>
                <td>{{ $oc['location'] ?: '—' }}</td>
                <td class="mono">{{ $oc['orders'] }}</td>
                <td class="mono" style="font-weight:800;color:var(--emerald)">{{ PosService::formatINR($oc['revenue']) }}</td>
                <td class="mono">{{ PosService::formatINR($oc['avg_bill']) }}</td>
                <td class="mono">{{ $oc['upi_share_pct'] }}%</td>
                <td class="mono">{{ $oc['discount_pct'] }}%</td>
                <td style="font-size:0.75rem;color:var(--text-sec)">{{ $oc['last_synced_at'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div style="text-align:center;font-size:0.76rem;color:var(--text-muted);margin-top:24px">
    Confidential Read-Only Executive Report • Generated from verified Royal Hotel Hybrid POS ledger • No customer PII or staff credentials included.
  </div>
</main>

<script src="{{ asset('js/pos-charts.js') }}"></script>
<script>
  (function () {
    const analytics = @json($analytics);
    PosCharts.renderRevenueTrend('invRevenueTrend', analytics.daily_trend || []);
    PosCharts.renderDonut(
      'invPaymentPie',
      (analytics.payment_split || []).map(p => ({ label: p.label, value: p.amount, color: p.color })),
      'COLLECTIONS'
    );
    PosCharts.renderDonut(
      'invCategoryPie',
      (analytics.category_mix || []).map(c => ({ label: c.name, value: c.revenue, color: c.color })),
      'CATEGORIES'
    );
    PosCharts.renderHourlyBars('invHourlyBars', analytics.hourly_sales || []);
  })();
</script>
</body>
</html>
