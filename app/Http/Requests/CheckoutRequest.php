<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'payment_gateway' => ['required', Rule::in(['stripe', 'paypal'])],
            'fulfillment_method' => ['required', 'in:pickup,shipping'],
            'shipping_method_id' => [
                'required_if:fulfillment_method,shipping',
                'nullable',
                Rule::exists('shipping_methods', 'id')->where('is_active', true),
            ],
            'shipping_full_name' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'shipping_phone' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'shipping_email' => ['required_if:fulfillment_method,shipping', 'nullable', 'email', 'max:255'],
            'shipping_address_line1' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'shipping_province' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'shipping_postal_code' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'shipping_country' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'delivery_carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
