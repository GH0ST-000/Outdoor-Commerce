<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SpeciesContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
