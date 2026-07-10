<?php

namespace App\Support;

class MobileSentrixCategoryIds
{
    public static function values(mixed $value): array
    {
        [$ids] = self::parse($value);

        return $ids;
    }

    public static function parse(mixed $value): array
    {
        $invalidCount = 0;
        $ids = self::parseValue($value, $invalidCount);

        return [array_values(array_unique($ids)), $invalidCount];
    }

    public static function missing(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        [$ids] = self::parse($value);

        return $ids === [];
    }

    public static function explicitEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        if (! is_string($value)) {
            return false;
        }

        $decoded = json_decode(trim($value), true);

        return json_last_error() === JSON_ERROR_NONE && $decoded === [];
    }

    private static function parseValue(mixed $value, int &$invalidCount, int $depth = 0): array
    {
        if ($depth > 8 || $value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            $ids = [];

            foreach (self::categoryValuesFromArray($value) as $item) {
                array_push($ids, ...self::parseValue($item, $invalidCount, $depth + 1));
            }

            return $ids;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            $id = ltrim(trim((string) $value), '0');
            $id = $id === '' ? '0' : $id;

            if ($id !== '0' && self::fitsUnsignedBigInteger($id)) {
                return [$id];
            }

            $invalidCount++;

            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && $decoded !== $value) {
                return self::parseValue($decoded, $invalidCount, $depth + 1);
            }

            if (str_contains($value, ',')) {
                $ids = [];

                foreach (explode(',', trim($value, "[] \t\n\r\0\x0B")) as $item) {
                    array_push($ids, ...self::parseValue(trim($item, " \"'"), $invalidCount, $depth + 1));
                }

                return $ids;
            }
        }

        $invalidCount++;

        return [];
    }

    private static function categoryValuesFromArray(array $value): array
    {
        foreach (['category_ids', 'category_id', 'entity_id', 'id', 'value'] as $key) {
            if (array_key_exists($key, $value)) {
                return [$value[$key]];
            }
        }

        return array_values(array_filter($value, fn ($item): bool => filled($item)));
    }

    private static function fitsUnsignedBigInteger(string $id): bool
    {
        $maximum = (string) PHP_INT_MAX;

        return strlen($id) < strlen($maximum)
            || (strlen($id) === strlen($maximum) && strcmp($id, $maximum) <= 0);
    }
}
