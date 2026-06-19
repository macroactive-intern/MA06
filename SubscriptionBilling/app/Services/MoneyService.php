<?php

declare(strict_types=1);

namespace App\Services;

class MoneyService
{
    public static function toCents(string|float|int $price): int
    {
        $str = (string) $price;

        if (str_contains($str, '.')) {
            [$dollars, $cents] = explode('.', $str, 2);
            $cents = str_pad(substr($cents, 0, 2), 2, '0');
            return (int) $dollars * 100 + (int) $cents;
        }

        return (int) $str * 100;
    }
}
