<?php

namespace App\Http\Requests;

use App\Models\EcliseMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEcliseMarkupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->input('scope_type') === EcliseMarkup::SCOPE_ALL ? null : $this->input('category_id'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', Rule::in(array_keys(EcliseMarkup::ITEM_TYPES))],
            'scope_type' => ['required', Rule::in(array_keys(EcliseMarkup::SCOPE_TYPES))],
            'category_id' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === EcliseMarkup::SCOPE_CATEGORY),
                'nullable',
                'integer',
                'min:1',
            ],
            'markup_type' => ['required', Rule::in(array_keys(EcliseMarkup::MARKUP_TYPES))],
            'markup_value' => ['required', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_active')) {
                    return;
                }

                $markup = $this->route('ecliseMarkup');
                $duplicate = EcliseMarkup::query()
                    ->whereKeyNot($markup?->id ?: 0)
                    ->where('is_active', true)
                    ->where('item_type', $this->input('item_type'))
                    ->where('scope_type', $this->input('scope_type'))
                    ->when(
                        $this->input('category_id'),
                        fn ($query) => $query->where('category_id', (int) $this->input('category_id')),
                        fn ($query) => $query->whereNull('category_id'),
                    )
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('item_type', 'An active markup rule already exists for this inventory type, scope, and category.');
                }
            },
        ];
    }
}
