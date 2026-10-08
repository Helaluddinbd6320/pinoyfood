<div>
    @if ($customer)
        <section class="ac-welcome" aria-live="polite">
            <div class="ac-wc">
                <div>
                    <h2>{{ $orders->isNotEmpty() ? 'Welcome back' : 'Welcome' }}, {{ $first }}</h2>
                    @if ($orders->isNotEmpty())
                        <p class="ac-muted">Last order: {{ \App\Services\CustomerAccountService::summary($orders->first()) }}</p>
                    @endif
                    @if ($notice) <p class="ac-ok">{{ $notice }}</p> @endif
                </div>
                @if ($orders->isNotEmpty())
                    <button class="ac-pri" type="button" wire:click="reorder({{ $orders->first()->id }})">Order again</button>
                @endif
            </div>

            @if ($askPhone)
                <form class="ac-ph" wire:submit="savePhone" novalidate>
                    <label for="ac-phone">Your WhatsApp number</label>
                    <p class="ac-muted">We use it to find your earlier orders and send order updates. You can skip this.</p>
                    <input id="ac-phone" class="ac-in" wire:model="phone" inputmode="tel" autocomplete="tel" placeholder="05X XXX XXXX or +63 9XX XXX XXXX" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                    @error('phone') <div class="ac-er">{{ $message }}</div> @enderror
                    <div class="ac-row">
                        <button class="ac-pri" type="submit" wire:loading.attr="disabled" wire:target="savePhone">Save</button>
                        <button class="ac-link" type="button" wire:click="skipPhone">Skip</button>
                    </div>
                </form>
            @endif

            @if ($orders->count() > 1)
                <div class="ac-past">
                    @foreach ($orders as $o)
                        <div class="ac-po">
                            <b>{{ $o->created_at->format('M j') }}</b>
                            <small>{{ \App\Services\CustomerAccountService::summary($o) }}</small>
                            <button type="button" wire:click="reorder({{ $o->id }})">Reorder</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>