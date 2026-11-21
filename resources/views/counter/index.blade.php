@extends('layouts.pos')
@section('title', 'Billing Counter POS')

@section('content')
@php use App\Services\PosService; @endphp

<!-- Top Counter Summary Strip -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h2 style="margin:0;font-size:1.25rem;font-weight:900">Billing Counter POS</h2>
    <p style="margin:2px 0 0;font-size:0.8rem;color:var(--text-muted)">Today's Settled Sales: <strong class="mono">{{ PosService::formatINR($todaySales) }}</strong> ({{ $todayBills }} bills)</p>
  </div>

  <!-- Quick New Parcel / Takeaway Form -->
  <form method="POST" action="{{ route('counter.parcel.create') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    @csrf
    <input type="text" name="customer_name" class="input" style="width:170px;padding:7px 10px" placeholder="Parcel Customer Name" />
    <input type="text" name="customer_phone" class="input mono" style="width:130px;padding:7px 10px" placeholder="Mobile (opt)" />
    <button type="submit" class="btn btn-brand btn-sm">+ New Parcel Order</button>
  </form>
</div>

<div class="counter-layout">
  <!-- LEFT: Live Floor Tables + Active Parcels + Today's Bills -->
  <div>
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Live Tables</h3>
          <p class="panel-sub">Click any running table to bill, or empty table to open directly</p>
        </div>
        <span class="badge b-blue">Live LAN Sync</span>
      </div>
      <div class="panel-body">
        <div class="table-grid">
          @foreach ($tables as $t)
            @php
              $ord = $t->activeOrder;
              $state = $ord ? ($ord->status === 'billing' ? 'billing' : 'occupied') : 'available';
              $isSel = $selectedOrder && $ord && $selectedOrder->id === $ord->id;
            @endphp
            @if ($ord)
              <a href="{{ route('counter.order', $ord) }}" class="t-card {{ $state }} {{ $isSel ? 'selected' : '' }}">
                <div class="t-top">
                  <span class="t-code">{{ $t->code }}</span>
                  @if ($ord->status === 'billing')
                    <span class="badge b-blue">BILL</span>
                  @elseif ($ord->kots->where('status','ready')->count() > 0)
                    <span class="pulse-ready">READY</span>
                  @endif
                </div>
                <div class="t-mid mono">{{ PosService::formatINR($ord->grand_total) }}</div>
                <div class="t-foot">
                  <span>{{ $ord->waiter?->name ?? 'Counter' }}</span>
                  <span class="mono">{{ (int) $ord->opened_at?->diffInMinutes(now()) }}m</span>
                </div>
              </a>
            @else
              <form method="POST" action="{{ route('counter.table.open', $t) }}" style="margin:0">
                @csrf
                <button type="submit" class="t-card available" style="width:100%;text-align:left;cursor:pointer;font-family:inherit">
                  <div class="t-top">
                    <span class="t-code">{{ $t->code }}</span>
                    <span class="t-cap">{{ $t->capacity }}p</span>
                  </div>
                  <div class="t-mid" style="color:var(--emerald);font-size:0.84rem">Empty • Open</div>
                  <div class="t-foot"><span>{{ $t->section }}</span><span>Free</span></div>
                </button>
              </form>
            @endif
          @endforeach
        </div>

        @if ($activeParcels->isNotEmpty())
          <div class="section-title">Active Takeaway / Parcel Orders</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            @foreach ($activeParcels as $p)
              <a href="{{ route('counter.order', $p) }}" class="btn btn-sm {{ $selectedOrder && $selectedOrder->id === $p->id ? 'btn-brand' : 'btn-outline' }}">
                📦 {{ $p->customer_name ?: $p->order_no }} • {{ PosService::formatINR($p->grand_total) }}
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <!-- Today's Settled Bills -->
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Today's Settled Bills (Latest 15)</h3>
          <p class="panel-sub">Click Reprint to print any thermal receipt again</p>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Bill No</th>
              <th>Table / Type</th>
              <th>Mode</th>
              <th>Total</th>
              <th>Print</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentBills as $b)
              <tr>
                <td class="mono" style="font-weight:700">{{ $b->order_no }}<div style="font-size:0.7rem;color:var(--text-muted)">{{ $b->paid_at?->format('h:i A') }}</div></td>
                <td>{{ $b->label() }}</td>
                <td><span class="badge b-green">{{ strtoupper((string) $b->payment_method) }}</span></td>
                <td class="mono" style="font-weight:800">{{ PosService::formatINR($b->grand_total) }}</td>
                <td><a href="{{ route('print.bill', $b) }}" target="_blank" class="btn btn-sm btn-outline">🖨 Bill</a></td>
              </tr>
            @empty
              <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">No bills settled today yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- RIGHT: Selected Order Billing & Settlement Panel -->
  <div>
    @if ($selectedOrder)
      <div class="panel">
        <div class="panel-head">
          <div>
            <h3 class="panel-title">{{ $selectedOrder->label() }}</h3>
            <p class="panel-sub mono">{{ $selectedOrder->order_no }} • Opened {{ $selectedOrder->opened_at?->format('h:i A') }} • Waiter: {{ $selectedOrder->waiter?->name ?? 'Counter' }}</p>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="{{ route('print.bill', $selectedOrder) }}" target="_blank" class="btn btn-sm btn-outline">🖨 Preview / Print Bill</a>
          </div>
        </div>

        <div class="panel-body">
          <!-- Quick Add Dish from Counter -->
          <form method="POST" action="{{ route('counter.order.items', $selectedOrder) }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;padding:10px;background:var(--surface-alt);border-radius:10px;border:1px solid var(--border)">
            @csrf
            <select name="menu_item_id" class="select" style="flex:1;min-width:170px;padding:7px 9px" required>
              <option value="">+ Quick Add Dish (Sends KOT)...</option>
              @foreach ($categories as $cat)
                <optgroup label="{{ $cat->name }}">
                  @foreach ($cat->items as $mi)
                    <option value="{{ $mi->id }}">{{ $mi->name }} ({{ $mi->short_code }}) — ₹{{ (int) $mi->price }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
            <input type="number" name="quantity" class="input mono" value="1" min="1" max="50" style="width:68px;padding:7px" />
            <button type="submit" class="btn btn-sm btn-brand">Add + KOT</button>
          </form>

          <!-- Order Items Table -->
          <div class="table-wrap" style="margin-bottom:14px">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Dish</th>
                  <th>Qty</th>
                  <th>Rate</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse ($selectedOrder->items as $it)
                  <tr style="{{ $it->status === 'cancelled' ? 'opacity:0.45;text-decoration:line-through' : '' }}">
                    <td>
                      <strong>{{ $it->item_name }}</strong>
                      @if ($it->special_note) <div style="font-size:0.72rem;color:var(--amber)">Note: {{ $it->special_note }}</div> @endif
                      @if ($it->status === 'cancelled') <div style="font-size:0.7rem;color:var(--rose)">Cancelled: {{ $it->cancel_reason }}</div> @endif
                    </td>
                    <td class="mono">{{ $it->quantity }}</td>
                    <td class="mono">{{ PosService::formatINR($it->unit_price) }}</td>
                    <td class="mono" style="font-weight:700">{{ PosService::formatINR($it->line_total) }}</td>
                    <td>
                      @if ($it->status !== 'cancelled' && !in_array($selectedOrder->status, ['paid','cancelled']))
                        <form method="POST" action="{{ route('counter.item.cancel', $it) }}" onsubmit="const r = prompt('Reason for cancelling {{ addslashes($it->item_name) }} (logged for Owner)?', 'Customer changed mind'); if(!r) return false; this.querySelector('[name=reason]').value = r; return true;">
                          @csrf
                          <input type="hidden" name="reason" value="" />
                          <button type="submit" class="btn btn-sm btn-danger" style="padding:3px 7px;font-size:0.7rem" title="Cancel Item">✕</button>
                        </form>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:18px">No items added yet. Use Waiter mobile or Quick Add above.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <!-- Tax & Grand Total Summary -->
          <div style="background:var(--surface-alt);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:0.86rem">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
              <span>Subtotal</span>
              <span class="mono">{{ PosService::formatINR($selectedOrder->subtotal) }}</span>
            </div>
            @if ($selectedOrder->discount_amount > 0)
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;color:var(--emerald)">
                <span>Discount ({{ $selectedOrder->discount_type === 'percent' ? $selectedOrder->discount_value.'%' : 'Flat' }})</span>
                <span class="mono">-{{ PosService::formatINR($selectedOrder->discount_amount) }}</span>
              </div>
            @endif
            @if ($selectedOrder->gst_rate > 0)
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;color:var(--text-sec)">
                <span>CGST ({{ $selectedOrder->gst_rate / 2 }}%) + SGST ({{ $selectedOrder->gst_rate / 2 }}%)</span>
                <span class="mono">{{ PosService::formatINR($selectedOrder->cgst_amount + $selectedOrder->sgst_amount) }}</span>
              </div>
            @endif
            @if ($selectedOrder->round_off != 0)
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;color:var(--text-muted)">
                <span>Round Off</span>
                <span class="mono">{{ $selectedOrder->round_off > 0 ? '+' : '' }}{{ number_format($selectedOrder->round_off, 2) }}</span>
              </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding-top:8px;margin-top:6px;border-top:2px solid var(--border-strong);font-size:1.25rem;font-weight:900">
              <span>GRAND TOTAL</span>
              <span class="mono" style="color:var(--brand)">{{ PosService::formatINR($selectedOrder->grand_total) }}</span>
            </div>
          </div>

          <!-- Discount & Table Transfer Tools -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
            <form method="POST" action="{{ route('counter.order.discount', $selectedOrder) }}" style="border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--surface-alt)">
              @csrf
              <div class="form-label" style="margin-bottom:6px">Apply Discount (Audit Logged)</div>
              <div style="display:flex;gap:5px;margin-bottom:6px">
                <select name="discount_type" class="select" style="padding:6px;font-size:0.78rem">
                  <option value="none" @selected($selectedOrder->discount_type === 'none')>None</option>
                  <option value="flat" @selected($selectedOrder->discount_type === 'flat')>Flat ₹</option>
                  <option value="percent" @selected($selectedOrder->discount_type === 'percent')>Percent %</option>
                </select>
                <input type="number" step="0.5" name="discount_value" class="input mono" style="padding:6px;font-size:0.8rem" value="{{ $selectedOrder->discount_value }}" placeholder="0" />
              </div>
              <input type="text" name="reason" class="input" style="padding:6px;font-size:0.76rem;margin-bottom:6px" placeholder="Reason (e.g. Regular customer)" />
              <button type="submit" class="btn btn-sm btn-outline" style="width:100%">Update Discount</button>
            </form>

            @if ($selectedOrder->order_type === 'dine_in' && $emptyTables->isNotEmpty())
              <form method="POST" action="{{ route('counter.order.transfer', $selectedOrder) }}" style="border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--surface-alt);display:flex;flex-direction:column;justify-content:space-between">
                @csrf
                <div>
                  <div class="form-label" style="margin-bottom:6px">Transfer Table</div>
                  <select name="target_table_id" class="select" style="padding:6px;font-size:0.8rem;margin-bottom:8px" required>
                    <option value="">Move to empty table...</option>
                    @foreach ($emptyTables as $et)
                      <option value="{{ $et->id }}">{{ $et->code }} — {{ $et->name }} ({{ $et->section }})</option>
                    @endforeach
                  </select>
                </div>
                <button type="submit" class="btn btn-sm btn-outline" style="width:100%">Transfer Table →</button>
              </form>
            @endif
          </div>

          <!-- Payment & Bill Settlement -->
          <form method="POST" action="{{ route('counter.order.settle', $selectedOrder) }}" style="border:2px solid var(--emerald);border-radius:14px;padding:14px;background:#f0fdf4">
            @csrf
            <div style="font-weight:900;font-size:0.95rem;color:var(--emerald);margin-bottom:10px">Settle Bill &amp; Free Table</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
              <label style="flex:1;min-width:80px;cursor:pointer;background:#fff;border:1px solid var(--border-strong);border-radius:9px;padding:8px;text-align:center;font-weight:700;font-size:0.82rem">
                <input type="radio" name="payment_method" value="cash" checked onchange="toggleSplit(false)" /> 💵 Cash
              </label>
              <label style="flex:1;min-width:80px;cursor:pointer;background:#fff;border:1px solid var(--border-strong);border-radius:9px;padding:8px;text-align:center;font-weight:700;font-size:0.82rem">
                <input type="radio" name="payment_method" value="upi" onchange="toggleSplit(false)" /> 📱 UPI
              </label>
              <label style="flex:1;min-width:80px;cursor:pointer;background:#fff;border:1px solid var(--border-strong);border-radius:9px;padding:8px;text-align:center;font-weight:700;font-size:0.82rem">
                <input type="radio" name="payment_method" value="card" onchange="toggleSplit(false)" /> 💳 Card
              </label>
              <label style="flex:1;min-width:80px;cursor:pointer;background:#fff;border:1px solid var(--border-strong);border-radius:9px;padding:8px;text-align:center;font-weight:700;font-size:0.82rem">
                <input type="radio" name="payment_method" value="split" onchange="toggleSplit(true)" /> ⇄ Split
              </label>
            </div>

            <div id="splitBox" class="hidden" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
              <input type="number" step="1" name="cash_amount" class="input mono" placeholder="Cash ₹" />
              <input type="number" step="1" name="upi_amount" class="input mono" placeholder="UPI ₹" />
            </div>

            <button type="submit" class="btn btn-emerald btn-lg" style="width:100%">
              Settle {{ PosService::formatINR($selectedOrder->grand_total) }} &amp; Complete Bill ✓
            </button>
          </form>

          <!-- Cancel Whole Order -->
          <form method="POST" action="{{ route('counter.order.cancel', $selectedOrder) }}" style="margin-top:12px;text-align:right"
            onsubmit="const r = prompt('Reason for cancelling entire order (logged in Audit Trail)?'); if(!r) return false; this.querySelector('[name=reason]').value = r; return true;">
            @csrf
            <input type="hidden" name="reason" value="" />
            <button type="submit" class="btn btn-sm btn-danger">Cancel Entire Order</button>
          </form>
        </div>
      </div>
    @else
      <div class="panel">
        <div class="panel-body" style="text-align:center;padding:70px 20px;color:var(--text-muted)">
          <h3 style="margin:0 0 6px;color:var(--text)">No Active Order Selected</h3>
          <p style="margin:0;font-size:0.86rem">Click any running table on the left, open an empty table, or start a new Parcel order.</p>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  function toggleSplit(show) {
    const el = document.getElementById("splitBox");
    if (el) el.classList.toggle("hidden", !show);
  }
</script>
@endpush
