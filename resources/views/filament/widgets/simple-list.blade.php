<x-filament-widgets::widget>
    <x-filament::section :heading="$heading">
        @forelse ($rows as $row)
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.4rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                <span>{{ $row['label'] }}</span>
                <x-filament::badge :color="$row['color'] ?? 'gray'">{{ $row['value'] }}</x-filament::badge>
            </div>
        @empty
            <p style="opacity:.7">{{ $empty }}</p>
        @endforelse

        @isset($note)
            <p style="font-size:.85rem;opacity:.7;margin-top:.6rem">{{ $note }}</p>
        @endisset
    </x-filament::section>
</x-filament-widgets::widget>