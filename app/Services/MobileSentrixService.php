<?php

namespace App\Services;

class MobileSentrixService
{
    public function syncParts(): array
    {
        return [
            'success' => false,
            'message' => 'MobileSentrix sync is not connected yet. Add MOBILESENTRIX_API_KEY and MOBILESENTRIX_API_URL to enable imports.',
        ];
    }
}
