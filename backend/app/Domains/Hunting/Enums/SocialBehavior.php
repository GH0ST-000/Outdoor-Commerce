<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SocialBehavior: string
{
    case Solitary = 'solitary';
    case Pair = 'pair';
    case Group = 'group';
    case Mixed = 'mixed';
}
