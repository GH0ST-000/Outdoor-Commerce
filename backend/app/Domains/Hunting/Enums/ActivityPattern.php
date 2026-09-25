<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum ActivityPattern: string
{
    case Diurnal = 'diurnal';
    case Nocturnal = 'nocturnal';
    case Crepuscular = 'crepuscular';
    case Cathemeral = 'cathemeral';
}
