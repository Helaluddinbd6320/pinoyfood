<x-filament-panels::page>
    <x-filament::section heading="Date range">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end">
            <div>
                <label for="rp-from" style="font-weight:600;display:block">From</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="rp-from" type="date" wire:model.live="from" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label for="rp-to" style="font-weight:600;display:block">To</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="rp-to" type="date" wire:model.live="to" />
                </x-filament::input.wrapper>
            </div>
            <p style="opacity:.7;font-size:.9rem">Showing {{ $rangeLabel }}. Cancelled orders are not counted as sales.</p>
        </div>
    </x-filament::section>

    <x-filament::section heading="Summary">
        @php
            $lines = [
                ['Orders (not cancelled)', $summary['orders']],
                ['Sales', 'SAR '.$summary['sales']],
                ['Average order', 'SAR '.$summary['average']],
                ['Delivery fees charged', 'SAR '.$summary['delivery_fees']],
                ['Offer discounts given', 'SAR '.$summary['offer_savings']],
                ['Gross margin (items with a cost)', 'SAR '.$summary['margin']],
                ['Delivered', $summary['delivered']],
                ['Cancelled', $summary['cancelled']],
                ['No answer (current status)', $summary['no_answer']],
            ];
        @endphp
        @foreach ($lines as [$label, $value])
            <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                <span>{{ $label }}</span><strong>{{ $value }}</strong>
            </div>
        @endforeach
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem;align-items:start">
        <x-filament::section heading="By source">
            @forelse ($sources as $r)
                <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                    <span>{{ $r->label }}</span><span>{{ $r->orders }} orders · SAR {{ $r->sales }}</span>
                </div>
            @empty
                <p style="opacity:.7">No orders in this range.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="By area">
            @forelse ($areas as $r)
                <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                    <span>{{ $r->area }}</span><span>{{ $r->orders }} orders · SAR {{ $r->sales }}</span>
                </div>
            @empty
                <p style="opacity:.7">No orders in this range.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Top products">
            @forelse ($products as $r)
                <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                    <span>{{ $r->name }}</span><span>{{ $r->qty }} sold · SAR {{ $r->revenue }}</span>
                </div>
            @empty
                <p style="opacity:.7">No sales in this range.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>