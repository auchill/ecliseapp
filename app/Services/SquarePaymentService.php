<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Str;

class SquarePaymentService
{
    public function createCheckout(Cart $cart, array $customer): array
    {
        return [
            'success' => true,
            'provider' => 'square',
            'reference' => 'sq-placeholder-'.Str::uuid()->toString(),
            'environment' => config('services.square.environment'),
            'message' => 'Square payment placeholder approved. Add API credentials to enable live payment collection.',
        ];
    }
}
