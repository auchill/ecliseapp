<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class InspectMobileSentrixSchemaCommand extends Command
{
    protected $signature = 'mobilesentrix:inspect-schema
        {--category= : Category ID to inspect with /categories/:id}
        {--product= : Product entity_id to inspect with /products/:id}
        {--sku= : SKU to inspect with /tags}
        {--limit=10 : Product sample size for list endpoints}';

    protected $description = 'Inspect live MobileSentrix API response keys and value types.';

    public function handle(MobileSentrixClient $client): int
    {
        $limit = max(1, min((int) $this->option('limit'), 50));

        $rootCategoryRecords = $this->inspect('GET /categories', fn () => $client->categories());
        $categoryId = $this->option('category') ?: data_get($rootCategoryRecords->first(), 'entity_id');

        if ($categoryId) {
            $this->inspect("GET /category/{$categoryId}", fn () => $client->category($categoryId));
        }

        $productRecords = $this->inspect('GET /products', fn () => $client->products([
            'limit' => $limit,
            'page' => 1,
            'pageinfo' => 1,
        ]));
        $productId = $this->option('product') ?: data_get($productRecords->first(), 'entity_id');
        $sku = $this->option('sku') ?: data_get($productRecords->first(), 'sku');

        if ($productId) {
            $this->inspect("GET /products/{$productId}?load=image_gallery,related_product", fn () => $client->product($productId, [
                'load' => 'image_gallery,related_product',
            ]));
        }

        if ($categoryId) {
            $this->inspect("GET /products?category_id={$categoryId}", fn () => $client->products([
                'category_id' => $categoryId,
                'limit' => $limit,
                'page' => 1,
                'pageinfo' => 1,
            ]));
        }

        $this->inspect('GET /products?product_type=devicesystem', fn () => $client->products([
            'limit' => $limit,
            'page' => 1,
            'pageinfo' => 1,
            'product_type' => 'devicesystem',
        ]));

        if ($sku) {
            $this->inspect("GET /tags filtered by SKU {$sku}", fn () => $client->tagsForSkus([$sku, $sku]));
        }

        return self::SUCCESS;
    }

    private function inspect(string $label, callable $fetch): Collection
    {
        $this->newLine();
        $this->info($label);

        try {
            $payload = $fetch();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return collect();
        }

        $records = $this->records($payload);
        $summary = $this->summarize($records);

        $this->line('Records sampled: '.$records->count());
        $this->table(['field', 'types', 'nullable', 'present', 'array/object'], $summary);

        return $records;
    }

    private function records(array $payload): Collection
    {
        if (isset($payload['data']['items']) && is_array($payload['data']['items'])) {
            return collect($payload['data']['items']);
        }

        if (isset($payload['data']) && is_array($payload['data']) && ! $this->looksLikeRecord($payload['data'])) {
            return collect($payload['data'])
                ->filter(fn ($value, $key): bool => $key !== 'page_info' && is_array($value))
                ->values();
        }

        if ($this->looksLikeRecord($payload)) {
            return collect([$payload]);
        }

        return collect($payload)
            ->filter(fn ($value, $key): bool => $key !== 'page_info' && is_array($value))
            ->values();
    }

    private function summarize(Collection $records): array
    {
        $fields = $records
            ->flatMap(fn (array $record): array => array_keys($record))
            ->unique()
            ->sort()
            ->values();

        return $fields
            ->map(function (string $field) use ($records): array {
                $values = $records
                    ->filter(fn (array $record): bool => array_key_exists($field, $record))
                    ->map(fn (array $record) => $record[$field]);

                $types = $values
                    ->map(fn ($value): string => $this->typeName($value))
                    ->unique()
                    ->sort()
                    ->implode(', ');

                $hasStructured = $values->contains(fn ($value): bool => is_array($value) || is_object($value));
                $nullable = $values->contains(fn ($value): bool => $value === null || $value === '');

                return [
                    'field' => $field,
                    'types' => $types,
                    'nullable' => $nullable ? 'yes' : 'no',
                    'present' => $values->count().'/'.$records->count(),
                    'array/object' => $hasStructured ? 'yes' : 'no',
                ];
            })
            ->all();
    }

    private function typeName(mixed $value): string
    {
        return match (true) {
            is_array($value) => $this->looksSequential($value) ? 'array' : 'object',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            $value === null => 'null',
            default => gettype($value),
        };
    }

    private function looksLikeRecord(array $payload): bool
    {
        return isset($payload['entity_id']) || isset($payload['product_id']) || isset($payload['sku']) || isset($payload['category_id']);
    }

    private function looksSequential(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
