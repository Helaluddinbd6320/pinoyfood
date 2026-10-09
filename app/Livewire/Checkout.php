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
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class Checkout extends Component
{
    /** Delivery slots: label, days from today, start hour (Riyadh). */
    private const SLOTS = [
        ['Today, 4 to 6 PM', 0, 16],
        ['Today, 6 to 8 PM', 0, 18],
        ['Tomorrow, 12 to 2 PM', 1, 12],
        ['Tomorrow, 4 to 6 PM', 1, 16],
        ['Tomorrow, 6 to 8 PM', 1, 18],
    ];

    /** A slot disappears this many hours before it starts. */
    private const LEAD_HOURS = 1;

    public string $name = '';
    public string $phone = '';
    public string $areaId = '';
    public string $pinUrl = '';
    public string $lat = '';
    public string $lng = '';
    public bool $geoError = false;
    public string $slot = '';
    public string $notes = '';
    public bool $optIn = false;
    public bool $prepay = false; // optional: pay the delivery fee in advance (spec 6.2)
    public string $website = ''; // honeypot, must stay empty

    public string $seenFingerprint = '';
    public ?string $notice = null;

    public function mount(CartService $cart, PricingService $pricing): void
    {
        // Signed-in customers get their details prefilled (spec 6.3). Guests are unchanged.
        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Customer) {
            $this->name = (string) $customer->name;
            $this->phone = (string) $customer->phone_raw;
            $this->pinUrl = (string) $customer->default_pin_url;
            $this->optIn = (bool) $customer->whatsapp_opt_in;

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

    public function place(OrderService $orders, CartService $cart, PricingService $pricing, CustomerAccountService $accounts, Request $request): void
    {
        if ($this->website !== '') {
            $this->redirectRoute('home');

            return;
        }

        $key = 'checkout:'.$request->ip();
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

        $slots = $this->slots();
        $lat = $this->lat !== '' ? (float) $this->lat : null;
        $lng = $this->lng !== '' ? (float) $this->lng : null;
        $pin = trim($this->pinUrl) !== ''
            ? trim($this->pinUrl)
            : ($lat !== null && $lng !== null ? "https://maps.google.com/?q={$this->lat},{$this->lng}" : null);

        $data = new PlaceOrderData(
            cart: $items,
            name: trim($this->name),
            phone: trim($this->phone),
            areaId: (int) $this->areaId,
            pinUrl: $pin,
            lat: $lat,
            lng: $lng,
            deliveryDate: $this->slot !== '' ? $slots[$this->slot] : null,
            slotLabel: $this->slot !== '' ? $this->slot : null,
            notes: trim($this->notes) !== '' ? trim($this->notes) : null,
            optIn: $this->optIn,
            expectedFingerprint: $this->seenFingerprint,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            prepayDeliveryFee: $this->prepay,
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

        // A Google-only customer has no number yet: the number typed here links them
        // to the customer record the order was placed on. Never blocks the order.
        $signedIn = Auth::guard('customer')->user();
        if ($signedIn instanceof Customer && blank($signedIn->phone_search)) {
            try {
                Auth::guard('customer')->login($accounts->attachPhone($signedIn, trim($this->phone)));
            } catch (DomainException|InvalidArgumentException) {
                // Leave the account as it is.
            }
        }

        $this->redirect(URL::temporarySignedRoute('order.done', now()->addDays(7), ['orderNo' => $order->order_no]));
    }

    public function render(CartService $cart, PricingService $pricing)
    {
        $items = $cart->items();

        return view('livewire.checkout', [
            'items' => $items,
            'products' => Product::query()->whereIn('id', array_keys($items))->get()->keyBy('id'),
            'quote' => $this->quote($cart, $pricing),
            'areas' => Area::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'slots' => array_keys($this->slots()),
        ]);
    }

    /** @return array<string,string> label => Y-m-d */
    private function slots(): array
    {
        $now = now();
        $out = [];

        foreach (self::SLOTS as [$label, $days, $hour]) {
            $start = $now->copy()->startOfDay()->addDays($days)->setHour($hour);
            if ($start->gt($now->copy()->addHours(self::LEAD_HOURS))) {
                $out[$label] = $start->toDateString();
            }
        }

        return $out;
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
            'phone' => ['required', 'string', 'max:40', function (string $attr, mixed $value, \Closure $fail) {
                // Spec 5: never block on format. Only require something that looks like a number.
                if (preg_match_all('/\p{Nd}/u', (string) $value) < 7) {
                    $fail('Enter your WhatsApp number.');
                }
            }],
            'areaId' => ['required', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'pinUrl' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'slot' => ['nullable', Rule::in(array_keys($this->slots()))],
            'notes' => ['nullable', 'string', 'max:255'],
            'optIn' => ['boolean'],
            'prepay' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'phone.required' => 'Enter your WhatsApp number.',
            'areaId.required' => 'Choose your area.',
            'areaId.exists' => 'Choose your area.',
            'pinUrl.url' => 'Paste a full link that starts with https://',
        ];
    }
}