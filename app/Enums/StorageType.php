<?php

namespace App\Enums;

enum StorageType: string
{
    case Frozen = 'frozen';
    case Chilled = 'chilled';
    case Ambient = 'ambient';

    public function label(): string
    {
        return match ($this) {
            self::Frozen => 'Frozen',
            self::Chilled => 'Chilled',
            self::Ambient => 'Ambient',
        };
    }
}