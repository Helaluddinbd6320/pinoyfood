<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Rider = 'rider';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}