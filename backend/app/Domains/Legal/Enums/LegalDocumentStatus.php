<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalDocumentStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Amended = 'amended';
    case Repealed = 'repealed';
    case Archived = 'archived';
    case Unknown = 'unknown';
}
