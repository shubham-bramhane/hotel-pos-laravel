@extends('layouts.pos')
@section('title', 'Menu Manager')

@section('content')
@php use App\Services\PosService; @endphp

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:16px;margin-bottom:18px">
  <!-- Add Category -->
  <div class="panel" style="margin:0">
    <div class="panel-head"><h3 class="panel-title">+ Add Menu Category</h3></div>
    <div class="panel-body">
      <form method="POST" action="{{ route('owner.category.store') }}" style="display:flex;gap:8px">
        @csrf
        <input type="text" name="name" class="input" placeholder="e.g. South Indian, Tandoor, Thali" required />
        <button type="submit" class="btn btn-brand">Add</button>
      </form>
    </div>
  </div>

  <!-- Add New Dish -->
  <div class="panel" style="margin:0">
    <div class="panel-head"><h3 class="panel-title">+ Add New Dish</h3></div>
    <div class="panel-body">
      <form method="POST" action="{{ route('owner.item.store') }}" class="form-grid" style="grid-template-columns:1.2fr 1.4fr 0.7fr 0.7fr 0.7fr auto;align-items:end">
        @csrf
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="select" required>
            @foreach ($categories as $c)
              <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Dish Name</label>
          <input type="text" name="name" class="input" placeholder="e.g. Shahi Paneer" required />
        </div>
        <div class="form-group">
          <label class="form-label">Code</label>
          <input type="text" name="short_code" class="input mono" placeholder="SP" />
        </div>
        <div class="form-group">
          <label class="form-label">Price ₹</label>
          <input type="number" step="1" name="price" class="input mono" placeholder="220" required />
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="is_veg" class="select">
            <option value="1">Veg</option>
            <option value="0">Non-Veg</option>
          </select>
        </div>
        <button type="submit" class="btn btn-brand">Add Dish</button>
      </form>
    </div>
  </div>
</div>

<!-- Existing Categories & Items -->
@foreach ($categories as $cat)
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title">{{ $cat->name }} ({{ $cat->items->count() }})</h3>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Dish Name</th>
            <th>Short Code</th>
            <th>Price (₹)</th>
            <th>Veg / Non-Veg</th>
            <th>Availability</th>
            <th>Save / Delete</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($cat->items as $it)
            <tr>
              <form method="POST" action="{{ route('owner.item.update', $it) }}">
                @csrf @method('PUT')
                <td><input type="text" name="name" class="input" style="padding:6px 8px" value="{{ $it->name }}" required /></td>
                <td><input type="text" name="short_code" class="input mono" style="padding:6px 8px;width:85px" value="{{ $it->short_code }}" /></td>
                <td><input type="number" step="1" name="price" class="input mono" style="padding:6px 8px;width:95px" value="{{ (int) $it->price }}" required /></td>
                <td>
                  <select name="is_veg" class="select" style="padding:6px;width:105px">
                    <option value="1" @selected($it->is_veg)>🟢 Veg</option>
                    <option value="0" @selected(!$it->is_veg)>🔴 Non-Veg</option>
                  </select>
                </td>
                <td>
                  <select name="is_available" class="select" style="padding:6px;width:115px">
                    <option value="1" @selected($it->is_available)>In Stock</option>
                    <option value="0" @selected(!$it->is_available)>Sold Out</option>
                  </select>
                </td>
                <td style="white-space:nowrap">
                  <button type="submit" class="btn btn-sm btn-emerald">Save</button>
              </form>
                  <form method="POST" action="{{ route('owner.item.destroy', $it) }}" style="display:inline" onsubmit="return confirm('Delete {{ addslashes($it->name) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">✕</button>
                  </form>
                </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endforeach
@endsection
