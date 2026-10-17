@use('App\Support\Sar')
@use('App\Support\ProductLook')
<div>
    @if (!$product)
        <div class="cx-card">
            <p>This item is not available right now.</p>
        </div>
    @else
        @php($look = ProductLook::for($product))
        <a class="pd-back" href="{{ route('home') }}#menu">← Back to menu</a>

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
                @if ($showTl)
                    <p class="pd-tl">{{ $product->name_tl }}</p>
                @endif

                <div class="pd-pr">
                    <span class="pd-price">
                        @if ($product->is_on_offer)
                            <s class="was">{{ Sar::format($product->price) }}</s>
                        @endif
                        {{ Sar::format($product->effective_price) }}
                    </span>
                    @if ($until)
                        <span class="pd-until">Offer until {{ $until }}</span>
                    @endif
                </div>

                @if ($storage || $product->shelf_life_days || $allergens !== [])
                    <div class="pd-chips">
                        @if ($storage)
                            <span class="pd-chip">{{ $storage }}</span>
                        @endif
                        @if ($product->shelf_life_days)
                            <span class="pd-chip">Keeps about {{ $product->shelf_life_days }} days</span>
                        @endif
                        @foreach ($allergens as $a)
                            <span class="pd-chip warn">Contains {{ $a }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($product->description)
                    <p class="pd-desc">{{ $product->description }}</p>
                @endif

                @if (filled($product->ingredients))
                    <div class="pd-box">
                        <h2 class="pd-h2">Ingredients</h2>
                        <p>{{ $product->ingredients }}</p>
                    </div>
                @endif

                <div class="pd-act">
                    @if ($soldOut)
                        <button type="button" class="cx-btn" disabled>Sold out</button>
                    @else
                        <button type="button" class="cx-btn pd-add" wire:click="add" wire:loading.attr="disabled"
                            wire:target="add">
                            <span class="pd-hand" aria-hidden="true">👉</span>
                            Add to cart
                        </button>
                    @endif
                    <a class="cx-btn wa" href="{{ $share }}" target="_blank" rel="noopener">Share on
                        WhatsApp</a>
                </div>
            </div>
        </article>

        @if ($related->isNotEmpty())
            <section class="pd-rel">
                <h2 class="pd-h2">Pairs well with</h2>
                <div class="pd-rg">
                    @foreach ($related as $r)
                        @php($rl = ProductLook::for($r))
                        <div class="pd-rc" wire:key="rel-{{ $r->id }}">
                            <a class="pd-ri" href="{{ route('product.show', $r->slug) }}"
                                style="background: {{ $rl['bg'] }}" aria-label="{{ $r->name }}">
                                @if ($rl['image'])
                                    <img src="{{ $rl['image'] }}" alt="{{ $r->name }}" loading="lazy">
                                @else
                                    <span aria-hidden="true">{{ $rl['emoji'] }}</span>
                                @endif
                            </a>
                            <div class="pd-rb">
                                <a class="pl" href="{{ route('product.show', $r->slug) }}">{{ $r->name }}</a>
                                <span class="pd-rp">
                                    @if ($r->is_on_offer)
                                        <s class="was">{{ Sar::format($r->price) }}</s>
                                    @endif
                                    {{ Sar::format($r->effective_price) }}
                                </span>
                                <button type="button" class="sx-add pd-radd"
                                    wire:click="addRelated({{ $r->id }})" wire:loading.attr="disabled"
                                    wire:target="addRelated({{ $r->id }})">
                                    <span class="pd-hand sm" aria-hidden="true">👉</span> + Add
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
