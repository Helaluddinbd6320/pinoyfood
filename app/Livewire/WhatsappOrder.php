<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\PlaceOrderData;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PricesChangedException;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CustomerAccountService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\WhatsappLinkService;
use Closure;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Order via WhatsApp": the order is saved first (source "whatsapp"), then the
 * customer sends the ready message that carries the order number.
 */
class WhatsappOrder extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $areaId = '';
    public string $notes = '';
    public string $website = ''; // honeypot, must stay empty

    public string $seenFingerprint = '';
    public ?string $notice = null;

    #[Locked]
    public ?string $orderNo = null;

    #[Locked]
    public ?string $doneUrl = null;

    #[Locked]
    public ?string $waUrl = null;

    public function mount(CartService $cart, PricingService $pricing): void
    {
        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Customer) {
            $this->name = (string) $customer->name;
            $this->phone = (string) $customer->phone_raw;

            if ($customer->area_id && Area::query()->where('is_active', true)->whereKey($customer->area_id)->exists()) {
                $this->areaId = (string) $customer->area_id;
            }
        }

        $this->seenFingerprint = $this->quote($cart, $pricing)->fingerprint();
    }

    public function updatedAreaId(CartService $cart, PricingService $pricing): void
    {
        // Choosing an area can legitimately change the fee. That is not "Prices updated".
        $this->seenFingerprint = $this->quote($cart, $pricing)->fingerprint();
        $this->notice = null;
    }

    public function place(
        OrderService $orders,
        CartService $cart,
        PricingService $pricing,
        CustomerAccountService $accounts,
        WhatsappLinkService $links,
        Request $request,
    ): void {
        if ($this->orderNo !== null) {
            return; // already placed: never create a second order from this page
        }

        if ($this->website !== '') {
            $this->redirectRoute('home');

            return;
        }

        $key = 'wa-order:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('form', 'Too many attempts. Please wait a minute and try again.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $items = $cart->items();
        if ($items === []) {
            $this->addError('form', 'Your cart is empty.');

            return;
        }

        $data = new PlaceOrderData(
            cart: $items,
            name: trim($this->name),
            phone: trim($this->phone),
            areaId: (int) $this->areaId,
            notes: trim($this->notes) !== '' ? trim($this->notes) : null,
            optIn: false,
            source: 'whatsapp',
            expectedFingerprint: $this->seenFingerprint,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        try {
            $order = $orders->place($data);
        } catch (PricesChangedException $e) {
            $this->seenFingerprint = $e->newFingerprint;
            $this->notice = 'Prices updated. Please check your total, then press Confirm.';

            return;
        } catch (InsufficientStockException $e) {
            $this->addError('form', $e->available > 0
                ? "Sorry, only {$e->available} of {$e->productName} left. Your cart was adjusted."
                : "Sorry, {$e->productName} is sold out. Please check your cart.");

            return;
        } catch (DomainException) {
            $this->addError('form', 'Your order is below the minimum order amount.');

            return;
        }

        $cart->clear();
        $this->dispatch('cart-updated');

        // A Google-only customer gets the typed number linked. Never blocks the order.
        $signedIn = Auth::guard('customer')->user();
        if ($signedIn instanceof Customer && blank($signedIn->phone_search)) {
            try {
                Auth::guard('customer')->login($accounts->attachPhone($signedIn, trim($this->phone)));
            } catch (DomainException|InvalidArgumentException) {
                // Leave the account as it is.
            }
        }

        $this->orderNo = (string) $order->order_no;
        $this->doneUrl = URL::temporarySignedRoute('order.done', now()->addDays(7), ['orderNo' => $order->order_no]);
        $this->waUrl = $links->toBusiness($links->placedOrderText($order));
    }

    public function render(CartService $cart, PricingService $pricing)
    {
        $items = $this->orderNo !== null ? [] : $cart->items();

        return view('livewire.whatsapp-order', [
            'items' => $items,
            'products' => Product::query()->whereIn('id', array_keys($items))->get()->keyBy('id'),
            'quote' => $this->quote($cart, $pricing),
            'areas' => Area::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    private function quote(CartService $cart, PricingService $pricing)
    {
        $area = $this->areaId !== ''
            ? Area::query()->where('is_active', true)->find((int) $this->areaId)
            : null;

        return $pricing->quote($cart->items(), $area, now(), false);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', function (string $attr, mixed $value, Closure $fail) {
                // Spec 5: never block on format. Only require something that looks like a number.
                if (preg_match_all('/\p{Nd}/u', (string) $value) < 7) {
                    $fail('Enter your WhatsApp number.');
                }
            }],
            'areaId' => ['required', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'phone.required' => 'Enter your WhatsApp number.',
            'areaId.required' => 'Choose your area.',
            'areaId.exists' => 'Choose your area.',
        ];
    }
}