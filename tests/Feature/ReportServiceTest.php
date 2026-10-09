<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Filament\Widgets\StatsOverview;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'cost' => '14.00', 'sale_price' => null,
            'stock' => 50, 'track_stock' => true, 'low_stock_threshold' => 5, 'is_active' => true,
        ], $attrs));
    }

    private function order(Product $p, int $qty = 1, string $phone = '0501230142'): Order
    {
        return app(OrderService::class)->place(new PlaceOrderData(
            cart: [$p->id => $qty], name: 'Maria S.', phone: $phone,
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
        ));
    }

    #[Test]
    public function today_counts_orders_and_sales_but_not_cancelled_ones(): void
    {
        $p = $this->product();
        $kept = $this->order($p);
        $cancelled = $this->order($p, 1, '0559998888');
        DB::table('orders')->where('id', $cancelled->id)->update(['status' => 'cancelled']);

        $today = app(ReportService::class)->today();

        $this->assertSame(1, $today['orders']);
        $this->assertSame((string) $kept->total, $today['sales']);
        $this->assertSame(0, $today['waiting'] - 1); // only the kept order is "new"
    }

    #[Test]
    public function the_summary_reports_offer_savings_margin_and_fees(): void
    {
        $p = $this->product(['price' => '30.00', 'cost' => '12.00', 'sale_price' => '20.00',
            'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]);
        $this->order($p, 2);

        [$from, $to] = app(ReportService::class)->range(now()->toDateString(), now()->toDateString());
        $s = app(ReportService::class)->summary($from, $to);

        $this->assertSame(1, $s['orders']);
        $this->assertSame('50.00', $s['sales']);          // 2 x 20 + 10 delivery
        $this->assertSame('10.00', $s['delivery_fees']);
        $this->assertSame('20.00', $s['offer_savings']);  // (30 - 20) x 2
        $this->assertSame('16.00', $s['margin']);         // (20 - 12) x 2
    }

    #[Test]
    public function orders_are_grouped_by_source_area_and_product(): void
    {
        $p = $this->product(['name' => 'Leche flan']);
        $this->order($p, 3);

        $report = app(ReportService::class);
        [$from, $to] = $report->range(null, null);

        $this->assertSame('website', $report->bySource($from, $to)->first()->source);
        $this->assertSame(1, $report->byArea($from, $to)->first()->orders);
        $top = $report->topProducts($from, $to)->first();
        $this->assertSame('Leche flan', $top->name);
        $this->assertSame(3, $top->qty);
    }

    #[Test]
    public function the_range_swaps_reversed_dates_and_survives_garbage(): void
    {
        $report = app(ReportService::class);

        [$from, $to] = $report->range('2026-10-09', '2026-10-01');
        $this->assertTrue($from->lte($to));

        [$from, $to] = $report->range('not a date', '');
        $this->assertTrue($from->lte($to));
    }

    #[Test]
    public function low_stock_lists_only_active_tracked_products_at_their_threshold(): void
    {
        $low = $this->product(['name' => 'Low', 'stock' => 3]);
        $this->product(['name' => 'Plenty', 'stock' => 40]);
        $this->product(['name' => 'Untracked', 'stock' => 0, 'track_stock' => false]);
        $this->product(['name' => 'Inactive', 'stock' => 1, 'is_active' => false]);

        $names = app(ReportService::class)->lowStock()->pluck('name')->all();

        $this->assertSame([$low->name], $names);
    }

    #[Test]
    public function only_admin_and_staff_see_the_dashboard_numbers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'rider', 'is_active' => true]));
        $this->assertFalse(StatsOverview::canView());

        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
        $this->assertTrue(StatsOverview::canView());
    }
}