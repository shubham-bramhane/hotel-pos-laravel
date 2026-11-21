<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Investor Link Unavailable — {{ $settings['hotel_name'] ?? 'Hotel POS' }}</title>
  <link rel="stylesheet" href="{{ asset('css/pos.css') }}" />
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <div class="badge b-red" style="margin-bottom:12px">Link Expired or Revoked</div>
      <h2 style="margin:0 0 8px;font-size:1.35rem;font-weight:900">Investor Report Unavailable</h2>
      <p style="margin:0;font-size:0.88rem;color:var(--text-sec);line-height:1.55">
        This read-only investor link has either expired, been revoked by the owner, or is invalid. Please request a fresh performance link from the hotel owner.
      </p>
    </div>
  </div>
</body>
</html>
