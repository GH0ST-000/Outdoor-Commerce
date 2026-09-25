<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalReviewType: string
{
    case SourceVerification = 'source_verification';
    case VersionVerification = 'version_verification';
    case ProvisionReview = 'provision_review';
    case RuleContentReview = 'rule_content_review';
    case LegalReview = 'legal_review';
    case PublicationReview = 'publication_review';
    case ConflictResolution = 'conflict_resolution';
}
