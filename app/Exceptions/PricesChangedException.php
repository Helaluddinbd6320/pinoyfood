<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PricesChangedException extends RuntimeException
{
    public function __construct(public readonly string $newFingerprint)
    {
        parent::__construct('Prices changed since the customer last saw the total.');
    }
}