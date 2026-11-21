<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff PIN Login — {{ $settings['hotel_name'] ?? 'Hotel POS' }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}" />
  <link rel="manifest" href="{{ asset('manifest.json') }}" />
  <meta name="theme-color" content="#ea580c" />
  <link rel="stylesheet" href="{{ asset('css/pos.css') }}" />
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <img src="{{ asset('icon.svg') }}" alt="Logo" style="width:56px;height:56px;margin-bottom:8px" />
      <h2 style="margin:0;font-size:1.3rem;font-weight:900">{{ $settings['hotel_name'] ?? 'Royal Hotel POS' }}</h2>
      <p style="margin:4px 0 0;font-size:0.82rem;color:var(--text-muted)">Tap your Staff PIN to open Waiter, Kitchen, Billing, or Owner panel</p>

      <form id="pinForm" method="POST" action="{{ route('pos.login.submit') }}">
        @csrf
        <input id="pinField" class="pin-display" type="password" name="pin" inputmode="numeric" maxlength="6" placeholder="••••" autocomplete="off" required autofocus />

        @error('pin')
          <p style="color:var(--rose);font-size:0.82rem;font-weight:700;margin:0 0 10px">{{ $message }}</p>
        @enderror
        @if (session('status'))
          <p style="color:var(--emerald);font-size:0.82rem;font-weight:700;margin:0 0 10px">{{ session('status') }}</p>
        @endif

        <div class="keypad">
          @foreach ([1,2,3,4,5,6,7,8,9] as $n)
            <button type="button" class="key-btn" data-key="{{ $n }}">{{ $n }}</button>
          @endforeach
          <button type="button" class="key-btn action" id="clearKey">Clear</button>
          <button type="button" class="key-btn" data-key="0">0</button>
          <button type="submit" class="key-btn go">Login →</button>
        </div>
      </form>

      <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);margin-bottom:6px">Quick Demo Staff PINs (Tap to Login):</div>
      <div class="demo-pins">
        @foreach ($activeStaff as $st)
          <button type="button" class="demo-pin-chip" data-quick-pin="{{ $st->pin_code }}">
            {{ $st->name }}: <span class="mono">{{ $st->pin_code }}</span>
          </button>
        @endforeach
      </div>
    </div>
  </div>

  <script>
    (function () {
      const inp = document.getElementById("pinField");
      const form = document.getElementById("pinForm");
      document.querySelectorAll("[data-key]").forEach((b) => {
        b.addEventListener("click", () => {
          if (inp.value.length < 6) inp.value += b.dataset.key;
        });
      });
      document.getElementById("clearKey").addEventListener("click", () => {
        inp.value = "";
        inp.focus();
      });
      document.querySelectorAll("[data-quick-pin]").forEach((b) => {
        b.addEventListener("click", () => {
          inp.value = b.dataset.quickPin;
          form.submit();
        });
      });
    })();
  </script>
</body>
</html>
