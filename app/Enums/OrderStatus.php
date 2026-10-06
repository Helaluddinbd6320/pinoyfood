<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case NoAnswer = 'no_answer';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Confirmed => 'Confirmed',
            self::OutForDelivery => 'On the way',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::NoAnswer => 'No answer',
        };
    }
}