@use('App\Support\Sar')
@use('App\Support\ProductLook')
<div>
    @if ($pairs->isNotEmpty() || $picked->isNotEmpty())
        <div class="sx">
            @if ($pairs->isNotEmpty())
                <h4>Pairs well with</h4>
                @foreach ($pairs as $p)
                    @php($look = ProductLook::for($p))
                    <div class="sx-i" wire:key="pair-{{ $p->id }}">
                        <span class="sx-e" style="background: {{ $look['bg'] }}">
                            @if ($look['image'])<img src="{{ $look['image'] }}" alt="">@else<span aria-hidden="true">{{ $look['emoji'] }}</span>@endif
                        </span>
                        <div><b>{{ \Illuminate\Support\Str::before($p->name, ' (') }}</b><small>{{ Sar::format($p->effective_price) }}</small></div>
                        <button type="button" class="sx-add" wire:click="add({{ $p->id }})" wire:loading.attr="disabled" wire:target="add({{ $p->id }})" aria-label="Add {{ $p->name }} to cart">+ Add</button>
                    </div>
                @endforeach
            @endif

            @if ($picked->isNotEmpty())
                <h4>Picked for you</h4>
                @foreach ($picked as $row)
                    @php($p = $row['product'])
                    @php($look = ProductLook::for($p))
                    <div class="sx-i" wire:key="pick-{{ $p->id }}">
                        <span class="sx-e" style="background: {{ $look['bg'] }}">
                            @if ($look['image'])<img src="{{ $look['image'] }}" alt="">@else<span aria-hidden="true">{{ $look['emoji'] }}</span>@endif
                        </span>
                        <div><b>{{ \Illuminate\Support\Str::before($p->name, ' (') }}</b><small>{{ $row['reason'] }} · {{ Sar::format($p->effective_price) }}</small></div>
                        <button type="button" class="sx-add" wire:click="add({{ $p->id }})" wire:loading.attr="disabled" wire:target="add({{ $p->id }})" aria-label="Add {{ $p->name }} to cart">+ Add</button>
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</div>