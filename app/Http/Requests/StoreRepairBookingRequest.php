<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'terms_accepted' => ['accepted'],
        ];
    }
}
