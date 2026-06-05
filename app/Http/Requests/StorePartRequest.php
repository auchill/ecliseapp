<?php

namespace App\Http\Requests;

use App\Models\Part;
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
            'name' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:120'],
            'model_compatibility' => ['nullable', 'string', 'max:255'],
            'part_category' => ['required', Rule::in(Part::CATEGORIES)],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_status' => ['required', 'string', 'max:120'],
            'supplier' => ['required', 'string', 'max:120'],
            'part_image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
