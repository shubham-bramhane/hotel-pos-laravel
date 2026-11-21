@extends('layouts.pos')
@section('title', 'Tables Manager')

@section('content')
<div class="panel">
  <div class="panel-head"><h3 class="panel-title">+ Add New Dining Table</h3></div>
  <div class="panel-body">
    <form method="POST" action="{{ route('owner.tables.store') }}" class="form-grid" style="grid-template-columns:0.8fr 1.2fr 1.2fr 0.7fr auto;align-items:end">
      @csrf
      <div class="form-group">
        <label class="form-label">Table Code</label>
        <input type="text" name="code" class="input mono" placeholder="T9 or AC-5" required />
      </div>
      <div class="form-group">
        <label class="form-label">Display Name</label>
        <input type="text" name="name" class="input" placeholder="Table 9" required />
      </div>
      <div class="form-group">
        <label class="form-label">Section</label>
        <input type="text" name="section" class="input" value="Main Hall" required />
      </div>
      <div class="form-group">
        <label class="form-label">Seats</label>
        <input type="number" name="capacity" class="input mono" value="4" min="1" max="30" required />
      </div>
      <button type="submit" class="btn btn-brand">Add Table</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3 class="panel-title">All Dining Tables ({{ $tables->count() }})</h3></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Code</th><th>Name</th><th>Section</th><th>Capacity</th><th>Live Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        @foreach ($tables as $t)
          <tr>
            <td class="mono" style="font-weight:800">{{ $t->code }}</td>
            <td>{{ $t->name }}</td>
            <td>{{ $t->section }}</td>
            <td class="mono">{{ $t->capacity }} Seats</td>
            <td>
              <span class="badge {{ $t->activeOrder ? 'b-amber' : 'b-green' }}">
                {{ $t->activeOrder ? 'Active Order ('.$t->activeOrder->order_no.')' : 'Available' }}
              </span>
            </td>
            <td>
              <form method="POST" action="{{ route('owner.tables.destroy', $t) }}" onsubmit="return confirm('Remove table {{ $t->code }}?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" {{ $t->activeOrder ? 'disabled' : '' }}>Remove</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
