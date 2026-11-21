@extends('layouts.pos')
@section('title', 'Hotel & Printer Settings')

@section('content')
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:18px;align-items:start">
  <div class="panel">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Hotel Profile, GST &amp; Thermal Printer Settings</h3>
        <p class="panel-sub">Printed on every Customer Bill &amp; KOT</p>
      </div>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('owner.settings.save') }}" class="form-grid">
        @csrf
        <div class="form-group full">
          <label class="form-label">Hotel / Restaurant Name</label>
          <input type="text" name="hotel_name" class="input" value="{{ $settings['hotel_name'] }}" required />
        </div>

        <div class="form-group full">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="input" value="{{ $settings['address'] }}" />
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

        <div class="form-group full">
          <button type="submit" class="btn btn-brand">Save Settings</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Local Database Backup Card -->
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
</div>
@endsection
