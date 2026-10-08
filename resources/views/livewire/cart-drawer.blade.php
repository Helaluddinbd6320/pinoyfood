@use('App\Support\Sar')
<div
    class="cartwrap"
    x-data="{ open: false, fab: false, toast: '', timer: null }"
    x-init="fab = window.scrollY > 240"
    x-on:scroll.window.passive="fab = window.scrollY > 240"
    x-on:keydown.escape.window="open = false"
    x-on:cart-toast.window="toast = $event.detail.message; clearTimeout(timer); timer = setTimeout(() => toast = '', 1800)"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''"
>
    <button type="button" class="cartbtn" aria-label="Open cart" x-on:click="open = true; $nextTick(() => $refs.close.focus())">
        Cart (<span>{{ $count }}</span>)
    </button>

    <button type="button" id="fab" aria-label="Open cart" x-bind:class="{ on: fab }" x-on:click="open = true; $nextTick(() => $refs.close.focus())">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 7H6"/></svg>
        <b>{{ $count }}</b>
    </button>

    <div class="ov" x-bind:class="{ on: open }" x-on:click="open = false"></div>

    <aside class="sheet" x-bind:class="{ on: open }" role="dialog" aria-modal="true" aria-label="Your cart" x-bind:aria-hidden="(!open).toString()">
        <div class="sh2">
            <h2>Your cart</h2>
            <button type="button" class="x" x-ref="close" aria-label="Close cart" x-on:click="open = false">✕</button>
        </div>

        @if ($count > 0 && $freeOn)
            <div class="fd">
                @if ($quote->freeDeliveryApplied)
                    <b>You got free delivery!</b>
                @else
                    <b>Add {{ Sar::format($remaining) }} more for free delivery</b>
                    <div class="fdbar" aria-hidden="true"><i style="width: {{ $progress }}%"></i></div>
                @endif
            </div>
        @endif

        <div class="lines">
            @forelse ($lines as $line)
                <div class="line" wire:key="line-{{ $line['id'] }}">
                    <div class="em" style="background: {{ $line['bg'] }}">
                        @if ($line['image'])
                            <img src="{{ $line['image'] }}" alt="">
                        @else
                            <span aria-hidden="true">{{ $line['emoji'] }}</span>
                        @endif
                    </div>
                    <div class="nm">
                        {{ $line['name'] }}
                        <small>{{ Sar::format($line['unit']) }} each · {{ Sar::format($line['total']) }}</small>
                    </div>
                    <div class="qty">
                        <button type="button" wire:click="decrement({{ $line['id'] }})" aria-label="Remove one {{ $line['name'] }}">−</button>
                        <b>{{ $line['qty'] }}</b>
                        <button type="button" wire:click="increment({{ $line['id'] }})" aria-label="Add one {{ $line['name'] }}">+</button>
                    </div>
                </div>
            @empty
                <div class="empty">Your cart is empty. Add something tasty.</div>
            @endforelse
        </div>

        {{-- Step 10: "Pairs well with" and "Picked for you" --}}
        <livewire:suggestions />

        <div class="foot">
            @if ($count > 0)
                <div class="sum">
                    <div><span>Items</span><span>{{ Sar::format($quote->subtotal) }}</span></div>
                    @if (bccomp((string) $quote->offerSavings, '0', 2) === 1)
                        <div class="save"><span>You save</span><span>{{ Sar::format($quote->offerSavings) }}</span></div>
                    @endif
                    <div>
                        <span>Delivery</span>
                        <span>{{ bccomp((string) $quote->deliveryFee, '0', 2) === 1 ? Sar::format($quote->deliveryFee) : 'Free' }}</span>
                    </div>
                </div>
            @endif
            <div class="tot"><span>Total</span><span>{{ Sar::format($quote->total) }}</span></div>
            <p class="note">The final delivery fee is confirmed at checkout once you choose your area. Pay cash or card on delivery.</p>

            @if ($count > 0 && ! $quote->meetsMinimum)
                <p class="note warn">Minimum order is {{ Sar::format($minOrder) }}.</p>
            @endif

            @if ($count > 0 && $quote->meetsMinimum && Route::has('checkout'))
                <a class="co" href="{{ route('checkout') }}">Checkout</a>
            @else
                <button type="button" class="co" disabled>Checkout</button>
            @endif
        </div>
    </aside>

    <div class="toast" x-bind:class="{ on: toast !== '' }" role="status" x-text="toast"></div>
</div>