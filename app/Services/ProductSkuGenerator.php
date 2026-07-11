<?php

namespace App\Services;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSkuGenerator
{
    public function next(?ProductCategory $category): string
    {
        $code = $this->codeFor($category);

        return DB::transaction(function () use ($code): string {
            $row = DB::table('product_sku_sequences')->where('id', 1)->lockForUpdate()->first();

            if (! $row) {
                DB::table('product_sku_sequences')->insert([
                    'id' => 1,
                    'last_sequence' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('product_sku_sequences')->where('id', 1)->lockForUpdate()->first();
            }

            $sequence = ((int) $row->last_sequence) + 1;

            DB::table('product_sku_sequences')->where('id', 1)->update([
                'last_sequence' => $sequence,
                'updated_at' => now(),
            ]);

            return sprintf('ECL-SHP-%s-%s', $code, str_pad((string) $sequence, 7, '0', STR_PAD_LEFT));
        });
    }

    private function codeFor(?ProductCategory $category): string
    {
        $code = strtoupper((string) ($category?->code ?: ''));

        if (preg_match('/^[A-Z]{3}$/', $code)) {
            return $code;
        }

        Log::warning('Product category is missing a valid SKU code; using OTH.', [
            'product_category_id' => $category?->id,
            'product_category_name' => $category?->name,
        ]);

        return 'OTH';
    }
}
