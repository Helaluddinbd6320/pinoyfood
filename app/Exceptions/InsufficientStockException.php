<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $available,
    ) {
        parent::__construct("Not enough stock for {$productName} (available: {$available}).");
    }
}