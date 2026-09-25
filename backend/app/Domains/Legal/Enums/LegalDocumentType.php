<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalDocumentType: string
{
    case Law = 'law';
    case Regulation = 'regulation';
    case Decree = 'decree';
    case Order = 'order';
    case Resolution = 'resolution';
    case OfficialNotice = 'official_notice';
    case Amendment = 'amendment';
    case Guidance = 'guidance';
    case Other = 'other';
}
