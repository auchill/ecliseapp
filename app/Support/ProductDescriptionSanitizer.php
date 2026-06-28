<?php

namespace App\Support;

class ProductDescriptionSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><span><ul><ol><li><table><thead><tbody><tr><td><th>';

    public static function sanitize(?string $html): string
    {
        if (! filled($html)) {
            return '';
        }

        $clean = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/\s+(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]*(\2)?/i', '', $clean) ?? '';
        $clean = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';

        return trim(strip_tags($clean, self::ALLOWED_TAGS));
    }
}
