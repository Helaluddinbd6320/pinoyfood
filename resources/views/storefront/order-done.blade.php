<x-layouts.storefront title="Order {{ $order->order_no }} – Pinoy Food KSA">
    <div class="cx-page">
        <div class="cx-card cx-done">
            <div class="cx-tick" aria-hidden="true">✓</div>
            <h1 class="cx-h">Salamat, {{ \Illuminate\Support\Str::before($order->customer_name, ' ') }}!</h1>
            <p>Order <b>{{ $order->order_no }}</b> received</p>
            <p>{{ \App\Support\Sar::format($order->amount_due_on_delivery) }} to pay on delivery</p>
            <p>{{ $order->slot_label ?? 'We will confirm your delivery time on WhatsApp' }}@if ($area) · {{ $area->name }}@endif</p>
            <p>{{ $order->location_pin_url ? 'Location pin saved.' : 'No pin yet. Send one on WhatsApp so the rider finds you faster.' }}</p>
            <p class="cx-muted">You will get a WhatsApp message when we confirm.</p>
        </div>

        <div class="cx-card">
            <h2 class="cx-h2">Your order</h2>
            @foreach ($order->items as $item)
                <div class="cx-row">
                    <span>{{ $item->product_name }} × {{ $item->qty }}</span>
                    <span>{{ \App\Support\Sar::format($item->line_total) }}</span>
                </div>
            @endforeach
            <div class="cx-sm">
                <div><span>Items</span><span>{{ \App\Support\Sar::format($order->subtotal) }}</span></div>
                <div><span>Delivery</span><span>{{ bccomp((string) $order->delivery_fee, '0', 2) === 0 ? 'Free' : \App\Support\Sar::format($order->delivery_fee) }}</span></div>
                <div class="t"><span>Total</span><span>{{ \App\Support\Sar::format($order->total) }}</span></div>
            </div>
            <a class="cx-btn" href="{{ route('home') }}">Back to menu</a>
        </div>
    </div>
</x-layouts.storefront>