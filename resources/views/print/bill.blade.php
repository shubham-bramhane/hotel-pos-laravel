<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Bill {{ $order->order_no }} — {{ $settings['hotel_name'] ?? 'Hotel POS' }}</title>
  @php $widthMm = ($settings['paper_width'] ?? '80') === '58' ? '58mm' : '80mm'; @endphp
  <style>
    @page { size: {{ $widthMm }} auto; margin: 2mm; }
    * { box-sizing: border-box; }
    body {
      font-family: "Courier New", Courier, monospace;
      font-size: 12px;
      line-height: 1.35;
      color: #000;
      width: {{ $widthMm }};
      margin: 0 auto;
      padding: 6px;
      background: #fff;
    }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: 700; }
    .hr { border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 2px 0; vertical-align: top; }
    th { border-bottom: 1px dashed #000; text-align: left; }
    .no-print { margin-bottom: 12px; text-align: center; }
    .no-print button { padding: 8px 14px; font-weight: 700; cursor: pointer; background: #ea580c; color: #fff; border: none; border-radius: 6px; }
    @media print { .no-print { display: none !important; } body { padding: 0; } }
  </style>
</head>
<body>
  <div class="no-print">
    <button onclick="window.print()">🖨 Print Thermal Bill ({{ $widthMm }})</button>
    <button onclick="window.close()" style="background:#334155">Close</button>
  </div>

  <div class="center">
    <div class="bold" style="font-size:15px">{{ strtoupper($settings['hotel_name'] ?? 'HOTEL POS') }}</div>
    @if (!empty($settings['address'])) <div>{{ $settings['address'] }}</div> @endif
    @if (!empty($settings['phone'])) <div>Ph: {{ $settings['phone'] }}</div> @endif
    @if (!empty($settings['gstin'])) <div class="bold">GSTIN: {{ $settings['gstin'] }}</div> @endif
  </div>

  <div class="hr"></div>
  <div><strong>Bill No:</strong> {{ $order->order_no }}</div>
  <div><strong>Table:</strong> {{ $order->label() }}</div>
  <div><strong>Date:</strong> {{ ($order->paid_at ?? now())->format('d-m-Y h:i A') }}</div>
  @if ($order->waiter) <div><strong>Waiter:</strong> {{ $order->waiter->name }}</div> @endif
  <div class="hr"></div>

  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th class="right">Qty</th>
        <th class="right">Rate</th>
        <th class="right">Amt</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($lines as $line)
        <tr>
          <td>{{ $line['name'] }}</td>
          <td class="right">{{ $line['qty'] }}</td>
          <td class="right">{{ number_format($line['unit_price'], 0) }}</td>
          <td class="right">{{ number_format($line['total'], 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="hr"></div>
  <table>
    <tr><td>Subtotal</td><td class="right">{{ number_format($order->subtotal, 2) }}</td></tr>
    @if ($order->discount_amount > 0)
      <tr><td>Discount</td><td class="right">-{{ number_format($order->discount_amount, 2) }}</td></tr>
    @endif
    @if ($order->gst_rate > 0)
      <tr><td>CGST ({{ $order->gst_rate / 2 }}%)</td><td class="right">{{ number_format($order->cgst_amount, 2) }}</td></tr>
      <tr><td>SGST ({{ $order->gst_rate / 2 }}%)</td><td class="right">{{ number_format($order->sgst_amount, 2) }}</td></tr>
    @endif
    @if ($order->round_off != 0)
      <tr><td>Round Off</td><td class="right">{{ number_format($order->round_off, 2) }}</td></tr>
    @endif
  </table>

  <div class="hr"></div>
  <table style="font-size:15px" class="bold">
    <tr>
      <td>NET PAYABLE</td>
      <td class="right">Rs. {{ number_format($order->grand_total, 2) }}</td>
    </tr>
  </table>
  <div class="hr"></div>

  @if ($order->payment_method)
    <div class="center bold">PAID VIA {{ strtoupper($order->payment_method) }}</div>
  @else
    <div class="center bold">PROFORMA BILL (UNPAID)</div>
  @endif

  @if (!empty($settings['upi_id']))
    <div class="center" style="margin-top:4px">UPI: {{ $settings['upi_id'] }}</div>
  @endif

  <div class="center" style="margin-top:8px">{{ $settings['bill_footer'] ?? 'Thank you! Visit Again.' }}</div>
</body>
</html>
