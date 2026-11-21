@extends('layouts.pos')
@section('title', 'Live Kitchen Display (KDS)')

@section('fullbleed')
<div class="kds-body">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
    <div>
      <h2 style="margin:0;font-size:1.35rem;font-weight:900;color:#fff">Kitchen Display System (Live KDS)</h2>
      <p style="margin:2px 0 0;font-size:0.8rem;color:#94a3b8">Auto-refreshes every 3 seconds over local LAN • Last sync: <span id="kdsClock" class="mono">{{ now()->format('h:i:s A') }}</span></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span class="badge b-amber" id="cntPending">Pending: {{ $kots->where('status','pending')->count() }}</span>
      <span class="badge b-blue" id="cntPreparing">Cooking: {{ $kots->where('status','preparing')->count() }}</span>
      <span class="badge b-green" id="cntReady">Ready: {{ $kots->where('status','ready')->count() }}</span>
      <button type="button" id="soundToggleBtn" class="btn btn-sm btn-emerald">🔔 Alert Sound: ON</button>
      <button type="button" id="stockToggleBtn" class="btn btn-sm btn-dark">86 / Stock Out</button>
    </div>
  </div>

  @if (session('status'))
    <div class="flash flash-ok" style="margin-bottom:14px"><span>{{ session('status') }}</span></div>
  @endif

  <!-- Live KOT Grid -->
  <div id="kdsGrid" class="kds-grid">
    @forelse ($kots as $kot)
      <div class="kot-card {{ $kot->status }}">
        <div class="kot-head">
          <div>
            <div style="font-size:1.1rem;font-weight:900">{{ $kot->order?->label() ?? 'Order' }}</div>
            <div class="mono" style="font-size:0.75rem;color:#cbd5e1">{{ $kot->kot_no }} • {{ $kot->createdBy?->name ?? 'Staff' }} • {{ $kot->created_at?->format('h:i A') }}</div>
          </div>
          <div style="text-align:right">
            <span class="badge {{ $kot->status === 'ready' ? 'b-green' : ($kot->status === 'preparing' ? 'b-blue' : 'b-amber') }}">{{ strtoupper($kot->status) }}</span>
            <div class="mono" style="font-size:0.78rem;margin-top:4px;color:#fbbf24">{{ (int) $kot->created_at?->diffInMinutes(now()) }} min</div>
          </div>
        </div>

        <div class="kot-items">
          @foreach ($kot->items->where('status', '!=', 'cancelled') as $it)
            <div class="kot-item-row">
              <div>
                <span class="veg-mark {{ $it->is_veg ? '' : 'nonveg' }}" style="margin-right:6px;vertical-align:middle"></span>
                <span>{{ $it->item_name }}</span>
                @if ($it->special_note)
                  <span class="kot-note">★ {{ $it->special_note }}</span>
                @endif
              </div>
              <span class="kot-qty mono">×{{ $it->quantity }}</span>
            </div>
          @endforeach
          @if ($kot->notes)
            <div style="background:rgba(250,204,21,0.16);border:1px solid rgba(250,204,21,0.4);color:#fde047;padding:8px 10px;border-radius:8px;font-size:0.82rem;font-weight:700">
              NOTE: {{ $kot->notes }}
            </div>
          @endif
        </div>

        <div class="kot-actions">
          @if ($kot->status === 'pending')
            <form method="POST" action="{{ route('kitchen.kot.status', $kot) }}" style="flex:1">
              @csrf @method('PATCH')
              <input type="hidden" name="status" value="preparing" />
              <button type="submit" class="btn btn-blue" style="width:100%">Start Cooking</button>
            </form>
          @endif
          @if (in_array($kot->status, ['pending', 'preparing']))
            <form method="POST" action="{{ route('kitchen.kot.status', $kot) }}" style="flex:1">
              @csrf @method('PATCH')
              <input type="hidden" name="status" value="ready" />
              <button type="submit" class="btn btn-emerald" style="width:100%">Mark Ready ✓</button>
            </form>
          @endif
          @if ($kot->status === 'ready')
            <form method="POST" action="{{ route('kitchen.kot.status', $kot) }}" style="flex:1">
              @csrf @method('PATCH')
              <input type="hidden" name="status" value="served" />
              <button type="submit" class="btn btn-dark" style="width:100%">Picked Up / Served</button>
            </form>
          @endif
          <a href="{{ route('print.kot', $kot) }}" target="_blank" class="btn btn-dark" title="Print KOT">🖨</a>
        </div>
      </div>
    @empty
      <div style="grid-column:1/-1;text-align:center;padding:80px 20px;color:#94a3b8;background:#1e293b;border-radius:16px;border:1px dashed #334155">
        <h3 style="margin:0 0 6px;color:#f8fafc">No Pending KOTs</h3>
        <p style="margin:0;font-size:0.88rem">New waiter orders will pop up here automatically with an alert chime!</p>
      </div>
    @endforelse
  </div>

  <!-- 86 / Out of Stock Drawer -->
  <div id="stockDrawer" class="hidden" style="margin-top:24px;background:#1e293b;border:1px solid #334155;border-radius:16px;padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="margin:0;color:#fff">Quick Stock Out (86 Menu Items)</h3>
      <button type="button" class="btn btn-sm btn-dark" onclick="document.getElementById('stockDrawer').classList.add('hidden')">Close</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px">
      @foreach ($menuItems as $mi)
        <form method="POST" action="{{ route('kitchen.menu.toggle', $mi) }}" style="display:flex;justify-content:space-between;align-items:center;background:#0f172a;padding:8px 10px;border-radius:10px;border:1px solid #334155">
          @csrf @method('PATCH')
          <span style="font-size:0.82rem;font-weight:700;color:{{ $mi->is_available ? '#f8fafc' : '#94a3b8;text-decoration:line-through' }}">{{ $mi->name }}</span>
          <button type="submit" class="btn btn-sm {{ $mi->is_available ? 'btn-emerald' : 'btn-danger' }}">
            {{ $mi->is_available ? 'In Stock' : 'Sold Out' }}
          </button>
        </form>
      @endforeach
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    const csrf = "{{ csrf_token() }}";
    let latestKotId = {{ (int) $latestKotId }};
    let soundOn = true;

    const soundBtn = document.getElementById("soundToggleBtn");
    soundBtn.addEventListener("click", () => {
      soundOn = !soundOn;
      soundBtn.textContent = soundOn ? "🔔 Alert Sound: ON" : "🔕 Alert Sound: OFF";
      soundBtn.className = "btn btn-sm " + (soundOn ? "btn-emerald" : "btn-dark");
      if (soundOn) playChime();
    });

    document.getElementById("stockToggleBtn").addEventListener("click", () => {
      document.getElementById("stockDrawer").classList.toggle("hidden");
    });

    // Offline pure Web Audio API chime (zero audio files or internet needed!)
    function playChime() {
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = "triangle";
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1174.66, ctx.currentTime + 0.16);
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.45);
      } catch (_) {}
    }

    function esc(s) {
      return String(s ?? "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }

    function renderKots(data) {
      document.getElementById("kdsClock").textContent = data.server_time || "";
      document.getElementById("cntPending").textContent = "Pending: " + (data.counts?.pending ?? 0);
      document.getElementById("cntPreparing").textContent = "Cooking: " + (data.counts?.preparing ?? 0);
      document.getElementById("cntReady").textContent = "Ready: " + (data.counts?.ready ?? 0);

      if (data.latest_kot_id > latestKotId) {
        latestKotId = data.latest_kot_id;
        if (soundOn) playChime();
      }

      const grid = document.getElementById("kdsGrid");
      const list = data.kots || [];
      if (!list.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:80px 20px;color:#94a3b8;background:#1e293b;border-radius:16px;border:1px dashed #334155"><h3 style="margin:0 0 6px;color:#f8fafc">No Pending KOTs</h3><p style="margin:0;font-size:0.88rem">New waiter orders will pop up here automatically with an alert chime!</p></div>';
        return;
      }

      grid.innerHTML = list.map((k) => {
        const badgeCls = k.status === "ready" ? "b-green" : k.status === "preparing" ? "b-blue" : "b-amber";
        const timeColor = k.elapsed_mins >= 20 ? "#f87171" : k.elapsed_mins >= 10 ? "#fbbf24" : "#94a3b8";
        return `
          <div class="kot-card ${esc(k.status)}">
            <div class="kot-head">
              <div>
                <div style="font-size:1.1rem;font-weight:900">${esc(k.table_label)}</div>
                <div class="mono" style="font-size:0.75rem;color:#cbd5e1">${esc(k.kot_no)} • ${esc(k.waiter)} • ${esc(k.created_time)}</div>
              </div>
              <div style="text-align:right">
                <span class="badge ${badgeCls}">${esc(k.status).toUpperCase()}</span>
                <div class="mono" style="font-size:0.78rem;margin-top:4px;color:${timeColor}">${k.elapsed_mins} min</div>
              </div>
            </div>
            <div class="kot-items">
              ${(k.items || []).map((it) => `
                <div class="kot-item-row">
                  <div>
                    <span class="veg-mark ${it.is_veg ? "" : "nonveg"}" style="margin-right:6px;vertical-align:middle"></span>
                    <span>${esc(it.name)}</span>
                    ${it.note ? `<span class="kot-note">★ ${esc(it.note)}</span>` : ""}
                  </div>
                  <span class="kot-qty mono">×${it.qty}</span>
                </div>`).join("")}
              ${k.notes ? `<div style="background:rgba(250,204,21,0.16);border:1px solid rgba(250,204,21,0.4);color:#fde047;padding:8px 10px;border-radius:8px;font-size:0.82rem;font-weight:700">NOTE: ${esc(k.notes)}</div>` : ""}
            </div>
            <div class="kot-actions">
              ${k.status === "pending" ? `<button type="button" class="btn btn-blue" style="flex:1" onclick="window.__kdsStatus('${esc(k.status_url)}','preparing')">Start Cooking</button>` : ""}
              ${(k.status === "pending" || k.status === "preparing") ? `<button type="button" class="btn btn-emerald" style="flex:1" onclick="window.__kdsStatus('${esc(k.status_url)}','ready')">Mark Ready ✓</button>` : ""}
              ${k.status === "ready" ? `<button type="button" class="btn btn-dark" style="flex:1" onclick="window.__kdsStatus('${esc(k.status_url)}','served')">Picked Up / Served</button>` : ""}
              <a href="${esc(k.print_url)}" target="_blank" class="btn btn-dark" title="Print KOT">🖨</a>
            </div>
          </div>`;
      }).join("");
    }

    function pollKds() {
      fetch("{{ route('api.kitchen.live') }}", { headers: { Accept: "application/json" } })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => { if (data) renderKots(data); })
        .catch(() => {});
    }

    window.__kdsStatus = function (url, status) {
      fetch(url, {
        method: "PATCH",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
        body: JSON.stringify({ status })
      }).then(() => pollKds());
    };

    setInterval(() => { if (!document.hidden) pollKds(); }, 3000);
  })();
</script>
@endpush
