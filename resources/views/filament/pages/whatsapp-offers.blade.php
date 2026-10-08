<x-filament-panels::page>
    <x-filament::section heading="Offer message">
        <p style="font-size:.9rem;opacity:.7;margin-bottom:.5rem">
            Use {name} for the customer's first name. "Reply STOP to unsubscribe." is added automatically.
            Send about one offer a week. Offers go only to customers who opted in.
        </p>
        <x-filament::input.wrapper>
            <textarea wire:model.live.debounce.500ms="text" rows="4" maxlength="1000" style="width:100%;padding:.6rem" aria-label="Offer message"></textarea>
        </x-filament::input.wrapper>
        <p style="margin-top:.75rem;font-size:.85rem;opacity:.7;white-space:pre-line">{{ $preview }}</p>
    </x-filament::section>

    <x-filament::section :heading="'Opted-in customers ('.$rows->count().')'">
        @forelse ($rows as $row)
            @php($c = $row['customer'])
            <div wire:key="offer-{{ $c->id }}" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:.6rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                <div>
                    <strong>{{ $c->name ?: 'No name' }}</strong>
                    <div style="font-size:.85rem;opacity:.7">
                        {{ $c->phone_raw }}
                        @if ($row['last'])
                            · last offer {{ $row['last']->diffForHumans() }}
                        @else
                            · no offer sent yet
                        @endif
                    </div>
                    @if ($row['recent'])
                        <x-filament::badge color="warning" style="display:inline-flex">Sent this week</x-filament::badge>
                    @endif
                </div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    @if ($row['link'])
                        <x-filament::button tag="a" size="sm" :href="$row['link']" target="_blank" rel="noopener">Open chat</x-filament::button>
                    @endif
                    <x-filament::button size="sm" color="gray" wire:click="markSent({{ $c->id }})">Mark sent</x-filament::button>
                    <x-filament::button size="sm" color="danger" outlined wire:click="recordStop({{ $c->id }})" wire:confirm="Record STOP for this customer? They will not receive offers.">Record STOP</x-filament::button>
                </div>
            </div>
        @empty
            <p style="opacity:.7">No opted-in customers yet.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>