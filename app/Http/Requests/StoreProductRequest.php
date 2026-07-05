<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product?->id;

        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'product_brand_id' => ['nullable', 'exists:product_brands,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'product_model_id' => ['nullable', 'exists:product_models,id'],
            'product_size_id' => ['nullable', 'exists:product_sizes,id'],
            'product_grade_id' => ['nullable', 'exists:product_grades,id'],
            'product_condition_id' => ['required', 'exists:productconditions,id'],
            'product_color_id' => ['nullable', 'exists:product_colors,id'],
            'product_carrier_id' => ['nullable', 'exists:product_carriers,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($productId)],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive,Out of Stock'],
            'product_image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
