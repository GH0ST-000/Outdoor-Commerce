<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Carbon\CarbonImmutable;

/**
 * Controllable clock for time-sensitive domain logic (pricing schedules, etc.).
 */
interface Clock
{
    public function now(): CarbonImmutable;
}
