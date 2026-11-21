@extends('layouts.pos')
@section('title', 'Hotel, Printer & Cloud AI Settings')

@section('content')
<div style="display:grid;grid-template-columns:1.55fr 1fr;gap:18px;align-items:start">
  <form method="POST" action="{{ route('owner.settings.save') }}">
    @csrf

    <!-- Core Hotel & Thermal Printer Settings (75% Offline POS) -->
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Hotel Profile, GST &amp; Thermal Printer Settings</h3>
          <p class="panel-sub">75% Offline Core • Printed on every Customer Bill &amp; KOT</p>
        </div>
        <span class="badge b-green">Offline Core</span>
      </div>
      <div class="panel-body">
        <div class="form-grid">
          <div class="form-group full">
            <label class="form-label">Hotel / Restaurant Name</label>
            <input type="text" name="hotel_name" class="input" value="{{ $settings['hotel_name'] }}" required />
          </div>

          <div class="form-group full">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="input" value="{{ $settings['address'] }}" />
          </div>

          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" name="outlet_city" class="input" value="{{ $settings['outlet_city'] }}" placeholder="Yavatmal" />
          </div>

          <div class="form-group">
            <label class="form-label">State</label>
            <input type="text" name="outlet_state" class="input" value="{{ $settings['outlet_state'] }}" placeholder="Maharashtra" />
          </div>

          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="input mono" value="{{ $settings['phone'] }}" />
          </div>

          <div class="form-group">
            <label class="form-label">GSTIN (Optional)</label>
            <input type="text" name="gstin" class="input mono" value="{{ $settings['gstin'] }}" placeholder="27XXXXX1234X1Z5" />
          </div>

          <div class="form-group">
            <label class="form-label">GST Billing Enabled?</label>
            <label style="display:flex;align-items:center;gap:8px;padding:10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--surface-alt);font-weight:700;font-size:0.84rem">
              <input type="checkbox" name="gst_enabled" value="1" @checked(($settings['gst_enabled'] ?? '1') === '1') />
              Enable CGST + SGST on New Bills
            </label>
          </div>

          <div class="form-group">
            <label class="form-label">Total GST Rate (%)</label>
            <input type="number" step="0.5" name="gst_rate" class="input mono" value="{{ $settings['gst_rate'] }}" />
          </div>

          <div class="form-group">
            <label class="form-label">Thermal Printer Roll Size</label>
            <select name="paper_width" class="select">
              <option value="80" @selected(($settings['paper_width'] ?? '80') === '80')>80mm (Standard Hotel Printer)</option>
              <option value="58" @selected(($settings['paper_width'] ?? '80') === '58')>58mm (Compact Mini Printer)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Hotel UPI ID (Printed on Bill)</label>
            <input type="text" name="upi_id" class="input mono" value="{{ $settings['upi_id'] }}" placeholder="royalhotel@upi" />
          </div>

          <div class="form-group full">
            <label class="form-label">Bill Footer Message</label>
            <input type="text" name="bill_footer" class="input" value="{{ $settings['bill_footer'] }}" />
          </div>
        </div>
      </div>
    </div>

    <!-- 25% Cloud AI & Nightly Sync Settings -->
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Hybrid Cloud Sync &amp; Google Gemini AI (25% Online Layer)</h3>
          <p class="panel-sub">Nightly automatic snapshot sync to live server for multi-branch benchmarking &amp; investor reports</p>
        </div>
        <span class="badge b-blue">25% Cloud AI</span>
      </div>
      <div class="panel-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Server Deployment Mode</label>
            <select name="pos_mode" class="select">
              <option value="outlet" @selected(($settings['pos_mode'] ?? 'outlet') === 'outlet')>Local Hotel Outlet (75% Offline + Nightly Sync)</option>
              <option value="cloud_hub" @selected(($settings['pos_mode'] ?? 'outlet') === 'cloud_hub')>Live Cloud Hub (Receives Multi-Hotel Syncs)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Unique Outlet / Branch Code</label>
            <input type="text" name="outlet_code" class="input mono" value="{{ $settings['outlet_code'] }}" placeholder="ROYAL-YTL-01" />
          </div>

          <div class="form-group">
            <label class="form-label">Nightly Cloud Sync Enabled?</label>
            <label style="display:flex;align-items:center;gap:8px;padding:10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--surface-alt);font-weight:700;font-size:0.84rem">
              <input type="checkbox" name="cloud_sync_enabled" value="1" @checked(($settings['cloud_sync_enabled'] ?? '0') === '1') />
              Sync Daily Snapshots Every Night
            </label>
          </div>

          <div class="form-group">
            <label class="form-label">Nightly Sync Time (24h)</label>
            <input type="text" name="nightly_sync_time" class="input mono" value="{{ $settings['nightly_sync_time'] }}" placeholder="01:00" />
          </div>

          <div class="form-group full">
            <label class="form-label">Live Cloud Hub Sync Endpoint URL (For Outlet Mode)</label>
            <input type="url" name="cloud_sync_url" class="input mono" value="{{ $settings['cloud_sync_url'] }}" placeholder="https://analytics.yourhotel.com/api/cloud/sync" />
          </div>

          <div class="form-group full">
            <label class="form-label">Shared Cloud Sync Secret Token {{ !empty($settings['cloud_sync_token']) ? '(Configured — leave blank to keep)' : '' }}</label>
            <input type="password" name="cloud_sync_token" class="input mono" value="" placeholder="{{ !empty($settings['cloud_sync_token']) ? '•••••••••••••• (Saved)' : 'Paste strong secret token shared between Outlet & Cloud Hub' }}" />
          </div>

          <div class="form-group">
            <label class="form-label">Google Gemini API Key {{ !empty($settings['gemini_api_key']) ? '(Configured — leave blank to keep)' : '(Optional)' }}</label>
            <input type="password" name="gemini_api_key" class="input mono" value="" placeholder="{{ !empty($settings['gemini_api_key']) ? '•••••••••••••• (Saved)' : 'AIza... (blank = offline statistical AI fallback)' }}" />
          </div>

          <div class="form-group">
            <label class="form-label">Gemini Model (Interactions API v1)</label>
            <select name="gemini_model" class="select mono">
              <option value="gemini-4-hydrogen" @selected(($settings['gemini_model'] ?? 'gemini-4-hydrogen') === 'gemini-4-hydrogen')>gemini-4-hydrogen (Recommended Fast)</option>
              <option value="gemini-4-argon" @selected(($settings['gemini_model'] ?? '') === 'gemini-4-argon')>gemini-4-argon (Deep Reasoning)</option>
              <option value="gemini-3.5-flash-lite" @selected(($settings['gemini_model'] ?? '') === 'gemini-3.5-flash-lite')>gemini-3.5-flash-lite (Ultra Low Cost)</option>
            </select>
          </div>

          <div class="form-group full">
            <label style="display:flex;align-items:center;gap:8px;padding:10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--surface-alt);font-weight:700;font-size:0.84rem">
              <input type="checkbox" name="ai_auto_nightly" value="1" @checked(($settings['ai_auto_nightly'] ?? '1') === '1') />
              Auto-generate refreshed AI Performance Summary after nightly sync
            </label>
          </div>

          <div class="form-group full">
            <button type="submit" class="btn btn-brand">Save All Settings</button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Right Column: Backup & Cloud Sync Info -->
  <div>
    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">Local Data Backup</h3>
          <p class="panel-sub">100% self-hosted MySQL (`hotel_pos`)</p>
        </div>
      </div>
      <div class="panel-body">
        <p style="font-size:0.84rem;color:var(--text-sec);margin-top:0">
          Save a complete snapshot of your Menu, Tables, Staff, Orders, Bills &amp; Audit Logs to your PC, pendrive, or Google Drive daily.
        </p>
        <form method="POST" action="{{ route('owner.backup') }}">
          @csrf
          <button type="submit" class="btn btn-emerald" style="width:100%">⬇ Download Full Database Backup (.json)</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div>
          <h3 class="panel-title">75% Offline / 25% Cloud Architecture</h3>
          <p class="panel-sub">How live sync &amp; investor sharing work together</p>
        </div>
      </div>
      <div class="panel-body" style="font-size:0.82rem;color:var(--text-sec);line-height:1.55">
        <p style="margin-top:0">
          <strong>75% Local Offline POS:</strong> Waiter phones, Kitchen KDS, Billing Counter, and Thermal Printing run strictly on local Wi-Fi — zero internet dependency during rush hours.
        </p>
        <p>
          <strong>25% Nightly Cloud &amp; AI Layer:</strong> Every night at <span class="mono">{{ $settings['nightly_sync_time'] ?? '01:00' }}</span>, <code class="mono">php artisan pos:sync-cloud --ai</code> sends signed daily financial snapshots to your Cloud Hub endpoint (retrying automatically if offline).
        </p>
        <p style="margin-bottom:12px">
          <strong>Live Inbound Endpoint (Cloud Hub Mode):</strong><br />
          <code class="mono" style="font-size:0.75rem;word-break:break-all">{{ url('/api/cloud/sync') }}</code>
        </p>
        <a href="{{ route('owner.analytics') }}" class="btn btn-blue" style="width:100%">Open AI Analytics &amp; Investor Links →</a>
      </div>
    </div>
  </div>
</div>
@endsection
