<x-layouts.storefront :title="'Payment for '.$order->order_no.' – '.config('app.name')">
    <div class="cx-page">
        <a class="cx-back" href="{{ route('home') }}">← Back to menu</a>
        <h1 class="cx-h">Order {{ $order->order_no }}</h1>

        <livewire:payment-proof-upload :payment-id="$payment->id" />
    </div>
</x-layouts.storefront>