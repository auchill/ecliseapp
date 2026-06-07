<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $shippingMethod = $this->route('shipping_method');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('shipping_methods', 'code')->ignore($shippingMethod?->id)],
            'description' => ['nullable', 'string'],
            'base_cost' => ['required', 'numeric', 'min:0'],
            'delivery_days_min' => ['required', 'integer', 'min:1'],
            'delivery_days_max' => ['required', 'integer', 'min:1', 'gte:delivery_days_min'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
