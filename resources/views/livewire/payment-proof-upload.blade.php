@use('App\Support\Sar')
@use('App\Enums\PaymentStatus')
<div>
    @if ($payment)
        <div class="cx-card">
            <h2 class="cx-h2">{{ $payment->type->label() }}: {{ Sar::format($payment->amount) }}</h2>

            @if ($payment->status === PaymentStatus::Paid)
                <p><b>Payment verified ✓.</b> Salamat! The rider will collect the rest on delivery.</p>
            @elseif ($payment->status === PaymentStatus::AwaitingVerification)
                <p>Screenshot received. We will verify it shortly and message you on WhatsApp.</p>
                <p class="cx-muted">Until it is verified, the rider collects the full amount.</p>
            @elseif ($canUpload)
                @if ($payment->status === PaymentStatus::Rejected)
                    <p class="cx-er" role="alert">We could not verify your screenshot: {{ $payment->reject_reason }}. Please send it again.</p>
                @endif

                <p>
                    @if ($instructions !== '')
                        {!! nl2br(e($instructions)) !!}
                    @else
                        We will message you on WhatsApp with where to send it.
                    @endif
                </p>
                <p class="cx-muted">This is optional. If you prefer, pay everything to the rider on delivery.</p>

                <form wire:submit="save" novalidate>
                    <div class="cx-fg">
                        <label for="pp-method">How did you pay?</label>
                        <select class="cx-in" id="pp-method" wire:model="method">
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cx-fg">
                        <label for="pp-photo">Payment screenshot</label>
                        <input class="cx-in" id="pp-photo" type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp">
                        <div class="cx-muted" wire:loading wire:target="photo">Uploading...</div>
                        @error('photo') <div class="cx-er">{{ $message }}</div> @enderror
                    </div>

                    <div class="cx-fg">
                        <label for="pp-ref">Note <i>(optional)</i></label>
                        <input class="cx-in" id="pp-ref" wire:model="reference" maxlength="100" placeholder="Name on the transfer">
                    </div>

                    <button class="cx-btn" type="submit" wire:loading.attr="disabled" wire:target="save,photo">Send screenshot</button>
                </form>
            @endif
        </div>
    @endif
</div>