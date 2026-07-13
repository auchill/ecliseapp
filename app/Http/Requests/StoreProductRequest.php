<?php

namespace App\Http\Requests;

use App\Models\ProductModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'product_brand_id' => ['nullable', 'exists:product_brands,id'],
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'product_model_id' => ['nullable', 'exists:product_models,id'],
            'product_size_ids' => ['nullable', 'array'],
            'product_size_ids.*' => ['integer', 'exists:product_sizes,id'],
            'product_grade_id' => ['nullable', 'exists:product_grades,id'],
            'product_condition_id' => ['nullable', 'exists:product_conditions,id'],
            'product_color_id' => ['nullable', 'exists:product_colors,id'],
            'product_network_id' => ['nullable', 'exists:product_networks,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($productId)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'product_images' => ['nullable', 'array'],
            'product_images.*' => ['image', 'max:4096'],
            'primary_image_id' => ['nullable', 'integer', 'exists:product_images,id'],
            'delete_image_ids' => ['nullable', 'array'],
            'delete_image_ids.*' => ['integer', 'exists:product_images,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('product_model_id')) {
                    $model = ProductModel::query()->find($this->integer('product_model_id'));

                    if ($model) {
                        if (! $this->filled('product_brand_id')) {
                            $validator->errors()->add('product_brand_id', 'Choose the product brand for the selected model.');
                        }

                        if ($model->product_brand_id && $model->product_brand_id !== $this->integer('product_brand_id')) {
                            $validator->errors()->add('product_model_id', 'The selected product model does not belong to the selected brand.');
                        }
                    }
                }

                if ($this->filled('sale_price') && (float) $this->input('sale_price') > (float) $this->input('regular_price')) {
                    $validator->errors()->add('sale_price', 'The sale price must be less than or equal to the regular price.');
                }

                $product = $this->route('product');

                if (! $product) {
                    return;
                }

                if ($this->filled('primary_image_id') && ! $product->images()->whereKey($this->integer('primary_image_id'))->exists()) {
                    $validator->errors()->add('primary_image_id', 'The selected primary image does not belong to this product.');
                }

                $deleteImageIds = collect($this->input('delete_image_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->values();

                if ($deleteImageIds->isEmpty()) {
                    return;
                }

                $ownedCount = $product->images()->whereIn('id', $deleteImageIds)->count();

                if ($ownedCount !== $deleteImageIds->count()) {
                    $validator->errors()->add('delete_image_ids', 'One or more selected images do not belong to this product.');
                }
            },
        ];
    }
}
