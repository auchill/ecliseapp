<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() === true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'payment_gateway' => ['required', Rule::in(['stripe', 'paypal'])],
            'fulfillment_method' => ['required', 'in:pickup,shipping'],
            'shipping_method_id' => [
                'required_if:fulfillment_method,shipping',
                'nullable',
                Rule::exists('shipping_methods', 'id')->where('is_active', true),
            ],
            'recipient_name' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'recipient_email' => ['required_if:fulfillment_method,shipping', 'nullable', 'email', 'max:255'],
            'address_line1' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'province' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'postal_code' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'country' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'delivery_carrier' => ['nullable', 'string', 'max:120'],
            'carrier_tracking_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
