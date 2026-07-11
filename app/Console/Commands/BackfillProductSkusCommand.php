<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSkuGenerator;
use Illuminate\Console\Command;

class BackfillProductSkusCommand extends Command
{
    protected $signature = 'products:backfill-skus {--chunk=100 : Number of products to process per chunk}';

    protected $description = 'Backfill missing Eclise shop product SKUs using the concurrency-safe SKU sequence.';

    public function handle(ProductSkuGenerator $skuGenerator): int
    {
        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $chunk = max(1, min((int) $this->option('chunk'), 1000));

        Product::query()
            ->with('productCategory')
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use ($skuGenerator, &$generated, &$skipped, &$failed): void {
                foreach ($products as $product) {
                    if (filled($product->sku)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $product->forceFill([
                            'sku' => $skuGenerator->next($product->productCategory),
                        ])->save();
                        $generated++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        $this->error("Product {$product->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Product SKU backfill complete. Generated: {$generated}, skipped: {$skipped}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
