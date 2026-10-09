<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Models\Area;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CustomerAnonymiseService;
use App\Services\CustomerLookupService;
use App\Services\OrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerAnonymiseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Customer, 1: Order} */
    private function customerWithOrder(): array
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);

        $order = app(OrderService::class)->place(new PlaceOrderData(
            cart: [$product->id => 1], name: 'Maria S.', phone: '0501230142',
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
            pinUrl: 'https://maps.google.com/?q=24.7,46.7', notes: 'Gate 4',
        ));

        return [$order->customer, $order];
    }

    #[Test]
    public function it_refuses_while_the_customer_has_an_open_order(): void
    {
        [$customer] = $this->customerWithOrder();

        try {
            app(CustomerAnonymiseService::class)->anonymise($customer);
            $this->fail('An open order must block deletion.');
        } catch (DomainException) {
            $this->assertFalse(Customer::withTrashed()->find($customer->id)->trashed());
            $this->assertSame('Maria S.', Customer::withTrashed()->find($customer->id)->name);
        }
    }

    #[Test]
    public function it_scrubs_the_customer_and_the_order_snapshots_but_keeps_the_totals(): void
    {
        [$customer, $order] = $this->customerWithOrder();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'delivered']);
        DB::table('consent_logs')->insert([
            'customer_id' => $customer->id, 'action' => 'opt_in', 'source' => 'checkout',
            'ip_address' => '203.0.113.9', 'user_agent' => 'Test', 'created_at' => now(),
        ]);
        DB::table('whatsapp_messages')->insert([
            'customer_id' => $customer->id, 'direction' => 'out', 'kind' => 'offer', 'body' => 'Hi Maria',
            'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(CustomerAnonymiseService::class)->anonymise($customer);

        $gone = Customer::withTrashed()->find($customer->id);
        $this->assertTrue($gone->trashed());
        $this->assertSame('Deleted customer', $gone->name);
        $this->assertNull($gone->phone_e164);
        $this->assertNull($gone->phone_search);
        $this->assertNull($gone->default_pin_url);
        $this->assertFalse((bool) $gone->whatsapp_opt_in);

        $o = Order::query()->find($order->id);
        $this->assertSame('Deleted customer', $o->customer_name);
        $this->assertNull($o->phone_e164);
        $this->assertNull($o->location_pin_url);
        $this->assertNull($o->customer_notes);
        $this->assertSame('38.00', (string) $o->total); // totals stay for reports

        $this->assertNull(DB::table('consent_logs')->where('customer_id', $customer->id)->value('ip_address'));
        $this->assertSame('[removed]', DB::table('whatsapp_messages')->where('customer_id', $customer->id)->value('body'));
    }

    #[Test]
    public function the_same_number_can_order_again_as_a_new_customer(): void
    {
        [$customer, $order] = $this->customerWithOrder();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'delivered']);

        app(CustomerAnonymiseService::class)->anonymise($customer);

        $new = app(CustomerLookupService::class)->findOrCreate('0501230142', ['name' => 'Maria again']);

        $this->assertNotSame($customer->id, $new->id);
        $this->assertFalse($new->trashed());
    }
}