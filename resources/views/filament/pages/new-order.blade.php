<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;align-items:start">

        <x-filament::section heading="Customer">
            <div style="display:flex;gap:.5rem;margin-bottom:1rem">
                @foreach ($sources as $key => $label)
                    <x-filament::button
                        size="sm"
                        :color="$source === $key ? 'primary' : 'gray'"
                        wire:click="setSource('{{ $key }}')"
                    >{{ $label }}</x-filament::button>
                @endforeach
            </div>

            <div style="display:grid;gap:1rem">
                <div>
                    <label for="no-phone" style="font-weight:600">WhatsApp number</label>
                    <x-filament::input.wrapper :valid="! $errors->has('phone')">
                        <x-filament::input
                            id="no-phone"
                            type="tel"
                            inputmode="tel"
                            placeholder="Any format, e.g. 050 123 0142"
                            wire:model.live.debounce.500ms="phone"
                        />
                    </x-filament::input.wrapper>
                    @error('phone') <p style="color:#c0392b;font-size:.85rem">{{ $message }}</p> @enderror

                    @if ($customer)
                        <p style="margin-top:.5rem;font-size:.9rem">
                            <x-filament::badge color="success" style="display:inline-flex">Returning customer</x-filament::badge>
                            {{ $customer->name }}
                            @if ($customer->requires_prepay)
                                <x-filament::badge color="warning" style="display:inline-flex">Prepay required</x-filament::badge>
                            @endif
                            <x-filament::link tag="button" wire:click="useLastOrder">Same as last order</x-filament::link>
                        </p>
                    @elseif (strlen(preg_replace('/\D/', '', $phone)) >= 9)
                        <p style="margin-top:.5rem;font-size:.9rem;opacity:.7">New customer. Enter the name and area.</p>
                    @endif
                </div>

                <div>
                    <label for="no-name" style="font-weight:600">Name</label>
                    <x-filament::input.wrapper :valid="! $errors->has('name')">
                        <x-filament::input id="no-name" type="text" wire:model="name" autocomplete="off" />
                    </x-filament::input.wrapper>
                    @error('name') <p style="color:#c0392b;font-size:.85rem">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="no-area" style="font-weight:600">Area</label>
                    <x-filament::input.wrapper :valid="! $errors->has('areaId')">
                        <x-filament::input.select id="no-area" wire:model.live="areaId">
                            <option value="">Choose area</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('areaId') <p style="color:#c0392b;font-size:.85rem">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="no-pin" style="font-weight:600">Location pin (optional)</label>
                    <x-filament::input.wrapper :valid="! $errors->has('pinUrl')">
                        <x-filament::input id="no-pin" type="url" placeholder="Paste a Google Maps link" wire:model="pinUrl" />
                    </x-filament::input.wrapper>
                    @error('pinUrl') <p style="color:#c0392b;font-size:.85rem">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="no-notes" style="font-weight:600">Notes (optional)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="no-notes" type="text" placeholder="Building, floor, gate code" wire:model="notes" />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <div style="display:grid;gap:1.5rem">
            <x-filament::section heading="Items">
                @foreach ($products as $p)
                    @php
                        $q = $qty[$p->id] ?? 0;
                        $max = $p->track_stock ? max(0, (int) $p->stock) : 99;
                    @endphp
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.6rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                        <div>
                            {{ $p->name }}
                            @if ($p->is_on_offer)
                                <x-filament::badge color="success" style="display:inline-flex">Offer</x-filament::badge>
                            @endif
                            <div style="font-size:.85rem;opacity:.7">
                                SAR {{ $p->effective_price }}
                                @if ($p->track_stock) · {{ $max }} in stock @endif
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <x-filament::icon-button
                                icon="heroicon-m-minus"
                                size="sm"
                                color="gray"
                                label="Remove one {{ $p->name }}"
                                wire:click="step({{ $p->id }}, -1)"
                                :disabled="$q === 0"
                            />
                            <strong style="min-width:1.5rem;text-align:center">{{ $q }}</strong>
                            <x-filament::icon-button
                                icon="heroicon-m-plus"
                                size="sm"
                                color="gray"
                                label="Add one {{ $p->name }}"
                                wire:click="step({{ $p->id }}, 1)"
                                :disabled="$q >= $max"
                            />
                        </div>
                    </div>
                @endforeach
                @error('qty') <p style="color:#c0392b;font-size:.85rem;margin-top:.5rem">{{ $message }}</p> @enderror
            </x-filament::section>

            <x-filament::section heading="Order summary">
                @php
                    $feeApplies = $quote->prepayType === 'delivery_fee_prepay';
                    $riderCollects = ($feePaid && $feeApplies)
                        ? bcsub((string) $quote->total, (string) $quote->prepayAmount, 2)
                        : (string) $quote->total;
                @endphp

                <div style="display:flex;justify-content:space-between;padding:.2rem 0">
                    <span>Items</span><span>SAR {{ $quote->subtotal }}</span>
                </div>
                @if (bccomp((string) $quote->offerSavings, '0', 2) > 0)
                    <div style="display:flex;justify-content:space-between;padding:.2rem 0;opacity:.7">
                        <span>Offer savings included</span><span>SAR {{ $quote->offerSavings }}</span>
                    </div>
                @endif
                <div style="display:flex;justify-content:space-between;padding:.2rem 0">
                    <span>Delivery</span>
                    <span>{{ bccomp((string) $quote->deliveryFee, '0', 2) > 0 ? 'SAR '.$quote->deliveryFee : 'Free' }}</span>
                </div>
                @if ($quote->amountToFreeDelivery !== null)
                    <p style="font-size:.85rem;opacity:.7;margin:.25rem 0">
                        Add SAR {{ $quote->amountToFreeDelivery }} more for free delivery.
                    </p>
                @endif

                @if ($feeApplies)
                    <label style="display:flex;gap:.5rem;align-items:flex-start;margin:.6rem 0;font-size:.9rem">
                        <input type="checkbox" wire:model.live="feePaid" style="margin-top:.2rem">
                        <span>Delivery fee (SAR {{ $quote->prepayAmount }}) already paid in advance. Tick only after you saw the transfer arrive.</span>
                    </label>
                @endif

                <div style="display:flex;justify-content:space-between;padding:.5rem 0 0;margin-top:.4rem;border-top:1px solid rgba(128,128,128,.3);font-weight:700">
                    <span>Rider collects</span><span>SAR {{ $riderCollects }}</span>
                </div>
                @if (! $quote->meetsMinimum)
                    <p style="color:#c0392b;font-size:.85rem">Minimum order is SAR {{ $quote->minOrderAmount }}.</p>
                @endif

                <div style="margin-top:1rem">
                    <x-filament::button wire:click="place" wire:loading.attr="disabled" wire:target="place">
                        Add order
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>