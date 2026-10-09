<x-layouts.storefront :title="'Privacy – '.config('app.name')">
    <div class="cx-page">
        <a class="cx-back" href="{{ route('home') }}">← Back to menu</a>
        <h1 class="cx-h">Privacy</h1>

        <div class="cx-card">
            <h2 class="cx-h2">What we collect</h2>
            <p>When you order we keep your name, WhatsApp number, delivery area, notes, an optional location pin and your order details. If you sign in with Google we also receive your name, email address and profile picture. We never see your Google password.</p>

            <h2 class="cx-h2">Why</h2>
            <p>To confirm and deliver your order, to show your order history, and to contact you about your delivery. If you tick the offers box, or switch offers on in your account, we send about one WhatsApp message a week. Reply STOP any time and we stop.</p>

            <h2 class="cx-h2">Payment</h2>
            <p>You pay the rider in cash or by card on delivery. Paying the delivery fee in advance is optional. If you do, you send us a screenshot of the transfer. Screenshots are private: only our staff can see them, never the rider, and we delete them about 90 days after your order is delivered. We do not store card details.</p>

            <h2 class="cx-h2">Sharing and keeping your data</h2>
            <p>We do not sell your data. Only our team and the rider delivering your order can see your number and location. We keep order records as long as needed to run the service.</p>

            <h2 class="cx-h2">Your choices</h2>
            <p>You can ask us to show, correct or delete your personal data at any time. When we delete it, your name, number, email, location and screenshots are removed from your account and your past orders. Contact us using the details below.</p>

            <h2 class="cx-h2">Contact</h2>
            <p>WhatsApp us from the website, or email <a href="mailto:{{ config('services.contact.email') }}">{{ config('services.contact.email') }}</a>.</p>
        </div>
    </div>
</x-layouts.storefront>