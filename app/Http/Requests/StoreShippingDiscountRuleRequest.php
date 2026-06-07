<?php

namespace App\Http\Requests;

use App\Models\ShippingDiscountRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingDiscountRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'minimum_order_amount' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['required', Rule::in(array_keys(ShippingDiscountRule::TYPES))],
            'discount_value' => [
                'nullable',
                'required_if:discount_type,fixed,percentage',
                'numeric',
                'min:0',
                Rule::when($this->input('discount_type') === 'percentage', ['max:100']),
            ],
            'shipping_method_id' => ['nullable', 'exists:shipping_methods,id'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
