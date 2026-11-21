@extends('layouts.pos')
@section('title', 'Anti-Theft Audit Log')

@section('content')
<div class="panel">
  <div class="panel-head">
    <div>
      <h3 class="panel-title">Anti-Theft Audit Trail</h3>
      <p class="panel-sub">Tracks every item cancellation, discount, order cancellation, table transfer &amp; bill settlement</p>
    </div>
    <form method="GET" action="{{ route('owner.audit') }}" style="display:flex;gap:8px;align-items:center">
      <select name="action" class="select" style="width:200px;padding:7px" onchange="this.form.submit()">
        <option value="all">All Actions</option>
        @foreach ($actions as $act)
          <option value="{{ $act }}" @selected($actionFilter === $act)>{{ $act }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Staff</th>
          <th>Action</th>
          <th>Details</th>
          <th>Device IP</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($logs as $log)
          @php
            $cls = str_contains($log->action, 'cancel') ? 'b-red' : (str_contains($log->action, 'discount') ? 'b-amber' : 'b-blue');
          @endphp
          <tr>
            <td class="mono" style="white-space:nowrap">{{ $log->created_at?->format('d-m-Y h:i:s A') }}</td>
            <td><strong>{{ $log->staff_name }}</strong> <span class="badge b-slate">{{ ucfirst($log->staff_role) }}</span></td>
            <td><span class="badge {{ $cls }}">{{ $log->action }}</span></td>
            <td class="mono" style="font-size:0.76rem;max-width:440px;word-break:break-word">{{ json_encode($log->details, JSON_UNESCAPED_UNICODE) }}</td>
            <td class="mono">{{ $log->ip_address ?: '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">No audit logs recorded yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
