@use('App\Support\Sar')
<div>
    <div class="sh">
        <h2>What are you craving?</h2>
        <div class="chips" role="group" aria-label="Filter menu">
            <button type="button" class="chip" aria-pressed="{{ $active === 'all' ? 'true' : 'false' }}" wire:click="setFilter('all')">All</button>
            @if ($hasOffers)
                <button type="button" class="chip" aria-pressed="{{ $active === 'offers' ? 'true' : 'false' }}" wire:click="setFilter('offers')">Offers</button>
            @endif
            @foreach ($categories as $category)
                <button type="button" class="chip" wire:key="chip-{{ $category->id }}" aria-pressed="{{ $active === $category->slug ? 'true' : 'false' }}" wire:click="setFilter('{{ $category->slug }}')">{{ $category->name }}</button>
            @endforeach
        </div>
    </div>

    <div class="grid">
        @forelse ($cards as $card)
            <article class="card" wire:key="product-{{ $card['id'] }}">
                <div class="ph" style="background: {{ $card['bg'] }}">
                    @if ($card['on_offer'])
                        <span class="off">-{{ $card['discount'] }}%</span>
                    @endif
                    @if ($card['image'])
                        <img src="{{ $card['image'] }}" alt="{{ $card['name'] }}" loading="lazy">
                    @else
                        <span aria-hidden="true">{{ $card['emoji'] }}</span>
                    @endif
                </div>
                <div class="cb">
                    <h3>{{ $card['name'] }}</h3>
                    @if ($card['description'])
                        <small>{{ $card['description'] }}</small>
                    @endif
                    @if ($card['until'])
                        <small class="until">Offer until {{ $card['until'] }}</small>
                    @endif
                    <div class="row">
                        <span class="price">
                            @if ($card['on_offer'])
                                <s class="was">{{ Sar::format($card['price']) }}</s>
                            @endif
                            {{ Sar::format($card['effective']) }}
                        </span>
                        @if ($card['sold_out'])
                            <button type="button" class="add" disabled>Sold out</button>
                        @else
                            <button type="button" class="add" wire:click="add({{ $card['id'] }})" wire:loading.attr="disabled" wire:target="add({{ $card['id'] }})">Add</button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="empty">Nothing here yet. Check back soon.</p>
        @endforelse
    </div>
</div>