<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Input for OrderService::place(). Contains no prices on purpose:
 * everything money-related is recomputed on the server.
 */
final readonly class PlaceOrderData
{
    /**
     * @param array<int,int> $cart productId => qty
     */
    public function __construct(
        public array $cart,
        public string $name,
        public string $phone,
        public int $areaId,
        public ?string $pinUrl = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $deliveryDate = null,
        public ?string $slotLabel = null,
        public ?string $notes = null,
        public bool $optIn = false,
        public string $optInSource = 'checkout',
        public string $source = 'website',
        public ?int $createdBy = null,
        public ?string $expectedFingerprint = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {
    }
}