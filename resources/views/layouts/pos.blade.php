<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'POS') — {{ $settings['hotel_name'] ?? 'Hotel POS' }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}" />
  <link rel="manifest" href="{{ asset('manifest.json') }}" />
  <meta name="theme-color" content="#ea580c" />
  <link rel="stylesheet" href="{{ asset('css/pos.css') }}" />
</head>
<body>
  <header class="pos-nav">
    <div class="pos-brand">
      <div class="pos-brand-icon">
        <img src="{{ asset('icon.svg') }}" alt="Logo" />
      </div>
      <div>
        <h1>{{ $settings['hotel_name'] ?? 'Royal Hotel POS' }}</h1>
        <span>Local LAN Server • Offline-Ready POS</span>
      </div>
    </div>

    <nav class="pos-nav-links">
      @if ($currentStaff->canAccess('waiter', 'cashier'))
        <a class="pos-nav-link {{ request()->routeIs('waiter.*') ? 'active' : '' }}" href="{{ route('waiter.index') }}">Waiter / Tables</a>
      @endif
      @if ($currentStaff->canAccess('kitchen', 'cashier'))
        <a class="pos-nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}" href="{{ route('kitchen.index') }}">Kitchen KDS</a>
      @endif
      @if ($currentStaff->canAccess('cashier'))
        <a class="pos-nav-link {{ request()->routeIs('counter.*') ? 'active' : '' }}" href="{{ route('counter.index') }}">Billing Counter</a>
      @endif
      @if ($currentStaff->role === 'owner')
        <a class="pos-nav-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}" href="{{ route('owner.dashboard') }}">Owner Reports</a>
        <a class="pos-nav-link {{ request()->routeIs('owner.menu') ? 'active' : '' }}" href="{{ route('owner.menu') }}">Menu</a>
        <a class="pos-nav-link {{ request()->routeIs('owner.tables') ? 'active' : '' }}" href="{{ route('owner.tables') }}">Tables</a>
        <a class="pos-nav-link {{ request()->routeIs('owner.staff') ? 'active' : '' }}" href="{{ route('owner.staff') }}">Staff</a>
        <a class="pos-nav-link {{ request()->routeIs('owner.audit') ? 'active' : '' }}" href="{{ route('owner.audit') }}">Audit</a>
        <a class="pos-nav-link {{ request()->routeIs('owner.settings') ? 'active' : '' }}" href="{{ route('owner.settings') }}">Settings</a>
      @endif
    </nav>

    <div class="pos-nav-right">
      <span class="staff-chip">
        <span class="role-dot"></span>
        {{ $currentStaff->name }} ({{ ucfirst($currentStaff->role) }})
      </span>
      <form method="POST" action="{{ route('pos.logout') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-dark">Switch PIN</button>
      </form>
    </div>
  </header>

  @hasSection('fullbleed')
    @yield('fullbleed')
  @else
    <main class="pos-main">
      @if (session('status'))
        <div class="flash flash-ok">
          <span>{{ session('status') }}</span>
          @if (session('print_bill_url'))
            <a href="{{ session('print_bill_url') }}" target="_blank" class="btn btn-sm btn-emerald">Print Thermal Bill 🖨</a>
          @endif
        </div>
      @endif

      @if (session('error'))
        <div class="flash flash-err">
          <span>{{ session('error') }}</span>
        </div>
      @endif

      @if ($errors->any())
        <div class="flash flash-err">
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      @yield('content')
    </main>
  @endif

  @stack('scripts')
</body>
</html>
