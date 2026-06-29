<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Services\MobileSentrix\MobileSentrixProductEnrichmentService;
use Illuminate\Console\Command;

class EnrichMobileSentrixProductCommand extends Command
{
    protected $signature = 'mobilesentrix:enrich-product {part_id_or_sku : Local MobileSentrix product ID, SKU, or new SKU} {--force : Enrich even when the local cache is fresh}';

    protected $description = 'Fetch MobileSentrix product details, tags, compatibility, images, badges, and related products for one part.';

    public function handle(MobileSentrixProductEnrichmentService $enrichmentService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $identifier = (string) $this->argument('part_id_or_sku');
        $part = $this->findPart($identifier);

        if (! $part) {
            $this->line("Part {$identifier} is not local yet. Refreshing it from MobileSentrix first...");
            $part = $enrichmentService->enrichPartBySku($identifier, true);
        }

        if (! $part) {
            $this->error("No local MobileSentrix part was found for {$identifier}.");

            return self::FAILURE;
        }

        $part = $enrichmentService->enrichPart($part, (bool) $this->option('force'));
        $part->load('warranty')->loadCount(['images', 'tags', 'badges', 'compatibilities', 'relatedParts']);

        $this->info("MobileSentrix part {$part->id} enriched.");
        $this->line('SKU: '.($part->sku ?: 'N/A'));
        $this->line('Name: '.$part->name);
        $this->line('Description updated: '.(filled($part->description) ? 'yes' : 'no'));
        $this->line('Images: '.$part->images_count);
        $this->line('Tags: '.$part->tags_count);
        $this->line('Compatibility rows: '.$part->compatibilities_count);
        $this->line('Badges: '.$part->badges_count);
        $this->line('Warranty detected: '.($part->warranty ? ($part->warranty->display_label ?: 'yes') : 'no'));
        $this->line('Related parts: '.$part->related_parts_count);

        return self::SUCCESS;
    }

    private function findPart(string $identifier): ?Part
    {
        return Part::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('sku', $identifier)
                    ->orWhere('new_sku', $identifier);

                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }
}
