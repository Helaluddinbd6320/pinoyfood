<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): Carbon
    {
        return Carbon::parse($time, 'Asia/Riyadh');
    }

    private function product(string $price, array $attrs = []): Product
    {
        return Product::factory()->for(Category::factory())->create(array_merge([
            'price' => $price,
            'cost' => '5.00',
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'is_active' => true,
        ], $attrs));
    }

    private function pricing(): PricingService
    {
        return app(PricingService::class);
    }

    private function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    #[Test]
    public function it_charges_the_delivery_fee_below_the_free_delivery_minimum(): void
    {
        $p = $this->product('28.00');

        $q = $this->pricing()->quote([$p->id => 2]);

        $this->assertSame('56.00', $q->subtotal);
        $this->assertSame('10.00', $q->deliveryFee);
        $this->assertFalse($q->freeDeliveryApplied);
        $this->assertSame('66.00', $q->total);
        $this->assertSame('94.00', $q->amountToFreeDelivery);
    }

    #[Test]
    public function it_makes_delivery_free_at_exactly_the_minimum_but_not_one_halala_below(): void
    {
        $exact = $this->product('150.00');
        $below = $this->product('149.99');

        $a = $this->pricing()->quote([$exact->id => 1]);
        $b = $this->pricing()->quote([$below->id => 1]);

        $this->assertSame('0.00', $a->deliveryFee);
        $this->assertTrue($a->freeDeliveryApplied);
        $this->assertSame('150.00', $a->total);
        $this->assertNull($a->amountToFreeDelivery);
        $this->assertSame('10.00', $b->deliveryFee);
        $this->assertSame('159.99', $b->total);
    }

    #[Test]
    public function it_always_charges_the_fee_when_free_delivery_is_switched_off(): void
    {
        $this->settings()->set('free_delivery_enabled', false);
        $p = $this->product('500.00');

        $q = $this->pricing()->quote([$p->id => 1]);

        $this->assertSame('10.00', $q->deliveryFee);
        $this->assertFalse($q->freeDeliveryApplied);
        $this->assertNull($q->amountToFreeDelivery);
    }

    #[Test]
    public function it_uses_the_area_fee_override_instead_of_the_global_fee(): void
    {
        $p = $this->product('50.00');
        $area = Area::factory()->create(['delivery_fee_override' => '15.00']);
        $freeArea = Area::factory()->create(['delivery_fee_override' => '0.00']);

        $a = $this->pricing()->quote([$p->id => 1], $area);
        $b = $this->pricing()->quote([$p->id => 1], $freeArea);

        $this->assertSame('15.00', $a->deliveryFee);
        $this->assertSame('65.00', $a->total);
        $this->assertSame('0.00', $b->deliveryFee);
        $this->assertFalse($b->freeDeliveryApplied);
        $this->assertNull($b->prepayType);
    }

    #[Test]
    public function it_judges_free_delivery_on_the_subtotal_after_offers(): void
    {
        $p = $this->product('80.00', [
            'sale_price' => '70.00',
            'sale_ends_at' => $this->at('2026-10-10 23:59:59'),
        ]);

        $during = $this->pricing()->quote([$p->id => 2], null, $this->at('2026-10-09 12:00:00'));
        $after = $this->pricing()->quote([$p->id => 2], null, $this->at('2026-10-11 12:00:00'));

        // 2 x 80 = 160 would be free, but 2 x 70 = 140 is not
        $this->assertSame('140.00', $during->subtotal);
        $this->assertSame('20.00', $during->offerSavings);
        $this->assertSame('10.00', $during->deliveryFee);
        $this->assertSame('150.00', $during->total);
        $this->assertSame('10.00', $during->amountToFreeDelivery);
        $this->assertSame('160.00', $after->subtotal);
        $this->assertSame('0.00', $after->deliveryFee);
        $this->assertSame('0.00', $after->offerSavings);
    }

    #[Test]
    public function it_switches_the_price_exactly_at_the_offer_start_and_end_second(): void
    {
        $p = $this->product('26.00', [
            'sale_price' => '21.00',
            'sale_starts_at' => $this->at('2026-10-08 12:00:00'),
            'sale_ends_at' => $this->at('2026-10-10 12:00:00'),
        ]);
        $unit = fn (string $t) => $this->pricing()->quote([$p->id => 1], null, $this->at($t))->lines[0]->unitPrice;

        $this->assertSame('26.00', $unit('2026-10-08 11:59:59'));
        $this->assertSame('21.00', $unit('2026-10-08 12:00:00'));
        $this->assertSame('21.00', $unit('2026-10-10 12:00:00'));
        $this->assertSame('26.00', $unit('2026-10-10 12:00:01'));
    }

    #[Test]
    public function it_changes_the_fingerprint_when_an_offer_ends_between_cart_and_checkout(): void
    {
        $p = $this->product('26.00', [
            'sale_price' => '21.00',
            'sale_ends_at' => $this->at('2026-10-10 12:00:00'),
        ]);

        $seen = $this->pricing()->quote([$p->id => 1], null, $this->at('2026-10-10 11:59:00'));
        $same = $this->pricing()->quote([$p->id => 1], null, $this->at('2026-10-10 11:59:30'));
        $later = $this->pricing()->quote([$p->id => 1], null, $this->at('2026-10-10 12:01:00'));

        $this->assertSame($seen->fingerprint(), $same->fingerprint());
        $this->assertNotSame($seen->fingerprint(), $later->fingerprint());
    }

    #[Test]
    public function it_snapshots_list_price_unit_price_cost_and_line_total_on_each_line(): void
    {
        $p = $this->product('26.00', ['sale_price' => '21.00', 'cost' => '12.00']);

        $line = $this->pricing()->quote([$p->id => 3])->lines[0];

        $this->assertSame('26.00', $line->listPrice);
        $this->assertSame('21.00', $line->unitPrice);
        $this->assertSame('12.00', $line->unitCost);
        $this->assertSame('63.00', $line->lineTotal);
        $this->assertTrue($line->onOffer);
        $this->assertSame(19, $line->discountPercent);
    }

    #[Test]
    public function it_skips_inactive_deleted_and_unknown_products_and_bad_quantities(): void
    {
        $ok = $this->product('20.00');
        $inactive = $this->product('20.00', ['is_active' => false]);
        $deleted = $this->product('20.00');
        $deleted->delete();

        $q = $this->pricing()->quote([
            $ok->id => 1, $inactive->id => 1, $deleted->id => 1, 99999 => 1, 5 => 0, 6 => -2,
        ]);

        $this->assertCount(1, $q->lines);
        $this->assertSame('20.00', $q->subtotal);
        $this->assertEqualsCanonicalizing([$inactive->id, $deleted->id, 99999], $q->unavailableProductIds);
    }

    #[Test]
    public function it_returns_an_empty_zero_quote_for_an_empty_cart(): void
    {
        $q = $this->pricing()->quote([]);

        $this->assertTrue($q->isEmpty());
        $this->assertSame('0.00', $q->deliveryFee);
        $this->assertSame('0.00', $q->total);
        $this->assertNull($q->prepayType);
    }

    #[Test]
    public function it_offers_an_optional_delivery_fee_prepay_when_delivery_is_charged(): void
    {
        $p = $this->product('28.00');

        $q = $this->pricing()->quote([$p->id => 1]);

        $this->assertSame('delivery_fee_prepay', $q->prepayType);
        $this->assertSame('10.00', $q->prepayAmount);
        $this->assertFalse($q->prepayRequired);
    }

    #[Test]
    public function it_makes_prepay_mandatory_for_flagged_customers(): void
    {
        $p = $this->product('28.00');

        $q = $this->pricing()->quote([$p->id => 1], null, null, customerRequiresPrepay: true);

        $this->assertTrue($q->prepayRequired);
    }

    #[Test]
    public function it_offers_no_prepay_when_the_admin_turns_advance_payment_off(): void
    {
        $this->settings()->set('allow_prepay_delivery_fee', false);
        $p = $this->product('28.00');

        $q = $this->pricing()->quote([$p->id => 1], null, null, customerRequiresPrepay: true);

        $this->assertNull($q->prepayType);
        $this->assertFalse($q->prepayRequired);
    }

    #[Test]
    public function it_asks_for_a_deposit_on_free_delivery_orders_only_when_that_setting_is_on(): void
    {
        $p = $this->product('160.00');

        $off = $this->pricing()->quote([$p->id => 1]);

        $this->settings()->set('require_deposit_for_free_delivery', true);
        $on = $this->pricing()->quote([$p->id => 1]);

        $this->assertNull($off->prepayType);
        $this->assertSame('deposit', $on->prepayType);
        $this->assertSame('10.00', $on->prepayAmount);
    }

    #[Test]
    public function it_reports_whether_the_minimum_order_amount_is_met(): void
    {
        $this->settings()->set('min_order_amount', 50);
        $p = $this->product('28.00');

        $low = $this->pricing()->quote([$p->id => 1]);
        $ok = $this->pricing()->quote([$p->id => 2]);

        $this->assertFalse($low->meetsMinimum);
        $this->assertSame('50.00', $low->minOrderAmount);
        $this->assertTrue($ok->meetsMinimum);
    }

    #[Test]
    public function it_uses_a_changed_delivery_fee_straight_away(): void
    {
        $p = $this->product('28.00');
        $this->assertSame('10.00', $this->pricing()->quote([$p->id => 1])->deliveryFee);

        $this->settings()->set('delivery_fee', 12);

        $this->assertSame('12.00', $this->pricing()->quote([$p->id => 1])->deliveryFee);
    }
}