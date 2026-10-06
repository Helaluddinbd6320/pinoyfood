<?php

namespace App\Enums;

enum PaymentType: string
{
    case DeliveryFeePrepay = 'delivery_fee_prepay';
    case Deposit = 'deposit';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::DeliveryFeePrepay => 'Delivery fee (advance)',
            self::Deposit => 'Deposit',
            self::Full => 'Full payment',
        };
    }
}