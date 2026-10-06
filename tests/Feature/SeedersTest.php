<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\AreaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_the_expected_rows(): void
    {
        $this->seed();

        $this->assertSame(count(AreaSeeder::NAMES), Area::count());
        $this->assertSame(4, Category::count());
        $this->assertSame(9, Product::count());
        $this->assertSame(8, Setting::count());
        $this->assertEquals(150, Setting::where('key', 'free_delivery_min')->first()->value);
        $this->assertTrue(Setting::where('key', 'free_delivery_enabled')->first()->value);
    }

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(count(AreaSeeder::NAMES), Area::count());
        $this->assertSame(9, Product::count());
        $this->assertSame(8, Setting::count());
    }

    public function test_area_names_are_unique_and_other_is_last(): void
    {
        $this->assertSame(count(AreaSeeder::NAMES), count(array_unique(AreaSeeder::NAMES)));

        $this->seed();

        $this->assertSame('Other', Area::orderByDesc('sort_order')->first()->name);
        $this->assertSame('Olaya', Area::orderBy('sort_order')->first()->name);
    }

    public function test_no_pork_products_are_seeded(): void
    {
        $this->seed();

        foreach (Product::all() as $product) {
            $text = strtolower($product->name.' '.$product->name_tl.' '.$product->description);

            foreach (['pork', 'baboy', 'bacon', 'lechon'] as $word) {
                $this->assertStringNotContainsString($word, $text, "{$product->name} looks like pork");
            }
        }
    }

    public function test_exactly_two_products_have_an_active_offer(): void
    {
        $this->seed();

        $offers = Product::whereNotNull('sale_price')->get();

        $this->assertCount(2, $offers);

        foreach ($offers as $product) {
            $this->assertTrue((float) $product->sale_price < (float) $product->price);
            $this->assertTrue($product->sale_starts_at->isPast());
            $this->assertTrue($product->sale_ends_at->isFuture());
        }
    }
}