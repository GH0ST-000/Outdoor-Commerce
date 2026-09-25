<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalVerificationLevel: string
{
    case Unverified = 'unverified';
    case SourceAttached = 'source_attached';
    case ProvisionVerified = 'provision_verified';
    case LegallyReviewed = 'legally_reviewed';
}
