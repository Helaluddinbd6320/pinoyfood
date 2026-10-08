<div class="cx-page">
    <a class="cx-back" href="{{ route('home') }}">← Back to menu</a>

    @if ($orderNo)
        <div class="cx-card cx-done">
            <div class="cx-tick">✓</div>
            <h1 class="cx-h">Order {{ $orderNo }} saved</h1>
            <p>One last step: send your order to us on WhatsApp so we can confirm it.</p>

            @if ($waUrl)
                <a class="cx-btn wa" href="{{ $waUrl }}" target="_blank" rel="noopener">Send on WhatsApp</a>
            @else
                <p class="cx-muted">We could not open WhatsApp from here. We will message you to confirm.</p>
            @endif

            @if ($doneUrl)
                <a class="cx-btn" href="{{ $doneUrl }}">View my order</a>
            @endif
        </div>
    @elseif ($items === [])
        <div class="cx-card">
            <h1 class="cx-h">Your cart is empty</h1>
            <a class="cx-btn" href="{{ route('home') }}#menu">Browse the menu</a>
        </div>
    @else
        <h1 class="cx-h">Order via WhatsApp</h1>
        <p class="cx-muted">We save your order first, then you send it to us on WhatsApp to confirm.</p>

        <form wire:submit="place" novalidate>
            <div class="cx-card">
                <h2 class="cx-h2">Your details</h2>

                <div class="cx-fg">
                    <label for="wo-name">Name</label>
                    <input class="cx-in" id="wo-name" wire:model.blur="name" autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name') <div class="cx-er">{{ $message }}</div> @enderror
                </div>

                <div class="cx-fg">
                    <label for="wo-phone">WhatsApp number</label>
                    <input class="cx-in" id="wo-phone" wire:model.blur="phone" inputmode="tel" autocomplete="tel" placeholder="05X XXX XXXX or +63 9XX XXX XXXX" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                    @error('phone') <div class="cx-er">{{ $message }}</div> @enderror
                </div>

                <div class="cx-fg">
                    <label for="wo-area">Area in Riyadh</label>
                    <select class="cx-in" id="wo-area" wire:model.live="areaId" aria-invalid="{{ $errors->has('areaId') ? 'true' : 'false' }}">
                        <option value="">Choose your area</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                    @error('areaId') <div class="cx-er">{{ $message }}</div> @enderror
                </div>

                <div class="cx-fg">
                    <label for="wo-notes">Notes <i>(optional)</i></label>
                    <input class="cx-in" id="wo-notes" wire:model.blur="notes" placeholder="Building, floor, gate code" maxlength="255">
                </div>

                {{-- Honeypot: hidden from people, bots fill it --}}
                <div class="cx-hp" aria-hidden="true">
                    <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                </div>
            </div>

            <div class="cx-card">
                <h2 class="cx-h2">Your order</h2>
                @foreach ($items as $id => $qty)
                    @php($p = $products->get($id))
                    @if ($p)
                        <div class="cx-row">
                            <span>{{ $p->name }} × {{ $qty }}</span>
                            <span>{{ \App\Support\Sar::format(bcmul((string) $p->effective_price, (string) $qty, 2)) }}</span>
                        </div>
                    @endif
                @endforeach

                <div class="cx-sm">
                    <div><span>Items</span><span>{{ \App\Support\Sar::format($quote->subtotal) }}</span></div>
                    @if (bccomp((string) $quote->offerSavings, '0', 2) > 0)
                        <div><span>Offer savings included</span><span>{{ \App\Support\Sar::format($quote->offerSavings) }}</span></div>
                    @endif
                    <div><span>Delivery</span><span>{{ bccomp((string) $quote->deliveryFee, '0', 2) === 0 ? 'Free' : \App\Support\Sar::format($quote->deliveryFee) }}</span></div>
                    <div class="t"><span>Total, pay on delivery</span><span>{{ \App\Support\Sar::format($quote->total) }}</span></div>
                </div>

                @if (! $quote->freeDeliveryApplied && $quote->amountToFreeDelivery !== null && bccomp((string) $quote->amountToFreeDelivery, '0', 2) > 0)
                    <p class="cx-muted">Add {{ \App\Support\Sar::format($quote->amountToFreeDelivery) }} more for free delivery.</p>
                @endif

                @unless ($quote->meetsMinimum)
                    <p class="cx-er">Your order is below the minimum order amount.</p>
                @endunless

                @if ($notice)
                    <p class="cx-note" role="alert">{{ $notice }}</p>
                @endif
                @error('form') <p class="cx-er" role="alert">{{ $message }}</p> @enderror

                <button class="cx-btn" type="submit" wire:loading.attr="disabled" wire:target="place" @disabled(! $quote->meetsMinimum)>
                    {{ $notice ? 'Confirm and save order' : 'Save order and continue' }}
                </button>
                <p class="cx-muted">Pay cash or card to the rider. No advance payment needed.</p>
            </div>
        </form>
    @endif
</div>