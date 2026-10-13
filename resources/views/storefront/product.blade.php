@php
    $look = \App\Support\ProductLook::for($product);
    $image = $look['image'] ?? null;
    $image = $image ? (str_starts_with($image, 'http') ? $image : url($image)) : null;
    $desc = \Illuminate\Support\Str::limit(
        trim(($product->description ?: $product->name).' · SAR '.$product->effective_price), 150, '');
@endphp
<x-layouts.storefront :title="$product->name.' – '.config('app.name')">
    <x-slot:head>
        <meta name="description" content="{{ $desc }}">
        <meta property="og:type" content="product">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $product->name }} – SAR {{ $product->effective_price }}">
        <meta property="og:description" content="{{ $desc }}">
        <meta property="og:url" content="{{ route('product.show', $product->slug) }}">
        @if ($image)
            <meta property="og:image" content="{{ $image }}">
        @endif
    </x-slot:head>

    <div class="wrap">
        <header>
            <a class="logo" href="{{ route('home') }}"><i></i>Pinoy Food KSA</a>
            <nav>
                <a href="{{ route('home') }}#menu">Menu</a>
                <livewire:cart-drawer />
            </nav>
        </header>

        <div class="pd-page">
            <livewire:product-detail :product-id="$product->id" />
        </div>
    </div>
</x-layouts.storefront>