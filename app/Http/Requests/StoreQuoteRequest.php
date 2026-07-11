<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() === true;
    }

    public function rules(): array
    {
        return [
            'device_type_id' => ['required', 'exists:repair_device_types,id'],
            'product_brand_id' => ['required', 'exists:product_brands,id'],
            'product_model_id' => ['nullable', 'exists:product_models,id'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'issue_category_id' => ['required', 'exists:issue_categories,id'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'device_image' => ['nullable', 'image', 'max:4096'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'customer_id' => ['prohibited'],
            'customer_name' => ['prohibited'],
            'email' => ['prohibited'],
            'phone_number' => ['prohibited'],
        ];
    }
}
