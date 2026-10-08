<div class="ac-wrap" x-data="{ open: false }" @keydown.escape.window="open = false">
    @if (! $customer)
        <form method="GET" action="{{ route('auth.google') }}">
            <button class="ac-btn" type="submit" aria-label="Sign in with Google"><span class="ac-g">G</span><span>Sign in</span></button>
        </form>
    @else
        <button class="ac-btn" type="button" @click="open = true" aria-label="My account">
            <span class="ac-av">{{ $initial }}</span><span>{{ $first }}</span>
        </button>

        <div class="ac-ov" x-show="open" x-cloak @click="open = false"></div>
        <aside class="ac-panel" x-show="open" x-cloak role="dialog" aria-modal="true" aria-label="My account">
            <div class="ac-head">
                <span class="ac-big">{{ $initial }}</span>
                <div>
                    <b>{{ $customer->name ?: 'Your account' }}</b><br>
                    <small class="ac-muted">{{ $customer->email }}</small>
                </div>
                <button class="ac-x" type="button" @click="open = false" aria-label="Close">✕</button>
            </div>

            @if ($latest)
                <div class="ac-card">
                    <h4>Order {{ $latest->order_no }} · {{ $statusLabel }}</h4>
                    @if ($stepIndex !== null)
                        <div class="ac-trk">
                            @foreach ($stepLabels as $i => $label)
                                <i class="{{ $i <= $stepIndex ? 'd' : '' }}"></i>
                                @unless ($loop->last)<u class="{{ $i < $stepIndex ? 'd' : '' }}"></u>@endunless
                            @endforeach
                        </div>
                        <div class="ac-tl">@foreach ($stepLabels as $label)<span>{{ $label }}</span>@endforeach</div>
                    @endif
                    <p class="ac-muted">{{ \App\Services\CustomerAccountService::summary($latest) }}</p>
                </div>

                <div class="ac-card">
                    <h4>Order again</h4>
                    <div class="ac-fv">
                        @foreach ($recent as $o)
                            <button type="button" wire:click="reorder({{ $o->id }})">{{ $o->created_at->format('M j') }} · {{ $o->items->sum('qty') }} items</button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($favorites->isNotEmpty())
                <div class="ac-card">
                    <h4>My favorites</h4>
                    <div class="ac-fv">
                        @foreach ($favorites as $p)
                            <button type="button" wire:click="addFavorite({{ $p->id }})">{{ \Illuminate\Support\Str::before($p->name, ' (') }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="ac-card">
                <h4>My details</h4>
                <div class="ac-kv"><span>WhatsApp</span><span>{{ $masked ?? 'Not added yet' }}</span></div>
                <div class="ac-kv"><span>Delivery area</span><span>{{ $customer->area?->name ?? 'Not set' }}</span></div>
                <div class="ac-kv"><span>Orders so far</span><span>{{ (int) $customer->orders_count }}</span></div>
            </div>

            <div class="ac-card">
                <div class="ac-sw">
                    <span>WhatsApp offers<br><small class="ac-muted">One message a week. Reply STOP any time.</small></span>
                    <button type="button" wire:click="toggleOffers" aria-pressed="{{ $customer->whatsapp_opt_in ? 'true' : 'false' }}" aria-label="WhatsApp offers" @disabled(! $customer->phone_search && ! $customer->whatsapp_opt_in)></button>
                </div>
                @error('offers') <div class="ac-er">{{ $message }}</div> @enderror
                @unless ($customer->phone_search)
                    <p class="ac-muted">Add your WhatsApp number to turn offers on.</p>
                @endunless
            </div>

            <div class="ac-card">
                <h4>Invite a friend</h4>
                <p class="ac-muted">Share Pinoy Food KSA with a kababayan in Riyadh.</p>
                <a class="ac-wa" href="{{ $share }}" target="_blank" rel="noopener">Share on WhatsApp</a>
            </div>

            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button class="ac-so" type="submit">Sign out</button>
            </form>
        </aside>
    @endif
</div>