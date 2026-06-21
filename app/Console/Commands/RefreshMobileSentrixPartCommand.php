<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Console\Command;

class RefreshMobileSentrixPartCommand extends Command
{
    protected $signature = 'mobilesentrix:refresh-part {sku : MobileSentrix SKU, new SKU, or product ID}';

    protected $description = 'Refresh one MobileSentrix part by SKU, new SKU, or product ID.';

    public function handle(MobileSentrixSyncService $syncService): int
    {
        $result = $syncService->refreshPart($this->argument('sku'));

        $this->info($result['message'] ?? 'MobileSentrix part refresh finished.');

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
