<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role, bool $active = true): User
    {
        return User::factory()->create(['role' => $role->value, 'is_active' => $active]);
    }

    #[Test]
    public function inactive_users_cannot_enter_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->user(UserRole::Staff)->canAccessPanel($panel));
        $this->assertFalse($this->user(UserRole::Staff, false)->canAccessPanel($panel));
    }

    #[Test]
    public function admin_sees_everything_and_staff_sees_orders_customers_products(): void
    {
        $admin = $this->user(UserRole::Admin);
        $staff = $this->user(UserRole::Staff);

        foreach ([Order::class, Customer::class, Product::class, Area::class, Category::class] as $model) {
            $this->assertTrue($admin->can('viewAny', $model), "admin viewAny {$model}");
        }

        foreach ([Order::class, Customer::class, Product::class] as $model) {
            $this->assertTrue($staff->can('viewAny', $model), "staff viewAny {$model}");
        }

        $this->assertFalse($staff->can('viewAny', Area::class));
        $this->assertFalse($staff->can('viewAny', Category::class));
    }

    #[Test]
    public function riders_cannot_see_customers_products_areas_or_categories(): void
    {
        $rider = $this->user(UserRole::Rider);

        foreach ([Customer::class, Product::class, Area::class, Category::class] as $model) {
            $this->assertFalse($rider->can('viewAny', $model), "rider viewAny {$model}");
        }
    }

    #[Test]
    public function riders_only_view_their_own_orders(): void
    {
        $rider = $this->user(UserRole::Rider);
        $other = $this->user(UserRole::Rider);
        $mine = Order::factory()->create(['rider_id' => $rider->id]);
        $theirs = Order::factory()->create(['rider_id' => $other->id]);

        $this->assertTrue($rider->can('view', $mine));
        $this->assertFalse($rider->can('view', $theirs));
        $this->assertFalse($rider->can('update', $mine));
    }

    #[Test]
    public function riders_may_only_set_out_for_delivery_or_delivered(): void
    {
        $rider = $this->user(UserRole::Rider);
        $order = Order::factory()->create(['rider_id' => $rider->id]);

        $this->assertTrue($rider->can('changeStatus', [$order, 'out_for_delivery']));
        $this->assertTrue($rider->can('changeStatus', [$order, 'delivered']));
        $this->assertFalse($rider->can('changeStatus', [$order, 'cancelled']));
        $this->assertFalse($rider->can('changeStatus', [$order, 'confirmed']));
    }

    #[Test]
    public function riders_cannot_change_status_on_orders_not_assigned_to_them(): void
    {
        $rider = $this->user(UserRole::Rider);
        $order = Order::factory()->create(['rider_id' => null]);

        $this->assertFalse($rider->can('changeStatus', [$order, 'delivered']));
    }

    #[Test]
    public function nobody_can_delete_orders(): void
    {
        $order = Order::factory()->create();

        foreach ([UserRole::Admin, UserRole::Staff, UserRole::Rider] as $role) {
            $this->assertFalse($this->user($role)->can('delete', $order), "{$role->value} delete order");
        }
    }

    #[Test]
    public function only_admin_deletes_products_and_customers_and_customers_are_never_force_deleted(): void
    {
        $admin = $this->user(UserRole::Admin);
        $staff = $this->user(UserRole::Staff);
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        $this->assertTrue($admin->can('delete', $product));
        $this->assertFalse($staff->can('delete', $product));
        $this->assertTrue($staff->can('update', $product));
        $this->assertTrue($admin->can('delete', $customer));
        $this->assertFalse($staff->can('delete', $customer));
        $this->assertFalse($admin->can('forceDelete', $customer));
        $this->assertFalse($admin->can('create', Customer::class));
    }
}