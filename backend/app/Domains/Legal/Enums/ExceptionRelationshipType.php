<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum ExceptionRelationshipType: string
{
    case ExceptionTo = 'exception_to';
    case Overrides = 'overrides';
    case Narrows = 'narrows';
    case Expands = 'expands';
    case Supersedes = 'supersedes';
}
