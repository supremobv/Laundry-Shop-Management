<?php

namespace App\Support;

final class Money
{
    /** Convert a validated decimal string into minor units without floating point arithmetic. */
    public static function cents(string|int|float $value): int
    {
        $parts = explode('.', (string) $value, 2);

        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function line(int $price, string $quantity): int
    {
        return intdiv($price * self::cents($quantity) + 50, 100);
    }
}
