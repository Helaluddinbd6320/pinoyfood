<?php

namespace App\View\Components;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class OffersBanner extends Component
{
    public Collection $products;

    public function __construct()
    {
        $this->products = Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->onOfferNow()
            ->orderBy('sort_order')
            ->get();
    }

    /** The banner exists only while at least one product is on offer (spec 6.9). */
    public function shouldRender(): bool
    {
        return $this->products->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.offers-banner');
    }
}