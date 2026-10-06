<?php

namespace Tests\Unit;

use App\Filament\Resources\Products\Tables\ProductsTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductOfferPriceTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'quarter off' => ['28.00', 25, '21.00'],
            'rounds half up' => ['18.00', 33, '12.06'],
            'one decimal result' => ['26.00', 15, '22.10'],
            'tiny price not below original' => ['0.50', 1, null],
            'zero percent' => ['28.00', 0, null],
            'over 90 percent' => ['28.00', 91, null],
            'not a number' => ['abc', 10, null],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_computes_the_offer_price(string $price, int $percent, ?string $expected): void
    {
        $this->assertSame($expected, ProductsTable::offerPrice($price, $percent));
    }
}