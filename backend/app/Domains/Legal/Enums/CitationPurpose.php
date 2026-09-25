<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum CitationPurpose: string
{
    case Authority = 'authority';
    case Definition = 'definition';
    case Condition = 'condition';
    case Exception = 'exception';
    case Limit = 'limit';
    case Amendment = 'amendment';
    case Repeal = 'repeal';
    case SupportingContext = 'supporting_context';
}
