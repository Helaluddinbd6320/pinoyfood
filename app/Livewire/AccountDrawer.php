<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Customer;
use App\Services\CartService;
use App\Services\CustomerAccountService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class AccountDrawer extends Component
{
    private const STATUS = [
        'new' => 'Received',
        'confirmed' => 'Confirmed',
        'out_for_delivery' => 'On the way',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'no_answer' => 'No answer',
    ];

    private const STEP = ['new' => 0, 'confirmed' => 1, 'out_for_delivery' => 2, 'delivered' => 3];

    #[On('account-updated')]
    public function refresh(): void
    {
        // Listening is enough: render() re-reads the signed-in customer.
    }

    public function toggleOffers(CustomerAccountService $accounts, Request $request): void
    {
        $customer = $this->customer();

        if (! $customer) {
            return;
        }

        try {
            $accounts->setOffers($customer, ! $customer->whatsapp_opt_in, $request->ip(), $request->userAgent());
        } catch (DomainException $e) {
            $this->addError('offers', $e->getMessage());
        }
    }

    public function reorder(int $orderId, CustomerAccountService $accounts): void
    {
        $customer = $this->customer();

        if (! $customer) {
            return;
        }

        $r = $accounts->reorder($customer, $orderId);

        if ($r['added'] === 0 && $r['skipped'] === 0) {
            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: $r['added'] > 0
            ? "Added {$r['added']} item".($r['added'] === 1 ? '' : 's').' to your cart'.($r['skipped'] ? " ({$r['skipped']} not available)" : '')
            : 'Those items are not available right now');
    }

    public function addFavorite(int $productId, CartService $cart): void
    {
        if (! $this->customer()) {
            return;
        }

        $result = $cart->add($productId);
        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: $result === 'added' ? 'Added to your cart' : 'That is all we have in stock');
    }

    public function render(CustomerAccountService $accounts)
    {
        $customer = $this->customer();

        if (! $customer) {
            return view('livewire.account-drawer', ['customer' => null]);
        }

        $recent = $accounts->recentOrders($customer, 3);
        $latest = $recent->first();
        $status = $latest ? (string) ($latest->status->value ?? $latest->status) : null;
        $invite = route('home', ['ref' => $customer->referral_code]);

        return view('livewire.account-drawer', [
            'customer' => $customer,
            'first' => Str::before((string) ($customer->name ?: 'Account'), ' '),
            'initial' => mb_strtoupper(mb_substr((string) ($customer->name ?: 'A'), 0, 1)),
            'recent' => $recent,
            'latest' => $latest,
            'statusLabel' => $status ? (self::STATUS[$status] ?? $status) : null,
            'stepIndex' => $status !== null ? (self::STEP[$status] ?? null) : null,
            'stepLabels' => ['Received', 'Confirmed', 'On the way', 'Delivered'],
            'favorites' => $accounts->favorites($customer, 3),
            'masked' => $customer->phone_search ? '•••• '.substr((string) $customer->phone_search, -4) : null,
            'invite' => $invite,
            'share' => 'https://wa.me/?text='.rawurlencode('Try Pinoy Food KSA for Filipino food delivered in Riyadh! Order here: '.$invite),
        ]);
    }

    private function customer(): ?Customer
    {
        $c = Auth::guard('customer')->user();

        return $c instanceof Customer ? $c : null;
    }
}