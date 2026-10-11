@use('App\Support\Sar')
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Order receipt {{ $order->order_no }}</title>
<style>
    @page { margin: 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #241533; }
    h1 { font-size: 18px; margin: 0; color: #5b2f8a; }
    h2 { font-size: 13px; margin: 14px 0 4px; }
    .muted { color: #6b5c7d; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 10px; color: #6b5c7d; border-bottom: 1px solid #ccc; padding: 4px 0; }
    td { padding: 4px 0; border-bottom: 1px solid #eee; vertical-align: top; }
    .r { text-align: right; }
    .tot td { border: 0; padding: 2px 0; }
    .big td { font-size: 13px; font-weight: bold; border-top: 1px solid #241533; padding-top: 6px; }
    .stamp { color: #c0392b; font-weight: bold; font-size: 14px; }
    .foot { margin-top: 16px; font-size: 9px; color: #6b5c7d; }
</style>
</head>
<body>
    <h1>{{ $shop['name'] }}</h1>
    <div class="muted">
        {{ $shop['url'] }}<br>
        @if ($shop['email']) {{ $shop['email'] }}@endif
        @if ($shop['phone']) · {{ $shop['phone'] }}@endif
    </div>

    <h2>Order receipt</h2>
    <table class="tot">
        <tr><td>Order</td><td class="r"><b>{{ $order->order_no }}</b></td></tr>
        <tr><td>Date</td><td class="r">{{ $order->created_at?->format('d M Y, H:i') }}</td></tr>
        <tr><td>Customer</td><td class="r">{{ $order->customer_name }}</td></tr>
        <tr><td>Area</td><td class="r">{{ $order->area?->name ?? '-' }}</td></tr>
        @if ($order->slot_label)
            <tr><td>Delivery time</td><td class="r">{{ $order->slot_label }}</td></tr>
        @endif
    </table>

    @if ($status === 'cancelled')
        <p class="stamp">CANCELLED</p>
    @endif

    <table style="margin-top:10px">
        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr></thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td class="r">{{ $item->qty }}</td>
                <td class="r">{{ Sar::format($item->unit_price) }}</td>
                <td class="r">{{ Sar::format($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="tot" style="margin-top:8px">
        <tr><td>Items</td><td class="r">{{ Sar::format($order->subtotal) }}</td></tr>
        <tr><td>Delivery</td><td class="r">{{ bccomp((string) $order->delivery_fee, '0', 2) === 0 ? 'Free' : Sar::format($order->delivery_fee) }}</td></tr>
        <tr class="big"><td>Total</td><td class="r">{{ Sar::format($order->total) }}</td></tr>
        @if (bccomp((string) $order->paid_online_total, '0', 2) === 1)
            <tr><td>Paid in advance (verified)</td><td class="r">- {{ Sar::format($order->paid_online_total) }}</td></tr>
        @endif
        <tr><td><b>To pay on delivery</b></td><td class="r"><b>{{ Sar::format($order->amount_due_on_delivery) }}</b></td></tr>
    </table>

    <p class="foot">
        Salamat! Pay cash or card to the rider on delivery.<br>
        This is an order receipt, not a tax invoice.
    </p>
</body>
</html>