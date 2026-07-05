<?php

namespace App\Models;

use Illuminate\Support\Str;

final class PartBadge
{
    public static function displayName(mixed $value): ?string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return null;
        }

        foreach (['Basic', 'Pro', 'Core', 'Genuine', 'AmpSentrix'] as $knownName) {
            if (Str::contains(Str::lower($name), Str::lower($knownName))) {
                return $knownName;
            }
        }

        return $name;
    }

    public static function displayIconUrl(mixed $name, ?string $apiUrl = null): ?string
    {
        $name = Str::lower((string) self::displayName($name));
        $filename = match (true) {
            str_contains($name, 'basic') => 'badge-basic.svg',
            str_contains($name, 'pro') => 'badge-pro.svg',
            str_contains($name, 'core') => 'badge-core.svg',
            str_contains($name, 'genuine') => 'badge-genuine.svg',
            str_contains($name, 'amp') => 'badge-ampsentrix.svg',
            default => 'badge-default.svg',
        };

        $path = 'images/parts/badges/'.$filename;

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return filled($apiUrl) ? $apiUrl : null;
    }
}
