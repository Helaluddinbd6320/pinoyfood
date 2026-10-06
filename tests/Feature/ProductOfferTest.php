<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductOfferTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): Carbon
    {
        return Carbon::parse($time, 'Asia/Riyadh');
    }

    private function product(array $attrs = []): Product
    {
        return (new Product)->forceFill(array_merge([
            'price' => '26.00',
            'cost' => '12.00',
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ], $attrs));
    }

    #[Test]
    public function it_uses_the_normal_price_when_there_is_no_offer(): void
    {
        $p = $this->product();
        $t = $this->at('2026-10-09 12:00:00');

        $this->assertSame('26.00', $p->effectivePriceAt($t));
        $this->assertFalse($p->isOnOfferAt($t));
        $this->assertSame(0, $p->discountPercentAt($t));
    }

    #[Test]
    public function it_applies_the_offer_inside_the_window_and_exposes_the_accessors(): void
    {
        $p = $this->product([
            'sale_price' => '21.00',
            'sale_starts_at' => $this->at('2026-10-08 12:00:00'),
            'sale_ends_at' => $this->at('2026-10-10 12:00:00'),
        ]);

        $this->travelTo($this->at('2026-10-09 09:00:00'));

        $this->assertSame('21.00', $p->effective_price);
        $this->assertTrue($p->is_on_offer);
        $this->assertSame(19, $p->discount_percent);
    }

    #[Test]
    public function it_is_inclusive_at_exactly_the_start_and_end_second(): void
    {
        $p = $this->product([
            'sale_price' => '21.00',
            'sale_starts_at' => $this->at('2026-10-08 12:00:00'),
            'sale_ends_at' => $this->at('2026-10-10 12:00:00'),
        ]);

        $this->assertSame('26.00', $p->effectivePriceAt($this->at('2026-10-08 11:59:59')));
        $this->assertSame('21.00', $p->effectivePriceAt($this->at('2026-10-08 12:00:00')));
        $this->assertSame('21.00', $p->effectivePriceAt($this->at('2026-10-10 12:00:00')));
        $this->assertSame('26.00', $p->effectivePriceAt($this->at('2026-10-10 12:00:01')));
    }

    #[Test]
    public function it_treats_a_null_start_as_from_now_and_a_null_end_as_until_removed(): void
    {
        $noStart = $this->product(['sale_price' => '21.00', 'sale_ends_at' => $this->at('2026-10-10 12:00:00')]);
        $noEnd = $this->product(['sale_price' => '21.00', 'sale_starts_at' => $this->at('2026-10-08 12:00:00')]);
        $open = $this->product(['sale_price' => '21.00']);

        $this->assertSame('21.00', $noStart->effectivePriceAt($this->at('2020-01-01 00:00:00')));
        $this->assertSame('26.00', $noStart->effectivePriceAt($this->at('2026-10-11 00:00:00')));
        $this->assertSame('21.00', $noEnd->effectivePriceAt($this->at('2030-01-01 00:00:00')));
        $this->assertSame('26.00', $noEnd->effectivePriceAt($this->at('2026-10-07 00:00:00')));
        $this->assertSame('21.00', $open->effectivePriceAt($this->at('2026-10-09 00:00:00')));
    }

    #[Test]
    public function it_compares_instants_so_the_timezone_of_the_clock_does_not_matter(): void
    {
        $p = $this->product([
            'sale_price' => '21.00',
            'sale_starts_at' => $this->at('2026-10-08 12:00:00'),
            'sale_ends_at' => $this->at('2026-10-10 12:00:00'),
        ]);

        // 2026-10-10 09:00 UTC is 12:00 in Riyadh, the last valid second
        $this->assertSame('21.00', $p->effectivePriceAt(Carbon::parse('2026-10-10 09:00:00', 'UTC')));
        $this->assertSame('26.00', $p->effectivePriceAt(Carbon::parse('2026-10-10 09:00:01', 'UTC')));
    }

    #[Test]
    public function it_ignores_invalid_offers(): void
    {
        $t = $this->at('2026-10-09 12:00:00');

        $this->assertSame('26.00', $this->product(['sale_price' => '26.00'])->effectivePriceAt($t));
        $this->assertSame('26.00', $this->product(['sale_price' => '30.00'])->effectivePriceAt($t));
        $this->assertSame('26.00', $this->product(['sale_price' => '0.00'])->effectivePriceAt($t));
    }

    #[Test]
    public function it_finds_products_that_are_on_offer_now_with_the_scope(): void
    {
        $cat = Category::factory()->create();
        $t = $this->at('2026-10-09 12:00:00');

        $live = Product::factory()->for($cat)->create([
            'price' => '26.00', 'sale_price' => '21.00',
            'sale_starts_at' => $this->at('2026-10-08 00:00:00'), 'sale_ends_at' => $this->at('2026-10-10 00:00:00'),
        ]);
        $expired = Product::factory()->for($cat)->create([
            'price' => '26.00', 'sale_price' => '21.00',
            'sale_starts_at' => $this->at('2026-10-01 00:00:00'), 'sale_ends_at' => $this->at('2026-10-02 00:00:00'),
        ]);
        $none = Product::factory()->for($cat)->create([
            'price' => '26.00', 'sale_price' => null,
            'sale_starts_at' => null, 'sale_ends_at' => null,
        ]);

        $ids = Product::onOfferNow($t)->pluck('id')->all();

        $this->assertContains($live->id, $ids);
        $this->assertNotContains($expired->id, $ids);
        $this->assertNotContains($none->id, $ids);
    }
}