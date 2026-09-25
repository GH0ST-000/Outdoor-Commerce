<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum AuthorityType: string
{
    case Legislature = 'legislature';
    case Ministry = 'ministry';
    case Agency = 'agency';
    case Municipality = 'municipality';
    case Other = 'other';
}
