<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\CitationPurpose;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalRule;

final class LegalCitationIntegrityValidator
{
    public function assertPublishable(LegalRule $rule): void
    {
        $rule->loadMissing(['citations.provision.version.document.source']);

        if ($rule->citations->isEmpty()) {
            throw LegalException::citationRequired();
        }

        $primary = $rule->citations->first(fn ($citation): bool => $citation->is_primary
            && $citation->citation_purpose === CitationPurpose::Authority);

        if ($primary === null) {
            throw LegalException::citationRequired();
        }

        foreach ($rule->citations as $citation) {
            $provision = $citation->provision;
            $version = $provision?->version;
            $source = $version?->document?->source;
            if ($provision === null || $version === null || $source === null) {
                throw LegalException::citationRequired();
            }
            if ($provision->review_status !== LegalReviewStatus::Approved) {
                throw LegalException::publicationInvalid(['reason' => 'provision_not_approved']);
            }
            if ($version->review_status !== LegalReviewStatus::Approved) {
                throw LegalException::publicationInvalid(['reason' => 'version_not_approved']);
            }
            if (! $source->isPublishableSource()) {
                throw LegalException::sourceUnverified();
            }
            if ($source->verification_status === LegalVerificationStatus::Suspended) {
                throw LegalException::sourceUnverified();
            }
        }
    }
}
