<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Livewire\PaymentProofUpload;
use App\Models\Area;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\PaymentProofService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentProofServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is not installed.');
        }

        Storage::fake('local');
    }

    private function payment(string $phone = '0501230142'): Payment
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);

        $order = app(OrderService::class)->place(new PlaceOrderData(
            cart: [$product->id => 1], name: 'Maria S.', phone: $phone,
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
            prepayDeliveryFee: true,
        ));

        return $order->payments->firstOrFail();
    }

    #[Test]
    public function it_stores_a_resized_jpeg_on_the_private_disk(): void
    {
        $payment = $this->payment();

        $saved = app(PaymentProofService::class)->store(
            $payment, UploadedFile::fake()->image('shot.png', 3000, 2000), 'stc_pay', 'Maria',
        );

        $this->assertSame('awaiting_verification', $saved->status->value);
        $this->assertSame('stc_pay', $saved->method);
        $this->assertSame('Maria', $saved->customer_reference);
        $this->assertNotNull($saved->proof_hash);
        $this->assertStringStartsWith('payment-proofs/', $saved->proof_path);
        $this->assertStringEndsWith('.jpg', $saved->proof_path);
        Storage::disk('local')->assertExists($saved->proof_path);

        $info = getimagesizefromstring(Storage::disk('local')->get($saved->proof_path));
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertLessThanOrEqual(1600, max($info[0], $info[1]));
    }

    #[Test]
    public function a_file_that_only_has_an_image_name_is_refused(): void
    {
        $this->expectException(DomainException::class);

        app(PaymentProofService::class)->store(
            $this->payment(), UploadedFile::fake()->createWithContent('shot.png', 'not an image at all'), 'stc_pay',
        );
    }

    #[Test]
    public function a_pdf_is_refused(): void
    {
        $this->expectException(DomainException::class);

        app(PaymentProofService::class)->store(
            $this->payment(), UploadedFile::fake()->create('shot.pdf', 20, 'application/pdf'), 'stc_pay',
        );
    }

    #[Test]
    public function an_unknown_method_becomes_other(): void
    {
        $saved = app(PaymentProofService::class)->store(
            $this->payment(), UploadedFile::fake()->image('a.jpg', 300, 300), 'bitcoin',
        );

        $this->assertSame('other', $saved->method);
    }

        #[Test]
    public function the_same_screenshot_on_another_payment_is_flagged_not_blocked(): void
    {
        $service = app(PaymentProofService::class);
        $first = $this->payment('0501230142');
        $second = $this->payment('0559998888');

        // Same bytes in two separate uploads. The image is built with GD so no temp file is lost.
        $img = imagecreatetruecolor(200, 200);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 10, 120, 200));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        $service->store($first, UploadedFile::fake()->createWithContent('a.png', $png), 'stc_pay');
        $saved = $service->store($second, UploadedFile::fake()->createWithContent('b.png', $png), 'stc_pay');

        $this->assertSame($first->id, $saved->payload['duplicate_of'] ?? null);
        $this->assertSame('awaiting_verification', $saved->status->value);
    }

    #[Test]
    public function a_paid_payment_cannot_take_a_new_screenshot(): void
    {
        $payment = $this->payment();
        $payment->forceFill(['status' => 'paid'])->save();

        $this->expectException(DomainException::class);

        app(PaymentProofService::class)->store($payment, UploadedFile::fake()->image('a.jpg', 200, 200), 'stc_pay');
    }

    #[Test]
    public function the_upload_component_saves_the_screenshot(): void
    {
        $payment = $this->payment();

        Livewire::test(PaymentProofUpload::class, ['paymentId' => $payment->id])
            ->set('photo', UploadedFile::fake()->image('a.jpg', 400, 400))
            ->set('method', 'bank_transfer')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('awaiting_verification', $payment->fresh()->status->value);
        $this->assertSame('bank_transfer', $payment->fresh()->method);
    }

    #[Test]
    public function the_upload_component_needs_a_file(): void
    {
        Livewire::test(PaymentProofUpload::class, ['paymentId' => $this->payment()->id])
            ->call('save')
            ->assertHasErrors(['photo']);
    }
}