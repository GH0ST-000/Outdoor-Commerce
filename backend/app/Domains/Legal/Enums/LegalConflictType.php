<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalConflictType: string
{
    case ContradictoryEffect = 'contradictory_effect';
    case OverlappingLimit = 'overlapping_limit';
    case TemporalOverlap = 'temporal_overlap';
    case PrecedenceMissing = 'precedence_missing';
    case CitationConflict = 'citation_conflict';
    case SupersessionConflict = 'supersession_conflict';
    case DuplicateRule = 'duplicate_rule';
}
