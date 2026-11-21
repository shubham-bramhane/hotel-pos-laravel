@extends('layouts.pos')
@section('title', 'Owner Dashboard & Sales Reports')

@section('content')
@php use App\Services\PosService; @endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h2 style="margin:0;font-size:1.3rem;font-weight:900">Owner Sales &amp; Control Dashboard</h2>
    <p style="margin:2px 0 0;font-size:0.8rem;color:var(--text-muted)">Showing {{ $fromDate }} to {{ $toDate }} • 100% Local MySQL Server</p>
  </div>

  <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7days' => '7 Days', 'month' => 'This Month'] as $key => $lbl)
      <a href="{{ route('owner.dashboard', ['range' => $key]) }}" class="btn btn-sm {{ $range === $key ? 'btn-brand' : 'btn-outline' }}">{{ $lbl }}</a>
    @endforeach
    <a href="{{ route('owner.export.sales', request()->query()) }}" class="btn btn-sm btn-emerald">Export Sales CSV</a>
  </div>
</div>

<!-- KPI Row -->
<section class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-lbl">Total Sales Revenue</div>
    <div class="kpi-val mono" style="color:var(--emerald)">{{ PosService::formatINR($kpis['revenue']) }}</div>
    <div class="kpi-sub">{{ $kpis['bills'] }} bills • Avg {{ PosService::formatINR($kpis['avg_bill']) }}</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Payment Split</div>
    <div class="kpi-val mono" style="font-size:1.15rem;line-height:1.5">
      Cash: {{ PosService::formatINR($kpis['cash']) }}<br />
      UPI: {{ PosService::formatINR($kpis['upi']) }}
    </div>
    <div class="kpi-sub">Card: {{ PosService::formatINR($kpis['card']) }}</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Running Floor Value</div>
    <div class="kpi-val mono" style="color:var(--amber)">{{ PosService::formatINR($kpis['running_value']) }}</div>
    <div class="kpi-sub">{{ $kpis['running_count'] }} active table/parcel order(s)</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-lbl">Discounts &amp; Cancellations</div>
    <div class="kpi-val mono" style="color:var(--rose)">{{ PosService::formatINR($kpis['discount']) }}</div>
    <div class="kpi-sub">{{ $kpis['cancelled_items'] }} cancelled item(s) • GST {{ PosService::formatINR($kpis['gst']) }}</div>
  </div>
</section>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px">
  <!-- Top Selling Dishes -->
  <div class="panel" style="margin:0">
    <div class="panel-head"><h3 class="panel-title">Top Selling Dishes</h3></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Dish</th><th>Qty Sold</th><th>Sales</th></tr></thead>
        <tbody>
          @forelse ($topItems as $ti)
            <tr>
              <td><strong>{{ $ti->item_name }}</strong></td>
              <td class="mono">{{ $ti->total_qty }}</td>
              <td class="mono" style="font-weight:800;color:var(--emerald)">{{ PosService::formatINR($ti->total_sales) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:24px">No settled sales in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Waiter Performance -->
  <div class="panel" style="margin:0">
    <div class="panel-head"><h3 class="panel-title">Staff / Waiter Performance</h3></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Staff Name</th><th>Orders</th><th>Revenue</th></tr></thead>
        <tbody>
          @forelse ($waiterStats as $ws)
            <tr>
              <td><strong>{{ $ws['name'] }}</strong></td>
              <td class="mono">{{ $ws['orders'] }}</td>
              <td class="mono" style="font-weight:800">{{ PosService::formatINR($ws['sales']) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:24px">No settled orders in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent Settled Bills -->
<div class="panel">
  <div class="panel-head">
    <div>
      <h3 class="panel-title">Settled Bills History</h3>
      <p class="panel-sub">Click Print to view any customer bill</p>
    </div>
    <a href="{{ route('owner.audit') }}" class="btn btn-sm btn-outline">Open Anti-Theft Audit Log →</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Bill No</th>
          <th>Date &amp; Time</th>
          <th>Table / Type</th>
          <th>Waiter</th>
          <th>Discount</th>
          <th>GST</th>
          <th>Grand Total</th>
          <th>Mode</th>
          <th>Bill</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($paidOrders as $o)
          <tr>
            <td class="mono" style="font-weight:700">{{ $o->order_no }}</td>
            <td class="mono">{{ $o->paid_at?->format('d-m-Y h:i A') }}</td>
            <td>{{ $o->label() }}</td>
            <td>{{ $o->waiter?->name ?? 'Counter' }}</td>
            <td class="mono">{{ $o->discount_amount > 0 ? '-'.PosService::formatINR($o->discount_amount) : '—' }}</td>
            <td class="mono">{{ PosService::formatINR($o->cgst_amount + $o->sgst_amount) }}</td>
            <td class="mono" style="font-weight:800;color:var(--emerald)">{{ PosService::formatINR($o->grand_total) }}</td>
            <td><span class="badge b-green">{{ strtoupper((string) $o->payment_method) }}</span></td>
            <td><a href="{{ route('print.bill', $o) }}" target="_blank" class="btn btn-sm btn-outline">🖨</a></td>
          </tr>
        @empty
          <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:28px">No settled bills yet in this date range.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
