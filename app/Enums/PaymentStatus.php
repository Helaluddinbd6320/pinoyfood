<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case AwaitingProof = 'awaiting_proof';
    case AwaitingVerification = 'awaiting_verification';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingProof => 'Awaiting screenshot',
            self::AwaitingVerification => 'Awaiting verification',
            self::Paid => 'Paid (verified)',
            self::Rejected => 'Rejected',
            self::Refunded => 'Refunded',
        };
    }
}