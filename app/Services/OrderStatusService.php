<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderStatusService
{
    /** Spec 6.5: this many no-answers flag the customer as prepay required. */
    public const NO_ANSWER_THRESHOLD = 2;

    public const MOVES = [
        'new'              => ['confirmed', 'no_answer', 'cancelled'],
        'confirmed'        => ['out_for_delivery', 'no_answer', 'cancelled'],
        'out_for_delivery' => ['delivered', 'no_answer', 'cancelled'],
        'no_answer'        => ['confirmed', 'cancelled'],
        'delivered'        => [],
        'cancelled'        => [],
    ];

    private const STAMPS = [
        'confirmed'        => 'confirmed_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered'        => 'delivered_at',
        'cancelled'        => 'cancelled_at',
    ];

    public static function current(Order $order): string
    {
        $status = $order->status;

        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }

    public static function canMove(Order $order, string $to): bool
    {
        return in_array($to, self::MOVES[self::current($order)] ?? [], true);
    }

    public function move(Order $order, string $to, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $to, $reason) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! self::canMove($locked, $to)) {
                throw new InvalidArgumentException(
                    "Order {$locked->order_no} cannot move from ".self::current($locked)." to {$to}."
                );
            }

            $locked->status = $to;

            if (isset(self::STAMPS[$to])) {
                $locked->{self::STAMPS[$to]} = now();
            }

            if ($to === 'cancelled') {
                $locked->cancel_reason = $reason;
            }

            $locked->save();

            $this->updateCustomer($locked, $to);

            if ($to === 'cancelled') {
                $this->restock($locked);
            }

            return $locked;
        });
    }

    /**
     * Puts the ordered quantities back on the shelf. Cancelled is a final status, so this
     * runs once per order. Products that no longer track stock are left alone.
     */
    private function restock(Order $order): void
    {
        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty) as qty')
            ->pluck('qty', 'product_id')
            ->all();

        // Fixed order, so two cancellations at once cannot deadlock.
        ksort($items);

        foreach ($items as $productId => $qty) {
            Product::withTrashed()
                ->whereKey((int) $productId)
                ->where('track_stock', true)
                ->increment('stock', (int) $qty);
        }
    }

    /** No-answer counting (spec 6.5) and total_spent on delivery. Runs inside the move transaction. */
    private function updateCustomer(Order $order, string $to): void
    {
        if ($to === 'no_answer') {
            $customer = Customer::withTrashed()->lockForUpdate()->find($order->customer_id);

            if ($customer === null) {
                return;
            }

            $count = ((int) $customer->no_answer_count) + 1;
            $customer->no_answer_count = $count;

            if ($count >= self::NO_ANSWER_THRESHOLD) {
                $customer->requires_prepay = true;
            }

            $customer->save();
        }

        if ($to === 'delivered') {
            // Delivered orders never move again, so this adds each order once.
            $amount = number_format((float) $order->total, 2, '.', '');

            Customer::withTrashed()->whereKey($order->customer_id)
                ->update(['total_spent' => DB::raw('total_spent + '.$amount)]);
        }
    }
}