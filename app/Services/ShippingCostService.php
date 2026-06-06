<?php

namespace App\Services;

class ShippingCostService
{
    public const METHOD_PICKUP = 'pickup';

    public const METHOD_SHIPPING = 'shipping';

    public function calculate(string $fulfillmentMethod, ?string $country = null): float
    {
        if ($fulfillmentMethod === self::METHOD_PICKUP) {
            return 0.00;
        }

        return $this->isCanada($country) ? 20.00 : 45.00;
    }

    private function isCanada(?string $country): bool
    {
        $normalized = strtolower(trim((string) $country));

        return in_array($normalized, ['canada', 'ca', 'can'], true);
    }
}
