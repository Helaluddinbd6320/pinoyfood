<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Product;
use App\Services\Pricing\Quote;
use App\Services\Pricing\QuoteLine;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Spec 6.2 and 6.9. The input is only product ids and quantities,
 * so browser-supplied prices and totals are never used.
 */
class PricingService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<int|string, int|string>  $cart  product id => quantity
     */
    public function quote(
        array $cart,
        ?Area $area = null,
        ?CarbonInterface $at = null,
        bool $customerRequiresPrepay = false,
    ): Quote {
        $at ??= now();
        $cart = $this->cleanCart($cart);

        $products = $cart === []
            ? collect()
            : Product::query()->where('is_active', true)->whereKey(array_keys($cart))->get()->keyBy('id');

        $lines = [];
        $unavailable = [];
        $subtotal = 0;
        $savings = 0;

        foreach ($cart as $id => $qty) {
            $product = $products->get($id);

            if ($product === null) {
                $unavailable[] = $id;

                continue;
            }

            $list = Money::toCents($product->price);
            $unit = Money::toCents($product->effectivePriceAt($at));

            $subtotal += $unit * $qty;
            $savings += ($list - $unit) * $qty;

            $lines[] = new QuoteLine(
                productId: $product->id,
                name: $product->name,
                qty: $qty,
                listPrice: Money::fromCents($list),
                unitPrice: Money::fromCents($unit),
                unitCost: $product->cost === null ? null : Money::fromCents(Money::toCents($product->cost)),
                lineTotal: Money::fromCents($unit * $qty),
                onOffer: $unit < $list,
                discountPercent: $product->discountPercentAt($at),
            );
        }

        // Delivery fee: area override, else global fee. Free delivery is judged on the subtotal AFTER offers.
        $fee = ($area !== null && $area->delivery_fee_override !== null)
            ? Money::toCents($area->delivery_fee_override)
            : Money::toCents($this->settings->deliveryFee());

        $freeEnabled = $this->settings->freeDeliveryEnabled();
        $freeMin = Money::toCents($this->settings->freeDeliveryMin());

        $thresholdMet = $freeEnabled && $subtotal > 0 && $subtotal >= $freeMin;
        $delivery = ($subtotal === 0 || $thresholdMet) ? 0 : $fee;
        $freeApplied = $thresholdMet && $fee > 0;
        $toFree = ($freeEnabled && $subtotal > 0 && ! $thresholdMet && $fee > 0) ? $freeMin - $subtotal : null;

        $discount = 0; // coupons and referral credits come later through this same service
        $total = $subtotal + $delivery - $discount;

        // Optional advance payment (spec 6.2)
        $prepayType = null;
        $prepayAmount = 0;

        if ($subtotal > 0) {
            if ($delivery > 0 && $this->settings->allowPrepayDeliveryFee()) {
                $prepayType = 'delivery_fee_prepay';
                $prepayAmount = $delivery;
            } elseif ($freeApplied && $this->settings->requireDepositForFreeDelivery()) {
                $deposit = Money::toCents($this->settings->depositAmount());
                if ($deposit > 0) {
                    $prepayType = 'deposit';
                    $prepayAmount = min($deposit, $total);
                }
            }
        }

        $minOrder = Money::toCents($this->settings->minOrderAmount());

        return new Quote(
            lines: $lines,
            unavailableProductIds: $unavailable,
            subtotal: Money::fromCents($subtotal),
            offerSavings: Money::fromCents($savings),
            deliveryFee: Money::fromCents($delivery),
            freeDeliveryApplied: $freeApplied,
            amountToFreeDelivery: $toFree === null ? null : Money::fromCents($toFree),
            discountTotal: Money::fromCents($discount),
            total: Money::fromCents($total),
            prepayType: $prepayType,
            prepayAmount: Money::fromCents($prepayAmount),
            prepayRequired: $customerRequiresPrepay && $prepayType !== null,
            minOrderAmount: Money::fromCents($minOrder),
            meetsMinimum: $subtotal >= $minOrder,
        );
    }

    /** @return array<int, int> */
    private function cleanCart(array $cart): array
    {
        $clean = [];

        foreach ($cart as $id => $qty) {
            $id = (int) $id;
            $qty = (int) $qty;

            if ($id > 0 && $qty > 0) {
                $clean[$id] = ($clean[$id] ?? 0) + $qty;
            }
        }

        return $clean;
    }
}