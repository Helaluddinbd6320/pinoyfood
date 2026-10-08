<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Customer;
use App\Services\CartService;
use App\Services\SuggestionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Suggestions extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        // Listening is enough: render() re-reads the cart from the session.
    }

    public function add(int $productId, CartService $cart): void
    {
        $status = $cart->add($productId);

        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: match ($status) {
            'added' => 'Added to your cart',
            'max' => 'That is all we have in stock',
            'sold_out' => 'Sorry, this item is sold out',
            default => 'This item is not available',
        });
    }

    public function render(CartService $cart, SuggestionService $suggestions)
    {
        $items = $cart->items();
        $customer = Auth::guard('customer')->user();

        $pairs = $suggestions->pairs($items);
        $picked = $suggestions->picked(
            $items,
            $customer instanceof Customer ? $customer : null,
            $pairs->pluck('id')->all(),
        );

        return view('livewire.suggestions', ['pairs' => $pairs, 'picked' => $picked]);
    }
}