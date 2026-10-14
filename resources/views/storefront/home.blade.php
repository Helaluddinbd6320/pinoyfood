@use('App\Support\Sar')
@inject('settings', 'App\Services\SettingsService')
@php
    $freeOn = (bool) $settings->get('free_delivery_enabled');
    $freeMin = (string) $settings->get('free_delivery_min');
@endphp
<x-layouts.storefront>
    <div class="wrap">
        <header>
            <a class="logo" href="{{ route('home') }}"><i></i>Pinoy Food KSA</a>
            <nav>
                <a href="#menu">Menu</a>
                <a href="#how">How it works</a>
                <a href="#areas">Delivery areas</a>
                <livewire:account-drawer />
                <livewire:cart-drawer />
            </nav>
        </header>

        @if (session('auth_error'))
            <p class="ac-er" role="alert">{{ session('auth_error') }}</p>
        @endif

        <div class="hero">
            <div>
                <h1>Lutong bahay, delivered in Riyadh.</h1>
                <p>Adobo, longganisa, pandesal and the pantry brands you grew up with. Order online, pay on delivery,
                    and eat like you are home.</p>
                <div class="cta">
                    <a class="btn p" href="#menu">Order now</a>
                    <a class="btn s" href="#how">How it works</a>
                </div>
            </div>
            <div class="plate" aria-hidden="true">
                <div class="disc"><span>🍲</span></div>
                <div class="ring"></div>
                @if ($freeOn)
                    <div class="tag a">🚚 Free delivery over {{ Sar::format($freeMin) }}</div>
                @endif
                <div class="tag b">Pay on delivery</div>
            </div>
        </div>

        <livewire:welcome-strip />

        <x-offers-banner />

        <div class="strip">
            <span><b>Cash</b> or card on delivery</span>
            <span><b>No pork</b> on our menu</span>
            <span><b>Choose</b> a delivery time at checkout</span>
        </div>

        <section id="menu">
            <livewire:product-grid />
        </section>

        <section id="how">
            <div class="sh">
                <h2>Three steps to your table</h2>
            </div>
            <div class="steps">
                <div class="step">
                    <h3>1. Pick your food</h3>
                    <p>Choose from ready meals, bakery and pantry items.</p>
                </div>
                <div class="step">
                    <h3>2. Tell us where</h3>
                    <p>Choose your area at checkout. A location pin is optional but makes delivery faster.</p>
                </div>
                <div class="step">
                    <h3>3. Pay when it arrives</h3>
                    <p>Cash or card to the rider.</p>
                </div>
            </div>
        </section>

        <section id="areas">
            <div class="sh">
                <h2>We deliver to</h2>
            </div>
            <x-delivery-areas />
        </section>
    </div>

    <footer>
        <div class="wrap">
            <h2>Miss home? Order lutong bahay today.</h2>
            <a class="btn" href="#menu">Order now</a>
            <a href="{{ route('privacy') }}" style="color:inherit">Privacy</a>
        </div>
    </footer>
</x-layouts.storefront>
