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
        $scope = $this->input('scope_type');

        $this->merge([
            'category_id' => null,
            'brand_text' => $scope === EcliseMarkup::SCOPE_BRAND ? trim((string) $this->input('brand_text')) : null,
            'brand_normalized' => $scope === EcliseMarkup::SCOPE_BRAND ? EcliseMarkup::normalizeBrand($this->input('brand_text')) : null,
            'min_price' => $scope === EcliseMarkup::SCOPE_PRICE_RANGE ? $this->input('min_price') : null,
            'max_price' => $scope === EcliseMarkup::SCOPE_PRICE_RANGE ? $this->input('max_price') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', Rule::in(array_keys(EcliseMarkup::ITEM_TYPES))],
            'scope_type' => ['required', Rule::in(array_keys(EcliseMarkup::SCOPE_TYPES))],
            'category_id' => ['nullable'],
            'brand_text' => [Rule::requiredIf(fn (): bool => $this->input('scope_type') === EcliseMarkup::SCOPE_BRAND), 'nullable', 'string', 'max:255'],
            'brand_normalized' => ['nullable', 'string', 'max:255'],
            'min_price' => [Rule::requiredIf(fn (): bool => $this->input('scope_type') === EcliseMarkup::SCOPE_PRICE_RANGE), 'nullable', 'numeric', 'min:0'],
            'max_price' => [Rule::requiredIf(fn (): bool => $this->input('scope_type') === EcliseMarkup::SCOPE_PRICE_RANGE), 'nullable', 'numeric', 'min:0', 'gte:min_price'],
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
                $query = EcliseMarkup::query()
                    ->whereKeyNot($markup?->id ?: 0)
                    ->where('is_active', true)
                    ->where('item_type', $this->input('item_type'))
                    ->where('scope_type', $this->input('scope_type'));

                $duplicate = match ($this->input('scope_type')) {
                    EcliseMarkup::SCOPE_ALL => $query->exists(),
                    EcliseMarkup::SCOPE_BRAND => $query->where('brand_normalized', $this->input('brand_normalized'))->exists(),
                    default => false,
                };

                if ($duplicate) {
                    $validator->errors()->add('item_type', 'An active markup rule already exists for this source and condition.');
                }

                if ($this->input('scope_type') !== EcliseMarkup::SCOPE_PRICE_RANGE) {
                    return;
                }

                $overlap = EcliseMarkup::query()
                    ->whereKeyNot($markup?->id ?: 0)
                    ->where('is_active', true)
                    ->where('item_type', $this->input('item_type'))
                    ->where('scope_type', EcliseMarkup::SCOPE_PRICE_RANGE)
                    ->where('min_price', '<=', (float) $this->input('max_price'))
                    ->where('max_price', '>=', (float) $this->input('min_price'))
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('min_price', 'This active price range overlaps another active range for the selected source.');
                }
            },
        ];
    }
}
