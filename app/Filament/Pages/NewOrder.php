<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Data\PlaceOrderData;
use App\Enums\OrderSource;
use App\Exceptions\InsufficientStockException;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CustomerLookupService;
use App\Services\OrderService;
use App\Services\PricingService;
use BackedEnum;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class NewOrder extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?string $navigationLabel = 'New order';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'New order';

    protected string $view = 'filament.pages.new-order';

    /** Sources staff may choose. "website" is reserved for the storefront. */
    private const SOURCES = ['call', 'whatsapp', 'admin'];

    public string $source = 'call';
    public string $phone = '';
    public string $name = '';
    public string $areaId = '';
    public string $pinUrl = '';
    public string $notes = '';

    /** @var array<int,int> productId => qty */
    public array $qty = [];

    public ?int $customerId = null;

    /** Admin and staff only. Riders never see this page. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isBackOffice();
    }

    public function setSource(string $source): void
    {
        if (in_array($source, self::SOURCES, true)) {
            $this->source = $source;
        }
    }

    public function updatedPhone(CustomerLookupService $lookup): void
    {
        $customer = $lookup->find($this->phone);

        $this->customerId = $customer?->id;

        if ($customer) {
            $this->name = (string) $customer->name;
            $this->areaId = $customer->area_id ? (string) $customer->area_id : $this->areaId;
        }
    }

    public function step(int $productId, int $delta): void
    {
        $product = Product::query()->where('is_active', true)->find($productId);

        if (! $product) {
            return;
        }

        $max = $product->track_stock ? max(0, (int) $product->stock) : 99;
        $next = max(0, min($max, ($this->qty[$productId] ?? 0) + $delta));

        if ($next === 0) {
            unset($this->qty[$productId]);
        } else {
            $this->qty[$productId] = $next;
        }
    }

    public function useLastOrder(): void
    {
        if (! $this->customerId) {
            return;
        }

        $last = Order::query()
            ->where('customer_id', $this->customerId)
            ->latest('id')
            ->with('items')
            ->first();

        if (! $last) {
            return;
        }

        $this->qty = [];

        foreach ($last->items as $item) {
            $product = $item->product_id
                ? Product::query()->where('is_active', true)->find($item->product_id)
                : null;

            if (! $product) {
                continue;
            }

            $max = $product->track_stock ? max(0, (int) $product->stock) : 99;
            $qty = min((int) $item->qty, $max);

            if ($qty > 0) {
                $this->qty[$product->id] = $qty;
            }
        }
    }

    public function place(OrderService $orders): void
    {
        $this->validate();

        $cart = array_filter($this->qty, fn ($q) => (int) $q > 0);

        if ($cart === []) {
            $this->addError('qty', 'Add at least one item.');

            return;
        }

        $data = new PlaceOrderData(
            cart: $cart,
            name: trim($this->name),
            phone: trim($this->phone),
            areaId: (int) $this->areaId,
            pinUrl: trim($this->pinUrl) !== '' ? trim($this->pinUrl) : null,
            notes: trim($this->notes) !== '' ? trim($this->notes) : null,
            optIn: false,
            optInSource: 'admin',
            source: $this->source,
            createdBy: auth()->id(),
        );

        try {
            $order = $orders->place($data);
        } catch (InsufficientStockException $e) {
            $this->addError('qty', $e->available > 0
                ? "Only {$e->available} of {$e->productName} left."
                : "{$e->productName} is sold out.");

            return;
        } catch (DomainException $e) {
            $this->addError('qty', $e->getMessage());

            return;
        }

        Notification::make()
            ->title("Order {$order->order_no} added")
            ->body('Source: '.OrderSource::from($this->source)->label())
            ->success()
            ->send();

        $this->reset(['phone', 'name', 'areaId', 'pinUrl', 'notes', 'qty', 'customerId']);
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $customer = $this->customerId ? Customer::query()->find($this->customerId) : null;
        $area = $this->areaId !== ''
            ? Area::query()->where('is_active', true)->find((int) $this->areaId)
            : null;

        $cart = array_filter($this->qty, fn ($q) => (int) $q > 0);

        return [
            'customer' => $customer,
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'areas' => Area::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'quote' => app(PricingService::class)->quote(
                $cart,
                $area,
                now(),
                (bool) ($customer?->requires_prepay),
            ),
            'sources' => collect(self::SOURCES)->mapWithKeys(
                fn (string $s) => [$s => OrderSource::from($s)->label()],
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'source' => ['required', Rule::in(self::SOURCES)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', function (string $attr, mixed $value, \Closure $fail) {
                // Spec 5: never block on format, only require something that looks like a number.
                if (preg_match_all('/\p{Nd}/u', (string) $value) < 7) {
                    $fail('Enter the customer\'s number.');
                }
            }],
            'areaId' => ['required', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'pinUrl' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required' => 'Enter the customer\'s name.',
            'phone.required' => 'Enter the customer\'s number.',
            'areaId.required' => 'Choose an area.',
            'areaId.exists' => 'Choose an area.',
            'pinUrl.url' => 'Paste a full link that starts with https://',
        ];
    }
}