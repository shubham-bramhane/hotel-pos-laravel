@extends('layouts.pos')
@section('title', "Table {$table->code} Order")

@section('content')
@php use App\Services\PosService; @endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <a href="{{ route('waiter.index') }}" class="btn btn-sm btn-outline">← All Tables</a>
    <h2 style="margin:0;font-size:1.2rem;font-weight:900">{{ $table->code }} — {{ $table->name }}</h2>
    <span class="badge {{ $order ? ($order->status === 'billing' ? 'b-blue' : 'b-amber') : 'b-green' }}">
      {{ $order ? ($order->status === 'billing' ? 'Bill Requested' : 'Running • '.$order->order_no) : 'Empty Table' }}
    </span>
  </div>

  @if ($order)
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      @if ($order->status === 'open')
        <form method="POST" action="{{ route('waiter.bill.request', $order) }}">
          @csrf
          <button type="submit" class="btn btn-sm btn-blue">Request Bill ({{ PosService::formatINR($order->grand_total) }})</button>
        </form>
      @endif
      @if ($currentStaff->canAccess('cashier'))
        <a href="{{ route('counter.order', $order) }}" class="btn btn-sm btn-emerald">Open at Billing Counter →</a>
      @endif
    </div>
  @endif
</div>

<div class="order-layout">
  <!-- LEFT: Fast Touch Menu Picker -->
  <div>
    <div class="panel">
      <div class="panel-body">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <input id="menuSearch" class="input" style="flex:1;min-width:190px" type="search" placeholder="Search dish or short code (e.g. PBM, Roti, Biryani)..." autocomplete="off" />
          <button type="button" class="btn btn-sm btn-outline" id="vegFilterBtn" data-veg-mode="all">All (Veg+NonVeg)</button>
        </div>

        <div class="cat-pills">
          <button type="button" class="cat-pill active" data-cat="all">All Menu</button>
          @foreach ($categories as $cat)
            <button type="button" class="cat-pill" data-cat="{{ $cat->id }}">{{ $cat->name }}</button>
          @endforeach
        </div>

        <div class="menu-grid" id="menuGrid">
          @foreach ($categories as $cat)
            @foreach ($cat->items as $item)
              <div class="dish-card {{ $item->is_available ? '' : 'unavailable' }}"
                   data-item-card
                   data-id="{{ $item->id }}"
                   data-cat-id="{{ $cat->id }}"
                   data-name="{{ strtolower($item->name) }}"
                   data-code="{{ strtolower($item->short_code ?? '') }}"
                   data-veg="{{ $item->is_veg ? '1' : '0' }}"
                   data-title="{{ $item->name }}"
                   data-price="{{ $item->price }}">
                <div class="dish-head">
                  <span class="veg-mark {{ $item->is_veg ? '' : 'nonveg' }}"></span>
                  <div style="min-width:0;flex:1">
                    <div class="dish-name">{{ $item->name }}</div>
                    <div class="dish-code">{{ $item->short_code ?: $cat->name }} {{ $item->is_available ? '' : '• OUT OF STOCK' }}</div>
                  </div>
                </div>
                <div class="dish-foot">
                  <span class="dish-price mono">{{ PosService::formatINR($item->price) }}</span>
                  @if ($item->is_available)
                    <div class="qty-ctrl">
                      <button type="button" class="qty-btn" data-dec="{{ $item->id }}">−</button>
                      <span class="qty-num mono" id="qty-{{ $item->id }}">0</span>
                      <button type="button" class="qty-btn plus" data-inc="{{ $item->id }}">+</button>
                    </div>
                  @else
                    <span class="badge b-red">Sold Out</span>
                  @endif
                </div>
              </div>
            @endforeach
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: New KOT Cart + Running Order History -->
  <div>
    <!-- New KOT Cart Panel -->
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">New KOT Cart</h3>
          <p class="panel-sub">Select dishes on left, then tap Send KOT to Kitchen</p>
        </div>
        <span class="badge b-amber" id="cartCountBadge">0 items</span>
      </div>
      <div class="panel-body">
        <form method="POST" action="{{ route('waiter.kot.send', $table) }}" id="kotForm">
          @csrf
          <input type="hidden" name="cart_json" id="cartJsonInput" value="[]" />

          @if (!$order)
            <div class="form-group" style="margin-bottom:12px">
              <label class="form-label">Guests at Table</label>
              <input type="number" name="guest_count" class="input mono" value="2" min="1" max="40" />
            </div>
          @endif

          <div id="cartItemsList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
            <div style="padding:18px;text-align:center;color:var(--text-muted);font-size:0.84rem;background:var(--surface-alt);border-radius:10px">
              Tap <strong>+</strong> on dishes to build KOT
            </div>
          </div>

          <div class="form-group" style="margin-bottom:12px">
            <label class="form-label">Kitchen Instructions (Optional)</label>
            <input type="text" name="kot_notes" class="input" placeholder="e.g. Less spicy, serve starters first..." />
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-weight:800;font-size:1rem">
            <span>New KOT Subtotal</span>
            <span class="mono" id="cartTotalText">₹0</span>
          </div>

          <button type="submit" id="sendKotBtn" class="btn btn-brand btn-lg" style="width:100%" disabled>
            Send KOT to Kitchen →
          </button>
        </form>
      </div>
    </div>

    <!-- Already Sent Running KOTs -->
    @if ($order)
      <div class="panel">
        <div class="panel-head">
          <div>
            <h3 class="panel-title">Running Order ({{ $order->order_no }})</h3>
            <p class="panel-sub">Grand Total (incl. GST): <strong>{{ PosService::formatINR($order->grand_total) }}</strong></p>
          </div>
        </div>
        <div class="panel-body" style="display:flex;flex-direction:column;gap:10px">
          @foreach ($order->kots as $kot)
            @php
              $kBadge = match ($kot->status) {
                  'ready' => 'b-green',
                  'preparing' => 'b-blue',
                  'served' => 'b-slate',
                  'cancelled' => 'b-red',
                  default => 'b-amber',
              };
            @endphp
            <div style="border:1px solid var(--border);border-radius:11px;padding:10px 12px;background:var(--surface-alt)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <strong class="mono">{{ $kot->kot_no }} • {{ $kot->created_at->format('h:i A') }}</strong>
                <div style="display:flex;gap:6px;align-items:center">
                  <span class="badge {{ $kBadge }}">{{ strtoupper($kot->status) }}</span>
                  <a href="{{ route('print.kot', $kot) }}" target="_blank" class="btn btn-sm btn-outline" style="padding:2px 7px;font-size:0.7rem">KOT 🖨</a>
                </div>
              </div>
              @foreach ($kot->items as $it)
                <div style="display:flex;justify-content:space-between;font-size:0.83rem;padding:2px 0;{{ $it->status === 'cancelled' ? 'text-decoration:line-through;color:var(--rose)' : '' }}">
                  <span>{{ $it->item_name }} × {{ $it->quantity }} @if($it->special_note) <em style="color:var(--amber)">({{ $it->special_note }})</em> @endif</span>
                  <span class="mono">{{ PosService::formatINR($it->line_total) }}</span>
                </div>
              @endforeach
            </div>
          @endforeach
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    const cart = {}; // { id: { menu_item_id, name, price, quantity, note } }
    let activeCat = "all";
    let vegMode = "all"; // all | veg | nonveg

    const cards = Array.from(document.querySelectorAll("[data-item-card]"));
    const searchInput = document.getElementById("menuSearch");
    const vegBtn = document.getElementById("vegFilterBtn");

    function filterMenu() {
      const q = (searchInput.value || "").trim().toLowerCase();
      cards.forEach((c) => {
        const matchCat = activeCat === "all" || c.dataset.catId === activeCat;
        const matchVeg = vegMode === "all" || (vegMode === "veg" && c.dataset.veg === "1") || (vegMode === "nonveg" && c.dataset.veg === "0");
        const matchQ = !q || c.dataset.name.includes(q) || c.dataset.code.includes(q);
        c.classList.toggle("hidden", !(matchCat && matchVeg && matchQ));
      });
    }

    searchInput.addEventListener("input", filterMenu);
    document.querySelectorAll(".cat-pill").forEach((btn) => {
      btn.addEventListener("click", () => {
        document.querySelectorAll(".cat-pill").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        activeCat = btn.dataset.cat;
        filterMenu();
      });
    });

    vegBtn.addEventListener("click", () => {
      vegMode = vegMode === "all" ? "veg" : vegMode === "veg" ? "nonveg" : "all";
      vegBtn.textContent = vegMode === "all" ? "All (Veg+NonVeg)" : vegMode === "veg" ? "🟢 Veg Only" : "🔴 Non-Veg Only";
      filterMenu();
    });

    function renderCart() {
      const items = Object.values(cart);
      const listEl = document.getElementById("cartItemsList");
      const badge = document.getElementById("cartCountBadge");
      const totalEl = document.getElementById("cartTotalText");
      const jsonEl = document.getElementById("cartJsonInput");
      const btn = document.getElementById("sendKotBtn");

      let qtySum = 0;
      let amtSum = 0;
      items.forEach((i) => { qtySum += i.quantity; amtSum += i.price * i.quantity; });

      badge.textContent = `${qtySum} item${qtySum === 1 ? "" : "s"}`;
      totalEl.textContent = "₹" + Math.round(amtSum).toLocaleString("en-IN");
      jsonEl.value = JSON.stringify(items);
      btn.disabled = items.length === 0;

      if (!items.length) {
        listEl.innerHTML = '<div style="padding:18px;text-align:center;color:var(--text-muted);font-size:0.84rem;background:var(--surface-alt);border-radius:10px">Tap <strong>+</strong> on dishes to build KOT</div>';
        return;
      }

      listEl.innerHTML = items.map((i) => `
        <div style="border:1px solid var(--border);border-radius:10px;padding:9px 10px;background:var(--surface-alt)">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;font-weight:800;font-size:0.86rem">
            <span>${esc(i.name)} × ${i.quantity}</span>
            <span class="mono">₹${Math.round(i.price * i.quantity).toLocaleString("en-IN")}</span>
          </div>
          <input type="text" class="input" style="margin-top:6px;padding:5px 8px;font-size:0.76rem"
            placeholder="Special note (e.g. Less spicy, No onion)" value="${esc(i.note || "")}"
            data-note-id="${i.menu_item_id}" />
        </div>`).join("");

      listEl.querySelectorAll("[data-note-id]").forEach((inp) => {
        inp.addEventListener("input", () => {
          const id = inp.dataset.noteId;
          if (cart[id]) {
            cart[id].note = inp.value;
            jsonEl.value = JSON.stringify(Object.values(cart));
          }
        });
      });
    }

    function changeQty(id, delta) {
      const card = document.querySelector(`[data-item-card][data-id="${id}"]`);
      if (!card) return;
      if (!cart[id]) {
        cart[id] = { menu_item_id: Number(id), name: card.dataset.title, price: parseFloat(card.dataset.price), quantity: 0, note: "" };
      }
      cart[id].quantity = Math.max(0, cart[id].quantity + delta);
      const qEl = document.getElementById(`qty-${id}`);
      if (qEl) qEl.textContent = String(cart[id].quantity);
      if (cart[id].quantity === 0) delete cart[id];
      renderCart();
    }

    document.querySelectorAll("[data-inc]").forEach((b) => b.addEventListener("click", () => changeQty(b.dataset.inc, 1)));
    document.querySelectorAll("[data-dec]").forEach((b) => b.addEventListener("click", () => changeQty(b.dataset.dec, -1)));

    function esc(s) {
      return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }
  })();
</script>
@endpush
