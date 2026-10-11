@use('App\Support\Sar')
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Delivery slip {{ $order->order_no }}</title>
<style>
    @page { margin: 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #000; }
    h1 { font-size: 22px; margin: 0 0 2px; }
    .box { border: 2px solid #000; padding: 8px; margin: 10px 0; }
    .collect { font-size: 20px; font-weight: bold; }
    .warn { color: #c0392b; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 4px 0; border-bottom: 1px solid #ccc; vertical-align: top; }
    .r { text-align: right; }
    .lbl { color: #555; width: 28%; }
    .small { font-size: 9px; word-break: break-all; }
</style>
</head>
<body>
    <h1>{{ $order->order_no }}</h1>
    <div>{{ $shop['name'] }} · Delivery slip</div>

    <div class="box">
        <div>Collect from customer</div>
        <div class="collect">{{ Sar::format($order->amount_due_on_delivery) }}</div>
        @if (bccomp((string) $order->paid_online_total, '0', 2) === 1)
            <div>Already paid in advance: {{ Sar::format($order->paid_online_total) }}</div>
        @endif
        @if ($unverifiedPrepay)
            <div class="warn">An advance payment is not verified yet. Collect the full amount above.</div>
        @endif
        <div>Pay by cash or card</div>
    </div>

    @if ($status === 'cancelled')
        <p class="warn">THIS ORDER WAS CANCELLED. DO NOT DELIVER.</p>
    @endif

    <table>
        <tr><td class="lbl">Customer</td><td>{{ $order->customer_name }}</td></tr>
        <tr><td class="lbl">Phone</td><td>{{ $order->phone_raw ?: '-' }}</td></tr>
        <tr><td class="lbl">Area</td><td>{{ $order->area?->name ?? '-' }}</td></tr>
        @if ($order->slot_label)
            <tr><td class="lbl">Time</td><td>{{ $order->slot_label }}</td></tr>
        @endif
        @if ($order->customer_notes)
            <tr><td class="lbl">Notes</td><td>{{ $order->customer_notes }}</td></tr>
        @endif
        @if ($mapsUrl)
            <tr><td class="lbl">Map pin</td><td class="small">{{ $mapsUrl }}</td></tr>
        @else
            <tr><td class="lbl">Map pin</td><td>No pin. Ask the customer on WhatsApp.</td></tr>
        @endif
    </table>

    <p><b>Items</b></p>
    <table>
        @foreach ($order->items as $item)
            <tr><td>{{ $item->product_name }}</td><td class="r"><b>x {{ $item->qty }}</b></td></tr>
        @endforeach
    </table>
</body>
</html>