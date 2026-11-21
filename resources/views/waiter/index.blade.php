@extends('layouts.pos')
@section('title', 'Waiter Table Map')

@section('content')
@php use App\Services\PosService; @endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <div>
    <h2 style="margin:0;font-size:1.25rem;font-weight:900">Waiter Table Map</h2>
    <p style="margin:2px 0 0;font-size:0.8rem;color:var(--text-muted)">Tap any table to take order or send next KOT • Live auto-refresh every 5s</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span class="badge b-green">Available: {{ $tables->filter(fn($t) => !$t->activeOrder)->count() }}</span>
    <span class="badge b-amber">Running: {{ $tables->filter(fn($t) => $t->activeOrder && $t->activeOrder->status === 'open')->count() }}</span>
    <span class="badge b-blue">Billing: {{ $tables->filter(fn($t) => $t->activeOrder && $t->activeOrder->status === 'billing')->count() }}</span>
    @if ($readyKotsCount > 0)
      <span class="pulse-ready">🍽 {{ $readyKotsCount }} KOT Ready to Serve!</span>
    @endif
  </div>
</div>

@foreach ($sections as $sectionName => $sectionTables)
  <div class="section-title">{{ $sectionName }}</div>
  <div class="table-grid">
    @foreach ($sectionTables as $t)
      @php
        $ord = $t->activeOrder;
        $state = $ord ? ($ord->status === 'billing' ? 'billing' : 'occupied') : 'available';
        $readyCount = $ord ? $ord->kots->where('status', 'ready')->count() : 0;
        $mins = ($ord && $ord->opened_at) ? (int) $ord->opened_at->diffInMinutes(now()) : 0;
      @endphp
      <a href="{{ route('waiter.table', $t) }}" class="t-card {{ $state }}" data-table-id="{{ $t->id }}">
        <div class="t-top">
          <span class="t-code">{{ $t->code }}</span>
          @if ($readyCount > 0)
            <span class="pulse-ready">READY</span>
          @else
            <span class="t-cap">{{ $t->capacity }} Seats</span>
          @endif
        </div>

        <div class="t-mid mono" data-t-total>
          @if ($ord)
            {{ PosService::formatINR($ord->grand_total) }}
          @else
            <span style="color:var(--emerald);font-size:0.85rem;font-family:var(--font)">Empty • Tap to Open</span>
          @endif
        </div>

        <div class="t-foot">
          @if ($ord)
            <span>{{ $ord->status === 'billing' ? 'Bill Requested' : ($ord->waiter?->name ?? 'Running') }}</span>
            <span class="mono">{{ $mins }}m</span>
          @else
            <span>{{ $t->name }}</span>
            <span>Free</span>
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endforeach
@endsection

@push('scripts')
<script>
  (function () {
    // Lightweight live LAN refresh every 6 seconds so waiters instantly see Ready KOTs & freed tables
    setInterval(() => {
      if (document.hidden) return;
      fetch("{{ route('api.waiter.tables') }}", { headers: { Accept: "application/json" } })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
          if (!data || !Array.isArray(data.tables)) return;
          data.tables.forEach((t) => {
            const card = document.querySelector(`[data-table-id="${t.id}"]`);
            if (!card) return;
            card.classList.remove("available", "occupied", "billing");
            card.classList.add(t.status);
            const totalEl = card.querySelector("[data-t-total]");
            if (totalEl) {
              totalEl.innerHTML = t.status === "available"
                ? '<span style="color:var(--emerald);font-size:0.85rem;font-family:var(--font)">Empty • Tap to Open</span>'
                : t.grand_total_fmt;
            }
          });
        })
        .catch(() => {});
    }, 6000);
  })();
</script>
@endpush
