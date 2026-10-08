<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Customer;
use App\Services\CustomerAccountService;
use Closure;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

class WelcomeStrip extends Component
{
    public string $phone = '';

    public ?string $notice = null;

    #[On('account-updated')]
    public function refresh(): void
    {
    }

    public function savePhone(CustomerAccountService $accounts): void
    {
        $customer = $this->customer();

        if (! $customer) {
            return;
        }

        // Typing numbers until one matches would expose other customers' history.
        $key = 'attach-phone:'.$customer->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', 'Too many tries. Please wait a few minutes.');

            return;
        }

        $this->validate();
        RateLimiter::hit($key, 600);

        try {
            $result = $accounts->attachPhone($customer, $this->phone);
        } catch (DomainException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        } catch (InvalidArgumentException) {
            $this->addError('phone', 'Enter your WhatsApp number.');

            return;
        }

        Auth::guard('customer')->login($result);
        session()->put('phone_prompt_done', true);
        $this->phone = '';
        $this->notice = 'Number saved.';
        $this->dispatch('account-updated');
    }

    public function skipPhone(): void
    {
        session()->put('phone_prompt_done', true);
    }

    public function reorder(int $orderId, CustomerAccountService $accounts): void
    {
        $customer = $this->customer();

        if (! $customer) {
            return;
        }

        $r = $accounts->reorder($customer, $orderId);

        if ($r['added'] === 0 && $r['skipped'] === 0) {
            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: $r['added'] > 0
            ? "Added {$r['added']} item".($r['added'] === 1 ? '' : 's').' to your cart'.($r['skipped'] ? " ({$r['skipped']} not available)" : '')
            : 'Those items are not available right now');
    }

    public function render(CustomerAccountService $accounts)
    {
        $customer = $this->customer();

        if (! $customer) {
            return view('livewire.welcome-strip', ['customer' => null]);
        }

        return view('livewire.welcome-strip', [
            'customer' => $customer,
            'first' => Str::before((string) ($customer->name ?: 'there'), ' '),
            'askPhone' => blank($customer->phone_search) && ! session('phone_prompt_done'),
            'orders' => $accounts->recentOrders($customer, 3),
        ]);
    }

    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:40', function (string $attr, mixed $value, Closure $fail) {
                // Spec 5: never block on format, only require something that looks like a number.
                if (preg_match_all('/\p{Nd}/u', (string) $value) < 7) {
                    $fail('Enter your WhatsApp number.');
                }
            }],
        ];
    }

    protected function messages(): array
    {
        return ['phone.required' => 'Enter your WhatsApp number.'];
    }

    private function customer(): ?Customer
    {
        $c = Auth::guard('customer')->user();

        return $c instanceof Customer ? $c : null;
    }
}