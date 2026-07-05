<?php

namespace App\Support;

use App\Models\MobileSentrixDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class MobileSentrixDeviceFilters
{
    public const FILTER_FIELDS = [
        'manufacturer_text' => 'Make',
        'device_model_text' => 'Model',
        'device_size_text' => 'Size',
        'device_color_text' => 'Color',
        'condition_text' => 'Condition',
        'device_carrier_text' => 'Carrier',
        'device_grade_text' => 'Grade',
    ];

    public function query(Request $request, bool $customer = false): Builder
    {
        $query = MobileSentrixDevice::query();

        if ($customer) {
            $query->available();
        } elseif ($request->query('availability') === 'in_stock') {
            $query->available();
        } elseif ($request->query('availability') === 'out_of_stock') {
            $query->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('available_qty')->where('available_qty', '<=', 0);
                })->orWhere(function (Builder $query): void {
                    $query->whereNull('available_qty')->where(function (Builder $query): void {
                        $query->whereNull('qty')->orWhere('qty', '<=', 0);
                    });
                });
            });
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                foreach (array_merge(['entity_id', 'sku', 'name'], array_keys(self::FILTER_FIELDS)) as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        foreach (array_keys(self::FILTER_FIELDS) as $field) {
            $values = $this->filterValues($request, $field);

            if ($values !== []) {
                $query->whereIn($field, $values);
            }
        }

        if (is_numeric($request->query('price_min'))) {
            $query->whereRaw('CAST(COALESCE(price, final_price, regular_price) AS DECIMAL(12,2)) >= ?', [(float) $request->query('price_min')]);
        }

        if (is_numeric($request->query('price_max'))) {
            $query->whereRaw('CAST(COALESCE(price, final_price, regular_price) AS DECIMAL(12,2)) <= ?', [(float) $request->query('price_max')]);
        }

        return $this->sort($query, (string) $request->query('price_sort', ''));
    }

    public function filterOptions(bool $customer = false): array
    {
        $baseQuery = MobileSentrixDevice::query();

        if ($customer) {
            $baseQuery->available();
        }

        return collect(self::FILTER_FIELDS)
            ->mapWithKeys(function (string $label, string $field) use ($baseQuery): array {
                $values = (clone $baseQuery)
                    ->selectRaw($field.' as value, count(*) as count')
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->groupBy($field)
                    ->orderBy($field)
                    ->limit(500)
                    ->get()
                    ->map(fn ($row): array => [
                        'value' => (string) $row->value,
                        'count' => (int) $row->count,
                    ])
                    ->all();

                return [$field => ['label' => $label, 'values' => $values]];
            })
            ->all();
    }

    public function selectedChips(Request $request): array
    {
        $chips = [];

        foreach (self::FILTER_FIELDS as $field => $label) {
            foreach ($this->filterValues($request, $field) as $value) {
                $chips[] = ['type' => 'column', 'field' => $field, 'label' => $label, 'value' => $value];
            }
        }

        foreach (['price_min' => 'Min price', 'price_max' => 'Max price'] as $field => $label) {
            if (filled($request->query($field))) {
                $chips[] = ['type' => $field, 'field' => $field, 'label' => $label, 'value' => $request->query($field)];
            }
        }

        if (filled($request->query('availability'))) {
            $chips[] = [
                'type' => 'scalar',
                'field' => 'availability',
                'label' => 'Availability',
                'value' => $request->query('availability') === 'out_of_stock' ? 'Out of stock' : 'In stock',
            ];
        }

        return $chips;
    }

    public function selectedFilterGroups(Request $request): array
    {
        return collect($this->selectedChips($request))
            ->groupBy('field')
            ->map(function (Collection $chips): array {
                $first = $chips->first();

                return [
                    'field' => $first['field'],
                    'label' => $first['label'],
                    'values' => $chips
                        ->map(fn (array $chip): array => [
                            'type' => $chip['type'],
                            'value' => $chip['value'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    public function csvRows(Builder $query): Collection
    {
        return $query->get()->map(fn (MobileSentrixDevice $device): array => [
            'Make' => $device->manufacturer_text,
            'Model' => $device->device_model_text,
            'Size' => $device->device_size_text,
            'Color' => $device->device_color_text,
            'Condition' => $device->condition_text,
            'Carrier' => $device->device_carrier_text,
            'Available Qty' => $device->availableQuantity(),
            'Price' => $device->displayPrice(),
            'SKU' => $device->sku,
            'Entity ID' => $device->entity_id,
            'Synced At' => $device->synced_at?->toDateTimeString(),
        ]);
    }

    private function filterValues(Request $request, string $field): array
    {
        return collect(Arr::wrap($request->query($field, [])))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function sort(Builder $query, string $sort): Builder
    {
        $direction = $sort === 'price_desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw('COALESCE(price, final_price, regular_price) IS NULL')
            ->orderByRaw("COALESCE(price, final_price, regular_price) {$direction}")
            ->orderBy('manufacturer_text')
            ->orderBy('device_model_text')
            ->orderBy('id');
    }
}
