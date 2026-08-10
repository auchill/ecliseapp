<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function format(mixed $amount, string $currency = 'cad'): string
    {
        return strtoupper($currency).' '.number_format((float) $amount, 2);
    }

    public static function toMinorUnits(mixed $amount, int $scale = 2): int
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Money scale cannot be negative.');
        }

        $normalized = preg_replace('/[,\s]/', '', trim((string) $amount));

        if ($normalized === '' || ! preg_match('/^[+-]?\d*(\.\d*)?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = $whole === '' ? '0' : $whole;
        $multiplier = 10 ** $scale;
        $fraction = str_pad($fraction, $scale + 1, '0');
        $roundingDigit = (int) substr($fraction, $scale, 1);
        $minor = ((int) $whole * $multiplier) + (int) substr($fraction, 0, $scale);

        if ($roundingDigit >= 5) {
            $minor++;
        }

        return $negative ? -$minor : $minor;
    }

    public static function fromMinorUnits(mixed $amount, int $scale = 2): float
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Money scale cannot be negative.');
        }

        return round(((int) $amount) / (10 ** $scale), $scale);
    }
}
