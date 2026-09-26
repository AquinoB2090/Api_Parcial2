<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public const MAX = 999999999999;

    public static function cents(mixed $value): int
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new InvalidArgumentException('Envíe el monto como cadena decimal con hasta dos decimales.');
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function minimum(?string $current, string $base): ?string
    {
        $cents = $current === null ? self::cents($base) + 1 : intdiv(self::cents($current) * 110 + 99, 100);

        return $cents > self::MAX ? null : self::format($cents);
    }
}
