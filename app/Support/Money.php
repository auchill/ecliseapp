<?php

namespace App\Support;

class Money
{
    public static function format(mixed $amount, string $currency = 'cad'): string
    {
        return strtoupper($currency).' '.number_format((float) $amount, 2);
    }
}
