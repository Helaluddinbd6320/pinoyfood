<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\NewOrder;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffPaidFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function product(string $price): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => $price, 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function fill(User $staff, Product $p, bool $feePaid)
    {
        $area = Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);

        return Livewire::actingAs($staff)->test(NewOrder::class)
            ->set('phone', '0501230142')
            ->set('name', 'Maria S.')
            ->set('areaId', (string) $area->id)
            ->call('step', $p->id, 1)
            ->set('feePaid', $feePaid)
            ->call('place')
            ->assertHasNoErrors();
    }

    #[Test]
    public function the_tick_creates_a_payment_already_paid_by_the_staff_member(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->fill($staff, $this->product('28.00'), true);

        $order = Order::query()->with('payments')->firstOrFail();
        $payment = $order->payments->firstOrFail();

        $this->assertSame('paid', $payment->status->value);
        $this->assertSame($staff->id, (int) $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertTrue((bool) ($payment->payload['entered_by_staff'] ?? false));
        $this->assertSame('10.00', (string) $order->paid_online_total);
        $this->assertSame('28.00', (string) $order->amount_due_on_delivery); // total 38.00 minus the 10.00 fee
    }

    #[Test]
    public function without_the_tick_no_payment_is_created(): void
    {
        $this->fill(User::factory()->create(['role' => 'staff', 'is_active' => true]), $this->product('28.00'), false);

        $this->assertSame(0, Order::query()->firstOrFail()->payments()->count());
    }

    #[Test]
    public function the_tick_is_ignored_when_delivery_is_free(): void
    {
        $this->fill(User::factory()->create(['role' => 'staff', 'is_active' => true]), $this->product('160.00'), true);

        $order = Order::query()->firstOrFail();
        $this->assertSame(0, $order->payments()->count());
        $this->assertSame('0.00', (string) $order->paid_online_total);
    }
}