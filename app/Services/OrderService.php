<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PlaceOrderData;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PricesChangedException;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Pricing\Quote;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The only place that creates orders. Website checkout (step 7) and the
 * manual New order page (step 8) both call place().
 */
class OrderService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CustomerLookupService $customers,
    ) {
    }

    public function place(PlaceOrderData $data): Order
    {
        if ($data->cart === []) {
            throw new DomainException('The cart is empty.');
        }

        return DB::transaction(function () use ($data): Order {
            $now = now();

            // 1. Lock the products so two checkouts cannot sell the last item twice.
            $products = Product::query()
                ->whereIn('id', array_keys($data->cart))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($data->cart as $productId => $qty) {
                $product = $products->get($productId);

                if (! $product || ! $product->is_active) {
                    throw new InsufficientStockException($product?->name ?? 'An item', 0);
                }
                if ($product->track_stock && (int) $product->stock < $qty) {
                    throw new InsufficientStockException($product->name, max(0, (int) $product->stock));
                }
            }

            // 2. Reprice on the server.
            $area = Area::query()->where('is_active', true)->findOrFail($data->areaId);
            $quote = $this->pricing->quote($data->cart, $area, $now, false);

            if (! $quote->meetsMinimum) {
                throw new DomainException('The minimum order amount is not met.');
            }
            if ($data->expectedFingerprint !== null && $data->expectedFingerprint !== $quote->fingerprint()) {
                throw new PricesChangedException($quote->fingerprint());
            }

            // 3. Customer (any phone format, never blocks the order).
            $customer = $this->customers->findOrCreate($data->phone, [
                'name' => $data->name,
                'area_id' => $area->id,
            ]);

            $updates = [
                'orders_count' => ((int) $customer->orders_count) + 1,
                'last_order_at' => $now,
            ];
            if (blank($customer->name)) {
                $updates['name'] = $data->name;
            }
            if (! $customer->area_id) {
                $updates['area_id'] = $area->id;
            }
            if (blank($customer->default_pin_url) && $data->pinUrl) {
                $updates['default_pin_url'] = $data->pinUrl;
                $updates['default_lat'] = $data->lat !== null ? $this->coord($data->lat) : null;
                $updates['default_lng'] = $data->lng !== null ? $this->coord($data->lng) : null;
            }
            if ($data->optIn && ! $customer->whatsapp_opt_in) {
                $updates += [
                    'whatsapp_opt_in' => true,
                    'opted_in_at' => $now,
                    'opted_out_at' => null,
                    'opt_in_source' => $data->optInSource,
                ];
                DB::table('consent_logs')->insert([
                    'customer_id' => $customer->id,
                    'action' => 'opt_in',
                    'source' => $data->optInSource,
                    'ip_address' => $data->ip,
                    'user_agent' => $data->userAgent ? Str::limit($data->userAgent, 250, '') : null,
                    'created_at' => $now,
                ]);
            }
            $customer->forceFill($updates)->save();

            // 4. Order header. Temporary order_no, replaced once the id is known.
            $order = Order::unguarded(fn () => Order::query()->create([
                'order_no' => 'TMP'.Str::upper(Str::random(12)),
                'customer_id' => $customer->id,
                'source' => $data->source,
                'status' => 'new',
                'customer_name' => $data->name,
                'phone_raw' => trim($data->phone),
                'phone_e164' => $customer->phone_e164,
                'area_id' => $area->id,
                'location_pin_url' => $data->pinUrl,
                'lat' => $data->lat !== null ? $this->coord($data->lat) : null,
                'lng' => $data->lng !== null ? $this->coord($data->lng) : null,
                'delivery_date' => $data->deliveryDate,
                'slot_label' => $data->slotLabel,
                'subtotal' => $this->money($quote->subtotal),
                'delivery_fee' => $this->money($quote->deliveryFee),
                'free_delivery_applied' => (bool) $quote->freeDeliveryApplied,
                'discount_total' => '0.00',
                'total' => $this->money($quote->total),
                'paid_online_total' => '0.00',
                'amount_due_on_delivery' => $this->money($quote->total),
                'customer_notes' => $data->notes,
                'created_by' => $data->createdBy,
            ]));

            $order->forceFill(['order_no' => 'PF-'.(1000 + $order->id)])->save();

            // 5. Items (snapshots) and stock.
            $itemsTotal = '0.00';
            foreach ($data->cart as $productId => $qty) {
                $product = $products->get($productId);
                $unit = $this->money($product->effective_price);
                $line = bcmul($unit, (string) $qty, 2);
                $itemsTotal = bcadd($itemsTotal, $line, 2);

                OrderItem::unguarded(fn () => $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'list_price' => $this->money($product->price),
                    'unit_price' => $unit,
                    'unit_cost' => $product->cost !== null ? $this->money($product->cost) : null,
                    'qty' => $qty,
                    'line_total' => $line,
                ]));

                if ($product->track_stock) {
                    $product->decrement('stock', $qty);
                }
            }

            // Safety net: the snapshots must agree with the quote. Rolls everything back if not.
            if (bccomp($itemsTotal, $this->money($quote->subtotal), 2) !== 0) {
                throw new LogicException('Order items do not match the pricing quote.');
            }

            // 6. Optional advance payment (spec 6.2). The amount comes from the quote, never from the browser.
            // It stays "awaiting_proof" and does not reduce amount_due_on_delivery until staff verify it.
            if ($this->wantsPrepay($quote, $data, $customer)) {
                $order->payments()->create([
                    'type' => PaymentType::from((string) $quote->prepayType),
                    'method' => 'other',
                    'amount' => $this->money($quote->prepayAmount),
                    'status' => PaymentStatus::AwaitingProof,
                ]);
            }

            return $order->load('items', 'payments');
        });
    }

    private function wantsPrepay(Quote $quote, PlaceOrderData $data, Customer $customer): bool
    {
        if ($quote->prepayType === null || bccomp($this->money($quote->prepayAmount), '0', 2) !== 1) {
            return false;
        }

        // A required deposit (admin setting) is always created.
        if ($quote->prepayType === 'deposit') {
            return true;
        }

        // The delivery fee is optional, unless the customer is flagged (spec 6.2).
        return $data->prepayDeliveryFee || (bool) $customer->requires_prepay;
    }

    private function money(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    private function coord(float $value): string
    {
        return number_format($value, 7, '.', '');
    }
}