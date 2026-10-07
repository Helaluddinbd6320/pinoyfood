<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\NewOrder;
use App\Models\Area;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => '28.00',
            'sale_price' => null,
            'stock' => 10,
            'track_stock' => true,
            'is_active' => true,
        ], $attrs));
    }

    private function area(): Area
    {
        return Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
    }

    #[Test]
    public function staff_can_place_a_call_order_with_source_and_creator(): void
    {
        $staff = $this->user('staff');
        $product = $this->product();
        $area = $this->area();

        Livewire::actingAs($staff)->test(NewOrder::class)
            ->set('source', 'call')
            ->set('phone', '050 123 0142')
            ->set('name', 'Maria S.')
            ->set('areaId', (string) $area->id)
            ->call('step', $product->id, 1)
            ->call('step', $product->id, 1)
            ->call('place')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame('call', $order->source->value ?? $order->source);
        $this->assertSame($staff->id, (int) $order->created_by);
        $this->assertSame('56.00', (string) $order->subtotal);
        $this->assertSame(8, (int) $product->fresh()->stock);
    }

    #[Test]
    public function stepper_cannot_exceed_stock(): void
    {
        $product = $this->product(['stock' => 2]);

        Livewire::actingAs($this->user('admin'))->test(NewOrder::class)
            ->call('step', $product->id, 1)
            ->call('step', $product->id, 1)
            ->call('step', $product->id, 1)
            ->assertSet('qty', [$product->id => 2]);
    }

    #[Test]
    public function an_order_needs_at_least_one_item(): void
    {
        $area = $this->area();

        Livewire::actingAs($this->user('admin'))->test(NewOrder::class)
            ->set('phone', '0501230142')
            ->set('name', 'Jun R.')
            ->set('areaId', (string) $area->id)
            ->call('place')
            ->assertHasErrors(['qty']);

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function name_phone_and_area_are_required(): void
    {
        Livewire::actingAs($this->user('admin'))->test(NewOrder::class)
            ->call('place')
            ->assertHasErrors(['name', 'phone', 'areaId']);
    }

    #[Test]
    public function an_unusual_phone_format_never_blocks_the_order(): void
    {
        $product = $this->product();
        $area = $this->area();

        Livewire::actingAs($this->user('staff'))->test(NewOrder::class)
            ->set('phone', '12345678')
            ->set('name', 'Rey D.')
            ->set('areaId', (string) $area->id)
            ->call('step', $product->id, 1)
            ->call('place')
            ->assertHasNoErrors();

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function a_returning_customer_is_found_and_last_order_can_be_repeated(): void
    {
        $staff = $this->user('staff');
        $product = $this->product();
        $area = $this->area();

        Livewire::actingAs($staff)->test(NewOrder::class)
            ->set('phone', '0501230142')
            ->set('name', 'Maria S.')
            ->set('areaId', (string) $area->id)
            ->call('step', $product->id, 2)
            ->call('place');

        Livewire::actingAs($staff)->test(NewOrder::class)
            ->set('phone', '+966 50 123 0142')
            ->assertSet('name', 'Maria S.')
            ->assertSet('areaId', (string) $area->id)
            ->call('useLastOrder')
            ->assertSet('qty', [$product->id => 2]);
    }

    #[Test]
    public function a_rider_cannot_open_the_page(): void
    {
        $this->actingAs($this->user('rider'));

        $this->assertFalse(NewOrder::canAccess());
    }

    #[Test]
    public function admin_and_staff_can_open_the_page(): void
    {
        $this->actingAs($this->user('admin'));
        $this->assertTrue(NewOrder::canAccess());

        $this->actingAs($this->user('staff'));
        $this->assertTrue(NewOrder::canAccess());
    }
}