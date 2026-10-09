<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentProofService;
use App\Services\SettingsService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Only mounted from pages that are behind a signed link. The payment id is locked. */
class PaymentProofUpload extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $paymentId = 0;

    public $photo = null;

    public string $method = 'stc_pay';

    public string $reference = '';

    public function mount(int $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function save(PaymentProofService $proofs, Request $request): void
    {
        $key = 'proof:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            $this->addError('photo', 'Too many tries. Please wait a few minutes.');

            return;
        }
        RateLimiter::hit($key, 600);

        $this->validate();

        $payment = Payment::query()->find($this->paymentId);

        if (! $payment) {
            return;
        }

        try {
            $proofs->store($payment, $this->photo, $this->method, $this->reference);
        } catch (DomainException $e) {
            $this->addError('photo', $e->getMessage());

            return;
        }

        $this->reset(['photo', 'reference']);
    }

    public function render(SettingsService $settings)
    {
        $payment = Payment::query()->find($this->paymentId);

        return view('livewire.payment-proof-upload', [
            'payment' => $payment,
            'canUpload' => $payment && in_array($payment->status, [PaymentStatus::AwaitingProof, PaymentStatus::Rejected], true),
            'instructions' => trim($settings->prepayInstructions()),
            'methods' => ['stc_pay' => 'STC Pay', 'bank_transfer' => 'Bank transfer', 'other' => 'Other'],
        ]);
    }

    protected function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'method' => ['required', 'in:stc_pay,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'photo.required' => 'Choose your payment screenshot.',
            'photo.mimes' => 'The screenshot must be a JPG, PNG or WebP image.',
            'photo.max' => 'The screenshot must be 5 MB or smaller.',
        ];
    }
}