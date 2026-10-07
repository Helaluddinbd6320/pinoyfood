<?php

namespace App\Support;

final class Sar
{
    /** "28.00" becomes "SAR 28", "28.50" stays "SAR 28.50". No float maths. */
    public static function format(string|int|float|null $amount): string
    {
        $text = (string) $amount;
        $value = preg_match('/^-?\d+(\.\d+)?$/', $text) ? bcadd($text, '0', 2) : '0.00';

        return 'SAR '.(str_ends_with($value, '.00') ? substr($value, 0, -3) : $value);
    }
}