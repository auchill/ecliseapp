<?php

namespace App\Services;

use App\Models\ShippingDiscountRule;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ShippingCostService
{
    public const METHOD_PICKUP = 'pickup';

    public const METHOD_SHIPPING = 'shipping';

    public function getAvailableShippingMethods(): Collection
    {
        return ShippingMethod::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function calculateForFulfillment(string $fulfillmentMethod, float $subtotal, ?int $shippingMethodId): array
    {
        if ($fulfillmentMethod === self::METHOD_PICKUP) {
            return $this->pickupResult();
        }

        if (! $shippingMethodId) {
            throw new InvalidArgumentException('A shipping method is required for shipping fulfillment.');
        }

        return $this->calculateForShopOrder($subtotal, $shippingMethodId);
    }

    public function calculateForShopOrder(float $subtotal, int $shippingMethodId): array
    {
        return $this->calculate($subtotal, $shippingMethodId);
    }

    public function calculateForRepairOrder(float $repairSubtotal, int $shippingMethodId): array
    {
        return $this->calculate($repairSubtotal, $shippingMethodId);
    }

    public function applyBestDiscount(float $subtotal, ShippingMethod $shippingMethod): array
    {
        $baseCost = (float) $shippingMethod->base_cost;
        $rules = ShippingDiscountRule::query()
            ->currentlyActive()
            ->where('minimum_order_amount', '<=', $subtotal)
            ->where(function ($query) use ($shippingMethod): void {
                $query->whereNull('shipping_method_id')
                    ->orWhere('shipping_method_id', $shippingMethod->id);
            })
            ->get();

        $bestRule = null;
        $bestDiscount = 0.0;

        foreach ($rules as $rule) {
            $discount = $this->discountAmount($rule, $baseCost);

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestRule = $rule;
            }
        }

        return [
            'discount_amount' => min($baseCost, round($bestDiscount, 2)),
            'discount_rule' => $bestRule,
        ];
    }

    private function calculate(float $subtotal, int $shippingMethodId): array
    {
        $shippingMethod = ShippingMethod::query()
            ->active()
            ->find($shippingMethodId);

        if (! $shippingMethod) {
            throw new InvalidArgumentException('The selected shipping method is not available.');
        }

        $baseCost = (float) $shippingMethod->base_cost;
        $discount = $this->applyBestDiscount($subtotal, $shippingMethod);
        $discountAmount = (float) $discount['discount_amount'];

        return [
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => $shippingMethod->name,
            'shipping_delivery_days' => $shippingMethod->deliveryDaysLabel(),
            'shipping_base_cost' => round($baseCost, 2),
            'shipping_discount_amount' => $discountAmount,
            'shipping_cost' => max(0, round($baseCost - $discountAmount, 2)),
            'shipping_discount_rule_name' => $discount['discount_rule']?->name,
        ];
    }

    private function pickupResult(): array
    {
        return [
            'shipping_method_id' => null,
            'shipping_method_name' => null,
            'shipping_delivery_days' => null,
            'shipping_base_cost' => 0.00,
            'shipping_discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'shipping_discount_rule_name' => null,
        ];
    }

    private function discountAmount(ShippingDiscountRule $rule, float $baseCost): float
    {
        return match ($rule->discount_type) {
            'free_shipping' => $baseCost,
            'percentage' => $baseCost * min(100, (float) $rule->discount_value) / 100,
            'fixed' => (float) $rule->discount_value,
            default => 0,
        };
    }
}
