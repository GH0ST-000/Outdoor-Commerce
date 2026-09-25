<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

use Illuminate\Support\Str;

final class LegalSlug
{
    public static function from(string $value): string
    {
        $slug = Str::slug($value, '-', 'en');
        if ($slug === '') {
            $slug = 'legal-'.substr(sha1($value), 0, 10);
        }

        return substr($slug, 0, 191);
    }
}
