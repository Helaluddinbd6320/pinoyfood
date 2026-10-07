<?php

namespace Tests\Unit;

use App\Support\Sar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SarTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'whole number' => ['28.00', 'SAR 28'],
            'half riyal' => ['28.50', 'SAR 28.50'],
            'one decimal' => ['26.1', 'SAR 26.10'],
            'integer' => [150, 'SAR 150'],
            'float' => [12.5, 'SAR 12.50'],
            'zero' => ['0.00', 'SAR 0'],
            'null' => [null, 'SAR 0'],
            'garbage' => ['abc', 'SAR 0'],
            'rounds extra digits down' => ['21.004', 'SAR 21'],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_formats_money(string|int|float|null $input, string $expected): void
    {
        $this->assertSame($expected, Sar::format($input));
    }
}