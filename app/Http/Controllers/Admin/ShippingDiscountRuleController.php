<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShippingDiscountRuleRequest;
use App\Models\ShippingDiscountRule;
use App\Models\ShippingMethod;

class ShippingDiscountRuleController extends Controller
{
    public function index()
    {
        return view('admin.shipping-discounts.index', [
            'discountRules' => ShippingDiscountRule::query()
                ->with('shippingMethod')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.shipping-discounts.form', [
            'discountRule' => new ShippingDiscountRule([
                'is_active' => true,
                'discount_type' => 'fixed',
                'minimum_order_amount' => 0,
            ]),
            'shippingMethods' => ShippingMethod::query()->orderBy('name')->get(),
            'discountTypes' => ShippingDiscountRule::TYPES,
        ]);
    }

    public function store(StoreShippingDiscountRuleRequest $request)
    {
        ShippingDiscountRule::query()->create($this->validatedData($request));

        return redirect()->route('admin.shipping-discounts.index')->with('status', 'Shipping discount created.');
    }

    public function edit(ShippingDiscountRule $shippingDiscount)
    {
        return view('admin.shipping-discounts.form', [
            'discountRule' => $shippingDiscount,
            'shippingMethods' => ShippingMethod::query()->orderBy('name')->get(),
            'discountTypes' => ShippingDiscountRule::TYPES,
        ]);
    }

    public function update(StoreShippingDiscountRuleRequest $request, ShippingDiscountRule $shippingDiscount)
    {
        $shippingDiscount->update($this->validatedData($request));

        return redirect()->route('admin.shipping-discounts.edit', $shippingDiscount)->with('status', 'Shipping discount updated.');
    }

    public function destroy(ShippingDiscountRule $shippingDiscount)
    {
        $shippingDiscount->delete();

        return redirect()->route('admin.shipping-discounts.index')->with('status', 'Shipping discount deleted.');
    }

    private function validatedData(StoreShippingDiscountRuleRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        if ($data['discount_type'] === 'free_shipping') {
            $data['discount_value'] = null;
        }

        return $data;
    }
}
