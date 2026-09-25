<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Models\Habitat;

final class HabitatCodeLookup
{
    public static function code(int $habitatId): ?string
    {
        if ($habitatId < 1) {
            return null;
        }

        $code = Habitat::query()->whereKey($habitatId)->value('code');

        return is_string($code) ? $code : null;
    }
}
