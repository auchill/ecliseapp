<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class CatalogImage
{
    public const FALLBACK_PATH = 'images/brand/eclise-thumb-grey.png';

    public const MOBILESENTRIX_PLACEHOLDER = 'https://static-preprod.mobilesentrix.ca/catalog/product/placeholder/default/mobilesentrix-thumb.jpg';

    public static function fallbackUrl(): string
    {
        return asset(self::FALLBACK_PATH);
    }

    public static function storageUrl(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return self::fallbackUrl();
        }

        return Storage::disk('public')->url($path);
    }

    public static function remoteUrl(?string $url): string
    {
        $url = trim((string) $url);

        if (
            $url === ''
            || strcasecmp($url, self::MOBILESENTRIX_PLACEHOLDER) === 0
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
        ) {
            return self::fallbackUrl();
        }

        return $url;
    }

    public static function displayUrl(?string $url): string
    {
        $url = trim((string) $url);

        if (str_starts_with($url, '/')) {
            $path = ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');

            return $path !== '' && file_exists(public_path($path))
                ? asset($path)
                : self::fallbackUrl();
        }

        return self::remoteUrl($url);
    }
}
