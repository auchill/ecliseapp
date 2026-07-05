<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\PartCategoryPivotService;
use Illuminate\Console\Command;

class GeneratePartCategoryPivotCommand extends Command
{
    protected $signature = 'mobilesentrix:generate-part-category-pivot
        {--chunk=500 : Number of parts processed per database chunk}';

    protected $description = 'Generate part category assignments from parts.category_ids.';

    public function handle(PartCategoryPivotService $pivotService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $result = $pivotService->generate(max(1, (int) $this->option('chunk')));

        $this->info($result['message']);

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
