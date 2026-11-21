@extends('layouts.pos')
@section('title', 'Staff & PIN Manager')

@section('content')
<div class="panel">
  <div class="panel-head"><h3 class="panel-title">+ Add New Staff Member</h3></div>
  <div class="panel-body">
    <form method="POST" action="{{ route('owner.staff.store') }}" class="form-grid" style="grid-template-columns:1.4fr 1fr 0.9fr auto;align-items:end">
      @csrf
      <div class="form-group">
        <label class="form-label">Staff Name</label>
        <input type="text" name="name" class="input" placeholder="e.g. Waiter Suresh" required />
      </div>
      <div class="form-group">
        <label class="form-label">Role</label>
        <select name="role" class="select">
          <option value="waiter">Waiter (Orders &amp; Tables Only)</option>
          <option value="kitchen">Kitchen (KDS Display &amp; Stock)</option>
          <option value="cashier">Cashier (Counter Billing &amp; Print)</option>
          <option value="owner">Owner (Full Admin &amp; Reports)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">4-6 Digit PIN</label>
        <input type="text" name="pin_code" class="input mono" placeholder="6666" required />
      </div>
      <button type="submit" class="btn btn-brand">Add Staff</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3 class="panel-title">Staff Directory &amp; PIN Access</h3></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Name</th><th>Role</th><th>PIN</th><th>Status</th><th>Save</th></tr>
      </thead>
      <tbody>
        @foreach ($staffList as $s)
          <tr>
            <form method="POST" action="{{ route('owner.staff.update', $s) }}">
              @csrf @method('PUT')
              <td><input type="text" name="name" class="input" style="padding:6px 8px" value="{{ $s->name }}" required /></td>
              <td>
                <select name="role" class="select" style="padding:6px">
                  @foreach (['waiter' => 'Waiter', 'kitchen' => 'Kitchen', 'cashier' => 'Cashier', 'owner' => 'Owner'] as $rk => $rl)
                    <option value="{{ $rk }}" @selected($s->role === $rk)>{{ $rl }}</option>
                  @endforeach
                </select>
              </td>
              <td><input type="text" name="pin_code" class="input mono" style="padding:6px 8px;width:100px" value="{{ $s->pin_code }}" required /></td>
              <td>
                <select name="is_active" class="select" style="padding:6px;width:110px">
                  <option value="1" @selected($s->is_active)>Active</option>
                  <option value="0" @selected(!$s->is_active)>Disabled</option>
                </select>
              </td>
              <td><button type="submit" class="btn btn-sm btn-emerald">Update</button></td>
            </form>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
