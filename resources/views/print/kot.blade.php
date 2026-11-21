<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>{{ $kot->kot_no }} — Kitchen Order Ticket</title>
  @php $widthMm = ($settings['paper_width'] ?? '80') === '58' ? '58mm' : '80mm'; @endphp
  <style>
    @page { size: {{ $widthMm }} auto; margin: 2mm; }
    body { font-family: "Courier New", Courier, monospace; font-size: 13px; color: #000; width: {{ $widthMm }}; margin: 0 auto; padding: 6px; }
    .center { text-align: center; }
    .bold { font-weight: 700; }
    .hr { border-top: 1px dashed #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; margin: 4px 0; font-size: 14px; font-weight: 700; }
    .no-print { margin-bottom: 10px; text-align: center; }
    .no-print button { padding: 7px 12px; font-weight: 700; cursor: pointer; background: #ea580c; color: #fff; border: none; border-radius: 6px; }
    @media print { .no-print { display: none !important; } body { padding: 0; } }
  </style>
</head>
<body>
  <div class="no-print">
    <button onclick="window.print()">🖨 Print KOT ({{ $widthMm }})</button>
    <button onclick="window.close()" style="background:#334155">Close</button>
  </div>

  <div class="center bold" style="font-size:16px">*** KITCHEN KOT ***</div>
  <div class="center bold" style="font-size:15px">{{ $kot->order?->label() }}</div>
  <div class="hr"></div>
  <div><strong>KOT No:</strong> {{ $kot->kot_no }}</div>
  <div><strong>Waiter:</strong> {{ $kot->createdBy?->name ?? 'Staff' }}</div>
  <div><strong>Time:</strong> {{ $kot->created_at?->format('d-m-Y h:i A') }}</div>
  <div class="hr"></div>

  @foreach ($kot->items->where('status', '!=', 'cancelled') as $item)
    <div class="row">
      <span>{{ $item->item_name }}</span>
      <span>x {{ $item->quantity }}</span>
    </div>
    @if ($item->special_note)
      <div style="font-size:12px;margin-bottom:4px">  >> NOTE: {{ $item->special_note }}</div>
    @endif
  @endforeach

  @if ($kot->notes)
    <div class="hr"></div>
    <div class="bold">KITCHEN NOTE: {{ $kot->notes }}</div>
  @endif

  <div class="hr"></div>
</body>
</html>
