<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Services\MobileSentrix\MobileSentrixProductEnrichmentService;
use Illuminate\Console\Command;

class EnrichMissingMobileSentrixProductsCommand extends Command
{
    protected $signature = 'mobilesentrix:enrich-missing-products {--limit=50 : Maximum number of products to enrich} {--force : Enrich even when cache is fresh}';

    protected $description = 'Enrich MobileSentrix parts missing descriptions, gallery images, tags, compatibility, badges, warranty, or related products.';

    public function handle(MobileSentrixProductEnrichmentService $enrichmentService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;
        $failed = 0;

        $parts = Part::query()
            ->where('is_api_item', true)
            ->where(function ($query): void {
                $query->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhereNull('part_warranty_id')
                    ->orWhereDoesntHave('images')
                    ->orWhereDoesntHave('tags')
                    ->orWhereDoesntHave('compatibilities')
                    ->orWhereDoesntHave('badges')
                    ->orWhereDoesntHave('relatedParts');
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        foreach ($parts as $part) {
            try {
                $enrichmentService->enrichPart($part, (bool) $this->option('force'));
                $processed++;
                $this->line("Enriched part {$part->id} ({$part->sku}).");
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Failed part {$part->id}: ".$exception->getMessage());
            }
        }

        $this->info("MobileSentrix missing product enrichment finished. Processed: {$processed}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
