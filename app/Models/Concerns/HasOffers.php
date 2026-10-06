<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * The ONE place where a product's effective price is decided (spec 6.9).
 * Nothing else may compute prices.
 *
 * Window rule: active when start <= now <= end. A null start means "from now",
 * a null end means "until removed". An invalid offer (sale_price <= 0 or
 * >= price) is ignored and the normal price is used.
 */
trait HasOffers
{
    public function initializeHasOffers(): void
    {
        $this->mergeCasts([
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ]);
    }

    public function hasValidOffer(): bool
    {
        if ($this->sale_price === null || $this->price === null) {
            return false;
        }

        $sale = Money::toCents($this->sale_price);

        return $sale > 0 && $sale < Money::toCents($this->price);
    }

    public function isOnOfferAt(?CarbonInterface $at = null): bool
    {
        if (! $this->hasValidOffer()) {
            return false;
        }

        $at ??= now();

        if ($this->sale_starts_at !== null && $at->lt($this->sale_starts_at)) {
            return false;
        }

        if ($this->sale_ends_at !== null && $at->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
    }

    public function effectivePriceAt(?CarbonInterface $at = null): string
    {
        $value = $this->isOnOfferAt($at) ? $this->sale_price : $this->price;

        return Money::fromCents(Money::toCents($value));
    }

    public function discountPercentAt(?CarbonInterface $at = null): int
    {
        if (! $this->isOnOfferAt($at)) {
            return 0;
        }

        $list = Money::toCents($this->price);
        $sale = Money::toCents($this->sale_price);

        return (int) round((1 - $sale / $list) * 100);
    }

    protected function effectivePrice(): Attribute
    {
        return Attribute::get(fn () => $this->effectivePriceAt());
    }

    protected function isOnOffer(): Attribute
    {
        return Attribute::get(fn () => $this->isOnOfferAt());
    }

    protected function discountPercent(): Attribute
    {
        return Attribute::get(fn () => $this->discountPercentAt());
    }

    /** Admin filter "On offer now" and the storefront Offers chip. */
    public function scopeOnOfferNow(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone'));

        return $query
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->whereColumn('sale_price', '<', 'price')
            ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', $at));
    }
}