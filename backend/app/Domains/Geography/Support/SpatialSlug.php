<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use Illuminate\Support\Str;

final class SpatialSlug
{
    public static function from(string $value): string
    {
        $slug = Str::slug($value, '-', 'en');
        if ($slug === '') {
            $slug = 'spatial-'.substr(sha1($value), 0, 10);
        }

        return substr($slug, 0, 191);
    }
}
