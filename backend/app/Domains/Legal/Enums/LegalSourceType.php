<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalSourceType: string
{
    case LegislationPortal = 'legislation_portal';
    case OfficialWebsite = 'official_website';
    case OfficialGazette = 'official_gazette';
    case OfficialDocument = 'official_document';
    case ManualVerifiedSource = 'manual_verified_source';
}
