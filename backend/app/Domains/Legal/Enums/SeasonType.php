<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum SeasonType: string
{
    case Opening = 'opening';
    case Closure = 'closure';
    case SpecialOpening = 'special_opening';
    case SpecialClosure = 'special_closure';
    case Restriction = 'restriction';

    public function isPermission(): bool
    {
        return $this === self::Opening || $this === self::SpecialOpening;
    }

    public function isProhibition(): bool
    {
        return $this === self::Closure || $this === self::SpecialClosure || $this === self::Restriction;
    }
}
