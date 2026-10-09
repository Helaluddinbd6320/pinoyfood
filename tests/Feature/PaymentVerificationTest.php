<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Filament\Pages\PaymentsToVerify;
use App\Models\Area;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentVerificationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function payment(string $status = 'awaiting_verification'): Payment
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);

        $order = app(OrderService::class)->place(new PlaceOrderData(
            cart: [$product->id => 1], name: 'Maria S.', phone: '0501230142',
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
            prepayDeliveryFee: true,
        ));

        $payment = $order->payments->firstOrFail();
        $payment->forceFill(['status' => $status])->save();

        return $payment->fresh();
    }

    #[Test]
    public function approving_counts_the_payment_and_lowers_what_the_rider_collects(): void
    {
        $staff = $this->user('staff');
        $payment = $this->payment();

        app(PaymentVerificationService::class)->approve($payment, $staff);

        $payment = $payment->fresh();
        $order = $payment->order->fresh();

        $this->assertSame('paid', $payment->status->value);
        $this->assertSame($staff->id, (int) $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame('10.00', (string) $order->paid_online_total);
        $this->assertSame('28.00', (string) $order->amount_due_on_delivery); // total 38.00 minus 10.00
    }

    #[Test]
    public function rejecting_needs_a_reason_and_changes_no_amounts(): void
    {
        $payment = $this->payment();

        try {
            app(PaymentVerificationService::class)->reject($payment, $this->user('staff'), '  ');
            $this->fail('A reason is required.');
        } catch (InvalidArgumentException) {
            $this->assertSame('awaiting_verification', $payment->fresh()->status->value);
        }

        app(PaymentVerificationService::class)->reject($payment, $this->user('staff'), 'Amount is wrong');

        $this->assertSame('rejected', $payment->fresh()->status->value);
        $this->assertSame('Amount is wrong', $payment->fresh()->reject_reason);
        $this->assertSame('0.00', (string) $payment->order->fresh()->paid_online_total);
    }

    #[Test]
    public function only_payments_awaiting_verification_can_be_approved(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PaymentVerificationService::class)->approve($this->payment('awaiting_proof'), $this->user('staff'));
    }

    #[Test]
    public function a_payment_cannot_be_approved_twice(): void
    {
        $payment = $this->payment();
        $service = app(PaymentVerificationService::class);
        $service->approve($payment, $this->user('staff'));

        $this->expectException(InvalidArgumentException::class);
        $service->approve($payment, $this->user('staff'));
    }

    #[Test]
    public function only_admin_and_staff_open_the_page(): void
    {
        $this->actingAs($this->user('rider'));
        $this->assertFalse(PaymentsToVerify::canAccess());

        $this->actingAs($this->user('staff'));
        $this->assertTrue(PaymentsToVerify::canAccess());
    }

    #[Test]
    public function staff_approve_from_the_page_and_the_badge_counts_waiting_payments(): void
    {
        $payment = $this->payment();
        $this->actingAs($this->user('staff'));

        $this->assertSame('1', PaymentsToVerify::getNavigationBadge());

        Livewire::test(PaymentsToVerify::class)->call('approve', $payment->id);

        $this->assertSame('paid', $payment->fresh()->status->value);
        $this->assertNull(PaymentsToVerify::getNavigationBadge());
    }

    #[Test]
    public function rejecting_from_the_page_needs_a_reason(): void
    {
        $payment = $this->payment();

        Livewire::actingAs($this->user('staff'))->test(PaymentsToVerify::class)
            ->call('reject', $payment->id);
        $this->assertSame('awaiting_verification', $payment->fresh()->status->value);

        Livewire::actingAs($this->user('staff'))->test(PaymentsToVerify::class)
            ->set("reasons.{$payment->id}", 'Blurry')
            ->call('reject', $payment->id);
        $this->assertSame('rejected', $payment->fresh()->status->value);
    }

    #[Test]
    public function screenshots_are_served_to_staff_only(): void
    {
        $payment = $this->payment();
        Storage::disk('local')->put('payment-proofs/x.jpg', 'jpegbytes');
        $payment->forceFill(['proof_path' => 'payment-proofs/x.jpg'])->save();

        $this->get(route('payments.proof', $payment))->assertForbidden();
        $this->actingAs($this->user('rider'))->get(route('payments.proof', $payment))->assertForbidden();
        $this->actingAs($this->user('staff'))->get(route('payments.proof', $payment))->assertOk();
    }

    #[Test]
    public function the_upload_page_needs_a_signed_link(): void
    {
        $payment = $this->payment('awaiting_proof');

        $this->get(route('payment.proof', $payment->id))->assertForbidden();

        $url = URL::temporarySignedRoute('payment.proof', now()->addDay(), ['payment' => $payment->id]);
        $this->get($url)->assertOk()->assertSee($payment->order->order_no);
    }

    #[Test]
    public function old_screenshots_are_deleted_after_ninety_days(): void
    {
        $payment = $this->payment();
        Storage::disk('local')->put('payment-proofs/old.jpg', 'x');
        $payment->forceFill(['proof_path' => 'payment-proofs/old.jpg', 'proof_hash' => str_repeat('a', 64)])->save();
        DB::table('orders')->where('id', $payment->order_id)->update(['status' => 'delivered', 'delivered_at' => now()->subDays(91)]);

        Artisan::call('payments:cleanup-proofs');

        Storage::disk('local')->assertMissing('payment-proofs/old.jpg');
        $this->assertNull($payment->fresh()->proof_path);
    }

    #[Test]
    public function recent_screenshots_are_kept(): void
    {
        $payment = $this->payment();
        Storage::disk('local')->put('payment-proofs/new.jpg', 'x');
        $payment->forceFill(['proof_path' => 'payment-proofs/new.jpg'])->save();
        DB::table('orders')->where('id', $payment->order_id)->update(['status' => 'delivered', 'delivered_at' => now()->subDays(10)]);

        Artisan::call('payments:cleanup-proofs');

        Storage::disk('local')->assertExists('payment-proofs/new.jpg');
    }
}