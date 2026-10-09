@use('App\Support\Sar')
<x-filament-panels::page>
    <x-filament::section :heading="'Awaiting verification ('.$verify->count().')'">
        <p style="font-size:.9rem;opacity:.7;margin-bottom:.5rem">
            Only approved payments count. Until then the rider collects the full amount. Screenshots are private: staff only.
        </p>

        @forelse ($verify as $p)
            <div wire:key="verify-{{ $p->id }}" style="display:flex;gap:1rem;flex-wrap:wrap;padding:.8rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                @if ($p->proof_path)
                    <a href="{{ route('payments.proof', $p) }}" target="_blank" rel="noopener">
                        <img src="{{ route('payments.proof', $p) }}" alt="Payment screenshot" loading="lazy" style="max-width:220px;max-height:280px;border-radius:.5rem;border:1px solid rgba(128,128,128,.3)">
                    </a>
                @endif

                <div style="flex:1;min-width:240px">
                    <strong>{{ $p->order->order_no }} · {{ $p->order->customer_name }}</strong>
                    <div style="font-size:.9rem;opacity:.8">
                        {{ $p->type->label() }}: {{ Sar::format($p->amount) }} · order total {{ Sar::format($p->order->total) }}<br>
                        Method: {{ str_replace('_', ' ', $p->method) }}
                        @if ($p->customer_reference) · note: {{ $p->customer_reference }} @endif<br>
                        Uploaded {{ $p->proof_uploaded_at?->diffForHumans() }}
                    </div>

                    @if (! empty($p->payload['duplicate_of']))
                        <x-filament::badge color="danger" style="display:inline-flex;margin-top:.4rem">
                            Same screenshot as payment #{{ $p->payload['duplicate_of'] }}
                        </x-filament::badge>
                    @endif

                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem;align-items:center">
                        <x-filament::button size="sm" color="success" wire:click="approve({{ $p->id }})" wire:confirm="Approve this payment? The rider will collect less.">Approve</x-filament::button>

                        <input type="text" wire:model="reasons.{{ $p->id }}" maxlength="255" placeholder="Reason for the customer" aria-label="Reject reason" style="padding:.35rem .6rem;border:1px solid rgba(128,128,128,.4);border-radius:.4rem;min-width:200px">
                        <x-filament::button size="sm" color="danger" outlined wire:click="reject({{ $p->id }})">Reject</x-filament::button>
                    </div>
                </div>
            </div>
        @empty
            <p style="opacity:.7">Nothing to verify.</p>
        @endforelse
    </x-filament::section>

    <x-filament::section :heading="'Waiting for a screenshot ('.$waiting->count().')'">
        @forelse ($waiting as $row)
            @php($p = $row['payment'])
            <div wire:key="wait-{{ $p->id }}" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:.6rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                <div>
                    <strong>{{ $p->order->order_no }} · {{ $p->order->customer_name }}</strong>
                    <div style="font-size:.85rem;opacity:.7">
                        {{ $p->type->label() }}: {{ Sar::format($p->amount) }} · {{ $p->status->label() }}
                        @if ($p->reject_reason) · {{ $p->reject_reason }} @endif
                    </div>
                </div>
                @if ($row['link'])
                    <x-filament::button tag="a" size="sm" color="gray" :href="$row['link']" target="_blank" rel="noopener">Remind on WhatsApp</x-filament::button>
                @endif
            </div>
        @empty
            <p style="opacity:.7">No payments waiting.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>