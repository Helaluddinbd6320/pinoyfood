<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CartDrawer;
use App\Livewire\WhatsappOrder;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsappOrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
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
    public function it_saves_a_whatsapp_order_and_returns_a_link_with_the_order_number(): void
    {
        config(['services.whatsapp.business_number' => '+966501230142']);
        $product = $this->product();
        $area = $this->area();
        app(CartService::class)->add($product->id);
        app(CartService::class)->add($product->id);

        $component = Livewire::test(WhatsappOrder::class)
            ->set('name', 'Maria S.')
            ->set('phone', '050 123 0142')
            ->set('areaId', (string) $area->id)
            ->call('place')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame('whatsapp', $order->source->value);
        $this->assertNull($order->created_by);
        $this->assertSame('56.00', (string) $order->subtotal);
        $this->assertSame(8, (int) $product->fresh()->stock);
        $this->assertSame([], app(CartService::class)->items());

        $component->assertSet('orderNo', $order->order_no)
            ->assertSee($order->order_no)
            ->assertSee('Send on WhatsApp');

        $url = $component->get('waUrl');
        $this->assertStringStartsWith('https://wa.me/966501230142?text=', $url);
        $this->assertStringContainsString(rawurlencode($order->order_no), $url);
    }

    #[Test]
    public function the_message_lists_prices_totals_and_the_customer(): void
    {
        config(['services.whatsapp.business_number' => '+966501230142']);
        $product = $this->product(['name' => 'Leche flan', 'price' => '18.00']);
        $area = $this->area();
        app(CartService::class)->add($product->id);

        Livewire::test(WhatsappOrder::class)
            ->set('name', 'Jun R.')
            ->set('phone', '0559998888')
            ->set('areaId', (string) $area->id)
            ->call('place');

        $order = Order::query()->with('items', 'area')->firstOrFail();
        $text = app(\App\Services\WhatsappLinkService::class)->placedOrderText($order);

        $this->assertStringContainsString($order->order_no, $text);
        $this->assertStringContainsString('1 x Leche flan (SAR 18 each) = SAR 18', $text);
        $this->assertStringContainsString('Name: Jun R.', $text);
        $this->assertStringContainsString('Area: ' . $area->name, $text);
    }

    #[Test]
    public function name_phone_and_area_are_required(): void
    {
        app(CartService::class)->add($this->product()->id);

        Livewire::test(WhatsappOrder::class)
            ->call('place')
            ->assertHasErrors(['name', 'phone', 'areaId']);

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function an_empty_cart_creates_nothing(): void
    {
        $area = $this->area();

        Livewire::test(WhatsappOrder::class)
            ->set('name', 'Rey D.')
            ->set('phone', '0501230142')
            ->set('areaId', (string) $area->id)
            ->call('place');

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function the_honeypot_blocks_bots_silently(): void
    {
        $area = $this->area();
        app(CartService::class)->add($this->product()->id);

        Livewire::test(WhatsappOrder::class)
            ->set('name', 'Bot')
            ->set('phone', '0501230142')
            ->set('areaId', (string) $area->id)
            ->set('website', 'spam.example')
            ->call('place')
            ->assertRedirect(route('home'));

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function pressing_place_twice_does_not_create_two_orders(): void
    {
        $area = $this->area();
        $product = $this->product();
        app(CartService::class)->add($product->id);

        Livewire::test(WhatsappOrder::class)
            ->set('name', 'Maria S.')
            ->set('phone', '0501230142')
            ->set('areaId', (string) $area->id)
            ->call('place')
            ->call('place');

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function the_locked_order_number_cannot_be_changed_from_the_browser(): void
    {
        $component = Livewire::test(WhatsappOrder::class);

        try {
            $component->set('orderNo', 'PF-1001');
            $this->fail('A locked property must refuse to be set from the browser.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable) {
            // Any exception means the property is locked.
        }

        $component->assertSet('orderNo', null);
    }

    #[Test]
    public function the_cart_button_opens_the_form_only_with_a_number_and_items(): void
    {
        $product = $this->product();

        config(['services.whatsapp.business_number' => '+966501230142']);
        Livewire::test(CartDrawer::class)->assertDontSee('Order via WhatsApp');

        app(CartService::class)->add($product->id);

        Livewire::test(CartDrawer::class)
            ->assertSee('Order via WhatsApp')
            ->assertSee(route('whatsapp.order'), false);

        config(['services.whatsapp.business_number' => '']);
        Livewire::test(CartDrawer::class)->assertDontSee('Order via WhatsApp');
    }
}
