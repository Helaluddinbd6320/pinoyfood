<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderPdfTest extends TestCase
{
    use RefreshDatabase;

        private function order(array $over = []): Order
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);

        return app(\App\Services\OrderService::class)->place(new PlaceOrderData(
            cart: [$product->id => 2], name: 'Maria Santos', phone: '0501230142',
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
            notes: 'Gate 4',
        ));
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    #[Test]
    public function the_customer_gets_a_pdf_through_a_signed_link_only(): void
    {
        $order = $this->order();

        $this->get(route('order.receipt', $order->order_no))->assertForbidden();

        $url = URL::temporarySignedRoute('order.receipt', now()->addDay(), ['orderNo' => $order->order_no]);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function staff_get_both_pdfs_and_guests_get_neither(): void
    {
        $order = $this->order();

        $this->get(route('staff.orders.receipt', $order))->assertForbidden();
        $this->get(route('staff.orders.slip', $order))->assertForbidden();

        $staff = $this->user('staff');
        $this->actingAs($staff)->get(route('staff.orders.receipt', $order))->assertOk();
        $this->actingAs($staff)->get(route('staff.orders.slip', $order))->assertOk();
    }

    #[Test]
    public function a_rider_gets_the_slip_only_for_their_own_order_and_never_the_receipt(): void
    {
        $order = $this->order();
        $mine = $this->user('rider');
        $other = $this->user('rider');
        $order->forceFill(['rider_id' => $mine->id])->save();

        $this->actingAs($mine)->get(route('staff.orders.slip', $order))->assertOk();
        $this->actingAs($mine)->get(route('staff.orders.receipt', $order))->assertForbidden();
        $this->actingAs($other)->get(route('staff.orders.slip', $order))->assertForbidden();
    }

    #[Test]
    public function the_receipt_shows_prices_the_amount_due_and_that_it_is_not_a_tax_invoice(): void
    {
        config(['services.contact.email' => 'hello@example.test', 'services.contact.phone' => '+966 50 000 0000']);
        $order = $this->order();

        $html = view('pdf.receipt', app(OrderPdfService::class)->data($order))->render();

        $this->assertStringContainsString($order->order_no, $html);
        $this->assertStringContainsString('Order receipt', $html);
        $this->assertStringContainsString('not a tax invoice', $html);
        $this->assertStringContainsString('hello@example.test', $html);
        $this->assertStringContainsString('+966 50 000 0000', $html);
        $this->assertStringContainsString('SAR 56', $html);   // 2 x 28
        $this->assertStringContainsString('SAR 66', $html);   // plus the SAR 10 fee
    }

    #[Test]
    public function the_slip_shows_the_phone_notes_and_what_to_collect_but_no_item_prices(): void
    {
        $order = $this->order();
        $order->forceFill(['internal_notes' => 'secret staff note'])->save();

        $html = view('pdf.slip', app(OrderPdfService::class)->data($order->fresh()))->render();

        $this->assertStringContainsString('Collect from customer', $html);
        $this->assertStringContainsString('SAR 66', $html);
        $this->assertStringContainsString('0501230142', $html);
        $this->assertStringContainsString('Gate 4', $html);
        $this->assertStringNotContainsString('secret staff note', $html);
        $this->assertStringNotContainsString('SAR 28', $html); // no unit prices for the rider
    }

    #[Test]
    public function an_unverified_prepayment_tells_the_rider_to_collect_everything(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);
        $order = app(\App\Services\OrderService::class)->place(new PlaceOrderData(
            cart: [$product->id => 1], name: 'Jun R.', phone: '0559998888',
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
            prepayDeliveryFee: true,
        ));

        $html = view('pdf.slip', app(OrderPdfService::class)->data($order))->render();

        $this->assertStringContainsString('not verified yet', $html);
    }

    #[Test]
    public function a_deleted_customer_shows_the_removed_name(): void
    {
        $order = $this->order();
        $order->forceFill(['customer_name' => 'Deleted customer', 'phone_raw' => ''])->save();

        $html = view('pdf.receipt', app(OrderPdfService::class)->data($order->fresh()))->render();

        $this->assertStringContainsString('Deleted customer', $html);
        $this->assertStringNotContainsString('Maria', $html);
    }
}