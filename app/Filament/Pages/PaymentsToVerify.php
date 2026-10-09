<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentVerificationService;
use App\Services\WhatsappLinkService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentsToVerify extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payments to verify';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Payments to verify';

    protected string $view = 'filament.pages.payments-to-verify';

    /** @var array<int, string> payment id => reason typed for Reject */
    public array $reasons = [];

    /** Admin and staff only. Riders never see screenshots. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isBackOffice();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $n = Payment::query()->where('status', PaymentStatus::AwaitingVerification->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    public function approve(int $paymentId, PaymentVerificationService $verification): void
    {
        $user = auth()->user();
        $payment = Payment::query()->find($paymentId);

        if (! $user?->isBackOffice() || ! $payment) {
            return;
        }

        try {
            $verification->approve($payment, $user);
            Notification::make()->title('Payment approved')->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Not allowed')->body($e->getMessage())->danger()->send();
        }
    }

    public function reject(int $paymentId, PaymentVerificationService $verification): void
    {
        $user = auth()->user();
        $payment = Payment::query()->find($paymentId);

        if (! $user?->isBackOffice() || ! $payment) {
            return;
        }

        try {
            $verification->reject($payment, $user, (string) ($this->reasons[$paymentId] ?? ''));
            unset($this->reasons[$paymentId]);
            Notification::make()->title('Payment rejected. Ask the customer to resend.')->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Not allowed')->body($e->getMessage())->danger()->send();
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $links = app(WhatsappLinkService::class);

        $verify = Payment::query()
            ->with('order')
            ->where('status', PaymentStatus::AwaitingVerification->value)
            ->orderBy('proof_uploaded_at')
            ->limit(100)
            ->get();

        $waiting = Payment::query()
            ->with('order')
            ->whereIn('status', [PaymentStatus::AwaitingProof->value, PaymentStatus::Rejected->value])
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Payment $p) => [
                'payment' => $p,
                'link' => $links->chat($p->order->phone_e164, $this->reminder($p)),
            ]);

        return ['verify' => $verify, 'waiting' => $waiting];
    }

    /** One reminder with the signed upload link (spec 6.2). Sent by a person through click-to-chat. */
    private function reminder(Payment $payment): string
    {
        $url = URL::temporarySignedRoute('payment.proof', now()->addDays(7), ['payment' => $payment->id]);
        $name = Str::before(trim((string) $payment->order->customer_name), ' ') ?: 'kababayan';
        $no = $payment->order->order_no;

        $why = $payment->status === PaymentStatus::Rejected
            ? "we could not verify your payment screenshot ({$payment->reject_reason}). Please send it again"
            : 'please upload your payment screenshot';

        return "Hi {$name}, order {$no}: {$why} here: {$url}\nIf you prefer, you can pay everything to the rider on delivery.";
    }
}