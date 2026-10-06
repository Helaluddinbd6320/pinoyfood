<?php

namespace App\Services\Pricing;

/** Result of PricingService::quote(). Every money value is a "0.00" string in SAR. */
final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     * @param  list<int>  $unavailableProductIds  missing, inactive or deleted products
     */
    public function __construct(
        public array $lines,
        public array $unavailableProductIds,
        public string $subtotal,
        public string $offerSavings,
        public string $deliveryFee,
        public bool $freeDeliveryApplied,
        public ?string $amountToFreeDelivery,
        public string $discountTotal,
        public string $total,
        public ?string $prepayType,
        public string $prepayAmount,
        public bool $prepayRequired,
        public string $minOrderAmount,
        public bool $meetsMinimum,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Changes whenever anything the customer pays changes (an offer ended,
     * a fee changed). Checkout compares the fingerprint the customer saw
     * with a fresh one and asks for a second confirmation if they differ.
     */
    public function fingerprint(): string
    {
        return sha1(json_encode([
            array_map(fn (QuoteLine $l) => [$l->productId, $l->qty, $l->unitPrice], $this->lines),
            $this->deliveryFee,
            $this->total,
        ]));
    }
}