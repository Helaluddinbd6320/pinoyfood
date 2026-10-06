<?php

namespace App\Services\Pricing;

/** One priced cart line. Values are what OrderService snapshots into order_items. */
final readonly class QuoteLine
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $qty,
        public string $listPrice,
        public string $unitPrice,
        public ?string $unitCost,
        public string $lineTotal,
        public bool $onOffer,
        public int $discountPercent,
    ) {}
}