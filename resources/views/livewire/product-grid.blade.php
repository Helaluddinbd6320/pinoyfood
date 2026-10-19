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
                <div class="pimg">
                    <a class="ph" href="{{ route('product.show', $card['slug']) }}" style="background: {{ $card['bg'] }}" aria-label="{{ $card['name'] }} details">
                        @if ($card['on_offer'])
                            <span class="off">-{{ $card['discount'] }}%</span>
                        @endif
                        @if ($card['image'])
                            <img src="{{ $card['image'] }}" alt="{{ $card['name'] }}" loading="lazy">
                        @else
                            <span aria-hidden="true">{{ $card['emoji'] }}</span>
                        @endif
                    </a>

                    @if ($card['sold_out'])
                        <span class="soldtag">Sold out</span>
                    @else
                        <button type="button" class="glass-add" wire:click="add({{ $card['id'] }})" wire:loading.attr="disabled" wire:target="add({{ $card['id'] }})" aria-label="Add {{ $card['name'] }} to cart">
                            <span class="pd-hand sm" aria-hidden="true">👉</span> Add
                        </button>
                    @endif
                </div>

                <div class="cb">
                    <h3><a class="pl" href="{{ route('product.show', $card['slug']) }}">{{ $card['name'] }}</a></h3>
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
                    </div>
                </div>
            </article>
        @empty
            <p class="empty">Nothing here yet. Check back soon.</p>
        @endforelse
    </div>
</div>