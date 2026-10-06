<?php

namespace App\Enums;

enum OrderSource: string
{
    case Website = 'website';
    case Whatsapp = 'whatsapp';
    case Call = 'call';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Whatsapp => 'WhatsApp',
            self::Call => 'Call',
            self::Admin => 'Admin',
        };
    }
}