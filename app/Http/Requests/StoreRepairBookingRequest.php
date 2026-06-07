<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'device_type' => ['required', 'in:Phone,Laptop,Desktop,Tablet,Other'],
            'device_brand' => ['nullable', 'string', 'max:120'],
            'device_model' => ['nullable', 'string', 'max:120'],
            'issue_category' => ['required', 'string', 'max:160'],
            'issue_description' => ['required', 'string', 'min:10'],
            'preferred_appointment_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_appointment_time' => ['nullable', 'date_format:H:i'],
            'device_image' => ['nullable', 'image', 'max:4096'],
            'payment_gateway' => ['nullable', Rule::in(['stripe', 'paypal'])],
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
            'terms_accepted' => ['accepted'],
        ];
    }
}
