<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShippingMethodRequest;
use App\Models\ShippingMethod;
use Illuminate\Support\Str;

class ShippingMethodController extends Controller
{
    public function index()
    {
        return view('admin.shipping-methods.index', [
            'shippingMethods' => ShippingMethod::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.shipping-methods.form', [
            'shippingMethod' => new ShippingMethod([
                'is_active' => true,
                'delivery_days_min' => 1,
                'delivery_days_max' => 1,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(StoreShippingMethodRequest $request)
    {
        ShippingMethod::query()->create($this->validatedData($request));

        return redirect()->route('admin.shipping-methods.index')->with('status', 'Shipping method created.');
    }

    public function edit(ShippingMethod $shippingMethod)
    {
        return view('admin.shipping-methods.form', [
            'shippingMethod' => $shippingMethod,
        ]);
    }

    public function update(StoreShippingMethodRequest $request, ShippingMethod $shippingMethod)
    {
        $shippingMethod->update($this->validatedData($request));

        return redirect()->route('admin.shipping-methods.edit', $shippingMethod)->with('status', 'Shipping method updated.');
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $shippingMethod->delete();

        return redirect()->route('admin.shipping-methods.index')->with('status', 'Shipping method deleted.');
    }

    private function validatedData(StoreShippingMethodRequest $request): array
    {
        $data = $request->validated();
        $data['code'] = Str::slug($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
