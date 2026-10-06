<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderStatusService
{
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

            return $locked;
        });
    }
}