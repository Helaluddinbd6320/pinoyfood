@use('App\Support\Sar')
@use('App\Support\ProductLook')
<div>
    @if (! $product)
        <div class="cx-card"><p>This item is not available right now.</p></div>
    @else
        @php($look = ProductLook::for($product))
        <article class="pd">
            <div class="pd-img" style="background: {{ $look['bg'] }}">
                @if ($product->is_on_offer)
                    <span class="off">-{{ $product->discount_percent }}%</span>
                @endif
                @if ($look['image'])
                    <img src="{{ $look['image'] }}" alt="{{ $product->name }}">
                @else
                    <span aria-hidden="true" class="pd-emoji">{{ $look['emoji'] }}</span>
                @endif
            </div>

            <div class="pd-body">
                <small class="pd-cat">{{ $product->category->name }}</small>
                <h1 class="pd-h">{{ $product->name }}</h1>
                @if ($product->name_tl)
                    <p class="pd-tl">{{ $product->name_tl }}</p>
                @endif

                <p class="pd-price">
                    @if ($product->is_on_offer)
                        <s class="was">{{ Sar::format($product->price) }}</s>
                    @endif
                    {{ Sar::format($product->effective_price) }}
                </p>
                @if ($until)
                    <p class="pd-until">Offer until {{ $until }}</p>
                @endif

                @if ($product->description)
                    <p>{{ $product->description }}</p>
                @endif

                @if (filled($product->ingredients))
                    <h2 class="pd-h2">Ingredients</h2>
                    <p>{{ $product->ingredients }}</p>
                @endif

                @if ($allergens !== [])
                    <h2 class="pd-h2">Allergens</h2>
                    <p>{{ implode(', ', $allergens) }}</p>
                @endif

                @if ($storage || $product->shelf_life_days)
                    <h2 class="pd-h2">Storage</h2>
                    <p>
                        {{ $storage }}@if ($storage && $product->shelf_life_days) · @endif
                        @if ($product->shelf_life_days) Keeps about {{ $product->shelf_life_days }} days @endif
                    </p>
                @endif

                <div class="pd-act">
                    @if ($soldOut)
                        <button type="button" class="cx-btn" disabled>Sold out</button>
                    @else
                        <button type="button" class="cx-btn" wire:click="add" wire:loading.attr="disabled" wire:target="add">Add to cart</button>
                    @endif
                    <a class="cx-btn wa" href="{{ $share }}" target="_blank" rel="noopener">Share on WhatsApp</a>
                    <a class="pd-back" href="{{ route('home') }}#menu">← Back to menu</a>
                </div>
            </div>
        </article>
    @endif
</div>