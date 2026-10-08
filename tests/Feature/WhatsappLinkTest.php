<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CartDrawer;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\WhatsappLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsappLinkTest extends TestCase
{
    use RefreshDatabase;

    private function links(): WhatsappLinkService
    {
        return app(WhatsappLinkService::class);
    }

    #[Test]
    public function a_chat_link_uses_digits_without_a_plus(): void
    {
        $this->assertSame('https://wa.me/966501230142', $this->links()->chat('+966501230142'));
        $this->assertSame('https://wa.me/966501230142?text=Hi%20there', $this->links()->chat('+966501230142', 'Hi there'));
    }

    #[Test]
    public function a_missing_number_gives_no_link(): void
    {
        $this->assertNull($this->links()->chat(null));
        $this->assertNull($this->links()->chat(''));
    }

    #[Test]
    public function the_business_number_accepts_any_format(): void
    {
        config(['services.whatsapp.business_number' => '050 123 0142']);
        $this->assertSame('966501230142', $this->links()->businessNumber());

        config(['services.whatsapp.business_number' => '']);
        $this->assertNull($this->links()->businessNumber());
        $this->assertNull($this->links()->toBusiness('Hello'));
    }

    #[Test]
    public function offers_always_end_with_the_stop_line_exactly_once(): void
    {
        $once = $this->links()->offerText('Kumusta! Fresh pandesal.');
        $this->assertStringEndsWith('Reply STOP to unsubscribe.', $once);

        $twice = $this->links()->offerText($once);
        $this->assertSame(1, substr_count(strtolower($twice), 'reply stop'));
    }

    #[Test]
    public function the_status_message_matches_the_order(): void
    {
        $order = new Order([
            'order_no' => 'PF-1049',
            'customer_name' => 'Maria Santos',
            'status' => 'confirmed',
            'amount_due_on_delivery' => '82.00',
            'slot_label' => 'Today, 4 to 6 PM',
        ]);

        $text = $this->links()->orderStatusText($order);

        $this->assertStringContainsString('Maria', $text);
        $this->assertStringContainsString('PF-1049', $text);
        $this->assertStringContainsString('SAR 82', $text);
        $this->assertStringContainsString('Today, 4 to 6 PM', $text);
    }

    #[Test]
    public function the_cart_text_shows_each_price_the_delivery_and_the_total(): void
    {
        $text = $this->links()->cartText(
            [
                ['name' => 'Chicken adobo (500g)', 'qty' => 2, 'unit' => '24.00', 'total' => '48.00'],
                ['name' => 'Leche flan', 'qty' => 1, 'unit' => '18.00', 'total' => '18.00'],
            ],
            '66.00',
            '10.00',
            '76.00',
        );

        $this->assertStringContainsString('2 x Chicken adobo (500g) (SAR 24 each) = SAR 48', $text);
        $this->assertStringContainsString('1 x Leche flan (SAR 18 each) = SAR 18', $text);
        $this->assertStringContainsString('Items total: SAR 66', $text);
        $this->assertStringContainsString('Delivery: SAR 10', $text);
        $this->assertStringContainsString('Total: SAR 76', $text);

        $free = $this->links()->cartText(
            [['name' => 'Leche flan', 'qty' => 9, 'unit' => '18.00', 'total' => '162.00']],
            '162.00',
        );
        $this->assertStringContainsString('Delivery: Free', $free);
    }

    // #[Test]
    // public function the_cart_drawer_shows_the_whatsapp_button_only_with_a_number_and_items(): void
    // {
    //     $product = Product::factory()->create([
    //         'category_id' => Category::factory()->create(['is_active' => true])->id,
    //         'price' => '28.00', 'sale_price' => null, 'stock' => 10, 'track_stock' => true, 'is_active' => true,
    //     ]);

    //     config(['services.whatsapp.business_number' => '+966501230142']);
    //     Livewire::test(CartDrawer::class)->assertDontSee('Order via WhatsApp');

    //     app(CartService::class)->add($product->id);

    //     Livewire::test(CartDrawer::class)
    //         ->assertSee('Order via WhatsApp')
    //         ->assertSee('https://wa.me/966501230142?text=', false);

    //     config(['services.whatsapp.business_number' => '']);
    //     Livewire::test(CartDrawer::class)->assertDontSee('Order via WhatsApp');
    // }
}