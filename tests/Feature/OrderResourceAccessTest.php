<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin'); // if the 5.x testing docs use another call, use that
    }

    #[Test]
    public function a_rider_only_sees_orders_assigned_to_them(): void
    {
        $rider = User::factory()->create(['role' => UserRole::Rider->value, 'is_active' => true]);
        $mine = Order::factory()->create(['rider_id' => $rider->id]);
        $other = Order::factory()->create(['rider_id' => null]);

        $this->actingAs($rider);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function staff_see_all_orders(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff->value, 'is_active' => true]);
        $orders = Order::factory()->count(3)->create();

        $this->actingAs($staff);

        Livewire::test(ListOrders::class)->assertCanSeeTableRecords($orders);
    }
}