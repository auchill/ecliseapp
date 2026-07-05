<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'part_category_id' => ['required', 'exists:part_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120'],
            'new_sku' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'internal_sku' => ['nullable', 'string', 'max:120'],
            'external_api_id' => ['nullable', 'string', 'max:255'],
            'external_api_source' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'device_type' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:120'],
            'model_compatibility' => ['nullable', 'string', 'max:255'],
            'compatibility' => ['nullable', 'json'],
            'specifications' => ['nullable', 'json'],
            'part_category' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'markup_type' => ['nullable', Rule::in(['none', 'fixed', 'percentage'])],
            'markup_value' => ['nullable', 'numeric', 'min:0'],
            'api_price' => ['nullable', 'numeric', 'min:0'],
            'final_price' => ['nullable', 'numeric', 'min:0'],
            'stock_id' => ['nullable', 'string', 'max:120'],
            'is_in_stock' => ['nullable', 'boolean'],
            'in_stock_qty' => ['nullable', 'integer', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'api_quantity' => ['nullable', 'integer', 'min:0'],
            'availability_status' => ['nullable', 'string', 'max:120'],
            'condition' => ['nullable', 'string', 'max:120'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'stock_status' => ['required', 'string', 'max:120'],
            'supplier' => ['required', 'string', 'max:120'],
            'api_status' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'is_api_item' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'part_image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
