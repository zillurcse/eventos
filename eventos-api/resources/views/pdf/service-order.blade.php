@php
    $money = fn ($n) => $order['currency'].' '.number_format((float) $n, 2);
    $statusColors = [
        'pending' => ['#fef3c7', '#b45309'],
        'partial' => ['#dbeafe', '#1d4ed8'],
        'approved' => ['#dcfce7', '#15803d'],
        'rejected' => ['#fee2e2', '#b91c1c'],
    ];
    [$badgeBg, $badgeFg] = $statusColors[$order['status']] ?? $statusColors['pending'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order['order_number'] }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2430; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .header { border-bottom: 2px solid #6352e7; padding-bottom: 12px; margin-bottom: 18px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 10px;
                 font-weight: bold; background: {{ $badgeBg }}; color: {{ $badgeFg }}; text-transform: capitalize; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { color: #6b7280; width: 90px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .06em;
                         color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 8px 6px; }
        table.items td { padding: 10px 6px; border-bottom: 1px solid #f1f2f5; vertical-align: top; }
        .right { text-align: right; }
        .name { font-weight: bold; }
        .line-status { font-size: 9px; text-transform: capitalize; }
        .totals { width: 45%; margin-left: 55%; margin-top: 16px; border-collapse: collapse; }
        .totals td { padding: 6px 0; }
        .totals .grand td { border-top: 1.5px solid #1f2430; font-size: 13px; font-weight: bold; padding-top: 10px; }
        .foot { margin-top: 28px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td><h1>Service Request</h1><div class="muted">Order {{ $order['order_number'] }}</div></td>
                <td class="right"><span class="badge">{{ $order['status'] }}</span></td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr><td class="label">Exhibitor</td><td>{{ $order['exhibitor']['name'] ?? '—' }}</td></tr>
        <tr><td class="label">Order date</td>
            <td>{{ $order['date'] ? \Illuminate\Support\Carbon::parse($order['date'])->format('M j, Y') : '—' }}</td></tr>
        <tr><td class="label">Items</td><td>{{ $order['lines_count'] }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Service</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Total</th>
                <th class="right">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order['items'] as $item)
                <tr>
                    <td>
                        <div class="name">{{ $item['name'] }}</div>
                        @if ($item['category'])
                            <div class="muted">{{ $item['category'] }}@if ($item['unit']) · {{ $item['unit'] }}@endif</div>
                        @endif
                    </td>
                    <td class="right">{{ $item['quantity'] }}</td>
                    <td class="right">{{ $money($item['unit_price']) }}</td>
                    <td class="right">{{ $money($item['line_total']) }}</td>
                    <td class="right line-status">{{ $item['status'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="right">{{ $money($order['subtotal']) }}</td></tr>
        <tr><td class="muted">Tax</td><td class="right">{{ $money($order['tax_total']) }}</td></tr>
        <tr class="grand"><td>Total Amount</td><td class="right">{{ $money($order['total']) }}</td></tr>
    </table>

    <div class="foot">Generated {{ now()->format('M j, Y H:i') }}</div>
</body>
</html>
