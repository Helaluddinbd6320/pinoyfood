<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_spec_defaults_when_nothing_is_stored(): void
    {
        $s = new SettingsService;

        $this->assertTrue($s->freeDeliveryEnabled());
        $this->assertSame('150.00', $s->freeDeliveryMin());
        $this->assertSame('10.00', $s->deliveryFee());
        $this->assertTrue($s->allowPrepayDeliveryFee());
        $this->assertFalse($s->requireDepositForFreeDelivery());
        $this->assertSame('10.00', $s->depositAmount());
        $this->assertSame('0.00', $s->minOrderAmount());
        $this->assertSame('', $s->prepayInstructions());
    }

    #[Test]
    public function it_reads_values_stored_as_numbers_strings_or_booleans(): void
    {
        $now = now()->format('Y-m-d H:i:s');
        DB::table('settings')->insert([
            ['key' => 'free_delivery_min', 'value' => json_encode(200), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'delivery_fee', 'value' => json_encode('12.5'), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'free_delivery_enabled', 'value' => json_encode(false), 'created_at' => $now, 'updated_at' => $now],
        ]);

        $s = new SettingsService;

        $this->assertSame('200.00', $s->freeDeliveryMin());
        $this->assertSame('12.50', $s->deliveryFee());
        $this->assertFalse($s->freeDeliveryEnabled());
    }

    #[Test]
    public function it_saves_a_value_clears_the_cache_and_keeps_one_row_per_key(): void
    {
        $s = new SettingsService;
        $s->freeDeliveryMin(); // warm the cache

        $s->set('free_delivery_min', 200);
        $s->set('free_delivery_min', '220');

        $this->assertSame('220.00', (new SettingsService)->freeDeliveryMin());
        $this->assertSame(1, DB::table('settings')->where('key', 'free_delivery_min')->count());
    }

    #[Test]
    public function it_rejects_unknown_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SettingsService)->set('free_deliveri_min', 100);
    }

    #[Test]
    public function it_rejects_negative_money(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SettingsService)->set('delivery_fee', -1);
    }

    #[Test]
    public function it_saves_nothing_when_one_value_in_a_batch_is_invalid(): void
    {
        $s = new SettingsService;

        try {
            $s->setMany(['delivery_fee' => 15, 'deposit_amount' => 'abc']);
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(0, DB::table('settings')->count());
        $this->assertSame('10.00', $s->deliveryFee());
    }

    #[Test]
    public function it_casts_boolean_input(): void
    {
        $s = new SettingsService;
        $s->set('free_delivery_enabled', 'false');

        $this->assertFalse($s->freeDeliveryEnabled());
    }
}