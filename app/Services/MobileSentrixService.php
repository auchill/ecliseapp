<?php

namespace App\Services;

use App\Services\MobileSentrix\MobileSentrixSyncService;

class MobileSentrixService
{
    public function __construct(private readonly MobileSentrixSyncService $syncService) {}

    public function syncParts(?string $categoryId = null): array
    {
        return $this->syncService->syncParts($categoryId);
    }
}
